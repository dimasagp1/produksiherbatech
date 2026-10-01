#!/usr/bin/env python3
"""Odoo seeder via JSON-RPC — stdlib only (no pip / venv).

Dipakai standalone atau oleh `php artisan odoo:seed` (hybrid).

Credentials (WAJIB, jangan hardcode):
  Env:
    ODOO_HOST, ODOO_DB, ODOO_USERNAME, ODOO_API_KEY
  Atau stdin JSON (untuk artisan):
    {"host":"...","db":"...","username":"...","api_key":"...",
     "only":"all|master|transactions","dry_run":false,"force":false}

Usage:
  python3 scripts/odoo/odoo_seed.py --only master --dry-run
  python3 scripts/odoo/odoo_seed.py --only transactions
  python3 scripts/odoo/odoo_seed.py --only all --force

stdout: JSON summary (dibaca artisan)
stderr: log manusiawi
exit: 0 sukses, 1 gagal
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.request
from datetime import date, timedelta
from typing import Any


# ---------------------------------------------------------------------------
# Dataset — sesuai doc/ODOO_SEEDER_PLAN.md §5
# ---------------------------------------------------------------------------

PARTNERS = [
    {"ref": "VND-001", "name": "PT Bahan Herbal Nusantara", "is_vendor": True},
    {"ref": "CUS-001", "name": "PT Distribusi Sehat Indonesia", "is_customer": True},
]

PRODUCTS = [
    {"default_code": "HB-001", "name": "Herbal Juice Lemon 1000ml", "category": "FG", "uom": "PCS", "sale_ok": True},
    {"default_code": "HB-002", "name": "Herbal Juice Jahe 1000ml", "category": "FG", "uom": "PCS", "sale_ok": True},
    {"default_code": "HB-003", "name": "Herbal Powder Kunyit 200g", "category": "FG", "uom": "PCS", "sale_ok": True},
    {"default_code": "RM-001", "name": "Ekstrak Jahe", "category": "RM", "uom": "KG", "sale_ok": False},
    {"default_code": "RM-002", "name": "Gula Aren", "category": "RM", "uom": "KG", "sale_ok": False},
    {"default_code": "RM-003", "name": "Air Mineral", "category": "RM", "uom": "L", "sale_ok": False},
    {"default_code": "PM-001", "name": "Botol PET 1000ml", "category": "PM", "uom": "PCS", "sale_ok": False},
    {"default_code": "PM-002", "name": "Label Produk", "category": "PM", "uom": "PCS", "sale_ok": False},
    {"default_code": "PM-003", "name": "Kardus Outer", "category": "PM", "uom": "PCS", "sale_ok": False},
]

BOMS = [
    {
        "product_default_code": "HB-001",
        "lines": [
            {"material": "RM-001", "qty": 0.15},
            {"material": "RM-002", "qty": 0.08},
            {"material": "PM-001", "qty": 1.0},
            {"material": "PM-002", "qty": 1.0},
        ],
    }
]

MANUFACTURING_ORDERS = [
    {
        "name": "MO/SEED/0001",
        "origin": "Batch: BATCH-001",
        "product": "HB-001",
        "qty": 2000,
        "state": "confirmed",
        "day_offset": 1,
    },
    {
        "name": "MO/SEED/0002",
        "origin": "Batch: BATCH-002",
        "product": "HB-002",
        "qty": 1500,
        "state": "progress",
        "day_offset": 2,
    },
    {
        "name": "MO/SEED/0003",
        "origin": "Batch: BATCH-003",
        "product": "HB-003",
        "qty": 1000,
        "state": "cancel",
        "day_offset": 3,
    },
]

SCRAPS = [
    {"origin": "Batch: BATCH-001", "product": "RM-001", "qty": 2.5, "state": "done"},
    {"origin": "Batch: BATCH-001", "product": "PM-002", "qty": 15.0, "state": "done"},
]

SALE_ORDERS = [
    {
        "name": "SO/SEED/0001",
        "partner": "CUS-001",
        "state": "sale",
        "line_product": "HB-001",
        "line_qty": 100.0,
        "price_unit": 250000.0,
    }
]

# ScmUom code LinePulse → kandidat nama di Odoo (biasanya English)
UOM_CANDIDATES = {
    "PCS": ["Units", "Pieces", "PCS", "Pcs", "Unit"],
    "KG": ["Kilograms", "Kilogram", "KG", "kg"],
    "L": ["Liters", "Liter", "L", "ltr"],
    "BOX": ["Boxes", "Box", "BOX"],
    "MTR": ["Meters", "Meter", "MTR"],
}

SEED_PREFIXES = ("HB-", "RM-", "PM-", "MO/SEED/", "SO/SEED/")
SEED_PARTNER_REFS = ("VND-001", "CUS-001")


def eprint(*args: Any, **kwargs: Any) -> None:
    print(*args, file=sys.stderr, **kwargs)


def next_workdays(count: int, start: date | None = None) -> list[date]:
    """Hitung `count` hari kerja (Senin–Jumat) mulai hari berikutnya."""
    d = (start or date.today()) + timedelta(days=1)
    days: list[date] = []
    while len(days) < count:
        if d.weekday() < 5:
            days.append(d)
        d += timedelta(days=1)
    return days


# ---------------------------------------------------------------------------
# Odoo JSON-RPC client (stdlib)
# ---------------------------------------------------------------------------


class OdooClient:
    def __init__(self, host: str, db: str, username: str, api_key: str, timeout: int = 30):
        self.host = host.rstrip("/")
        self.db = db
        self.username = username
        self.api_key = api_key
        self.timeout = timeout
        self.uid: int | None = None
        self.server_version: str = "unknown"
        self._rpc_id = 0

    def _next_id(self) -> int:
        self._rpc_id += 1
        return self._rpc_id

    def _post(self, payload: dict) -> dict:
        url = f"{self.host}/jsonrpc"
        data = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(
            url,
            data=data,
            headers={
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                body = resp.read().decode("utf-8")
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")[:500]
            raise RuntimeError(f"HTTP {exc.code} ke {url}: {detail}") from exc
        except urllib.error.URLError as exc:
            raise RuntimeError(f"Gagal koneksi ke {url}: {exc.reason}") from exc

        try:
            return json.loads(body)
        except json.JSONDecodeError as exc:
            raise RuntimeError(f"Respons Odoo bukan JSON valid: {body[:300]}") from exc

    def version(self) -> dict:
        payload = {
            "jsonrpc": "2.0",
            "method": "call",
            "params": {"service": "common", "method": "version", "args": []},
            "id": self._next_id(),
        }
        result = self._post(payload).get("result") or {}
        self.server_version = str(result.get("server_version", "unknown"))
        return result

    def authenticate(self) -> int:
        if self.uid is not None:
            return self.uid

        if not all([self.host, self.db, self.username, self.api_key]):
            raise RuntimeError(
                "Kredensial Odoo belum lengkap. Set env ODOO_HOST, ODOO_DB, "
                "ODOO_USERNAME, ODOO_API_KEY (atau kirim JSON via stdin)."
            )

        self.version()

        payload = {
            "jsonrpc": "2.0",
            "method": "call",
            "params": {
                "service": "common",
                "method": "authenticate",
                "args": [self.db, self.username, self.api_key, {}],
            },
            "id": self._next_id(),
        }
        body = self._post(payload)
        if "error" in body:
            err = body["error"]
            msg = (err.get("data") or {}).get("message") or err.get("message") or "RPC Error"
            raise RuntimeError(f"Autentikasi Odoo gagal: {msg}")

        uid = body.get("result")
        if not uid or not isinstance(uid, int):
            raise RuntimeError(
                "Autentikasi Odoo gagal. Periksa email, database, dan API Key."
            )
        self.uid = uid
        eprint(f"[odoo] Auth OK uid={uid} version={self.server_version}")
        return uid

    def execute_kw(self, model: str, method: str, args: list | None = None, kwargs: dict | None = None) -> Any:
        uid = self.authenticate()
        payload = {
            "jsonrpc": "2.0",
            "method": "call",
            "params": {
                "service": "object",
                "method": "execute_kw",
                "args": [self.db, uid, self.api_key, model, method, args or [], kwargs or {}],
            },
            "id": self._next_id(),
        }
        body = self._post(payload)
        if "error" in body:
            err = body["error"]
            msg = (err.get("data") or {}).get("message") or err.get("message") or "RPC Error"
            raise RuntimeError(f"{model}::{method}: {msg}")
        return body.get("result")

    def search_read(
        self,
        model: str,
        domain: list | None = None,
        fields: list | None = None,
        limit: int = 1,
    ) -> list[dict]:
        kwargs: dict[str, Any] = {"limit": limit}
        if fields:
            kwargs["fields"] = fields
        result = self.execute_kw(model, "search_read", [domain or []], kwargs)
        return result if isinstance(result, list) else []

    def search_ids(self, model: str, domain: list, limit: int = 10) -> list[int]:
        result = self.execute_kw(model, "search", [domain], {"limit": limit})
        return [int(x) for x in result] if isinstance(result, list) else []

    def model_exists(self, model: str) -> bool:
        try:
            rows = self.execute_kw(
                "ir.model",
                "search_read",
                [[("model", "=", model)]],
                {"fields": ["model"], "limit": 1},
            )
            return bool(rows)
        except Exception:  # noqa: BLE001
            return False

    def read(self, model: str, res_ids: list[int], fields: list) -> list[dict]:
        if not res_ids:
            return []
        result = self.execute_kw(model, "read", [res_ids], {"fields": fields})
        return result if isinstance(result, list) else []

    def create(self, model: str, vals: dict) -> int:
        result = self.execute_kw(model, "create", [vals])
        if not isinstance(result, int):
            raise RuntimeError(f"{model}.create return bukan int: {result!r}")
        return result

    def write(self, model: str, res_id: int, vals: dict) -> bool:
        return bool(self.execute_kw(model, "write", [[res_id], vals]))

    def unlink(self, model: str, res_ids: list[int]) -> bool:
        if not res_ids:
            return True
        return bool(self.execute_kw(model, "unlink", [res_ids]))

    def call_button(self, model: str, method: str, res_id: int) -> Any:
        return self.execute_kw(model, method, [[res_id]])


# ---------------------------------------------------------------------------
# Seeder logic
# ---------------------------------------------------------------------------


class Seeder:
    def __init__(self, client: OdooClient, dry_run: bool = False, force: bool = False):
        self.client = client
        self.dry_run = dry_run
        self.force = force
        self.uom_cache: dict[str, int] = {}
        self.product_cache: dict[str, int] = {}
        self.partner_cache: dict[str, int] = {}
        self.results: dict[str, dict[str, Any]] = {}

    def _bucket(self, model: str) -> dict[str, Any]:
        if model not in self.results:
            self.results[model] = {"model": model, "created": 0, "updated": 0, "skipped": 0, "errors": []}
        return self.results[model]

    def _bump(self, model: str, key: str, n: int = 1) -> None:
        self._bucket(model)[key] += n

    def _error(self, model: str, msg: str) -> None:
        eprint(f"[error] {model}: {msg}")
        self._bucket(model)["errors"].append(msg)

    # -- master -----------------------------------------------------------

    def _uom_models(self) -> list[str]:
        """Odoo 17+: uom.uom. Odoo ≤16: product.uom (alias lama)."""
        return ["uom.uom", "product.uom"]

    def resolve_uom(self, code: str) -> int:
        if code in self.uom_cache:
            return self.uom_cache[code]

        candidates = UOM_CANDIDATES.get(code, [code])
        for model in self._uom_models():
            for name in candidates:
                try:
                    rows = self.client.search_read(
                        model,
                        [("name", "=", name)],
                        ["id", "name"],
                        limit=1,
                    )
                except Exception as exc:  # noqa: BLE001 — model mungkin tidak ada di versi ini
                    eprint(f"[uom] skip model {model}: {exc}")
                    continue
                if rows:
                    self.uom_cache[code] = int(rows[0]["id"])
                    eprint(f"[uom] {code} → {rows[0]['name']} (id={rows[0]['id']}, model={model})")
                    return self.uom_cache[code]

            # fallback fuzzy
            for name in candidates:
                try:
                    rows = self.client.search_read(
                        model,
                        [("name", "ilike", name)],
                        ["id", "name"],
                        limit=5,
                    )
                except Exception:  # noqa: BLE001
                    continue
                if rows:
                    self.uom_cache[code] = int(rows[0]["id"])
                    eprint(f"[uom] {code} → fuzzy {rows[0]['name']} (id={rows[0]['id']}, model={model})")
                    return self.uom_cache[code]

        raise RuntimeError(
            f"UoM '{code}' tidak ditemukan di Odoo. Kandidat: {candidates}. "
            f"Pastikan uom Odoo match ScmMasterSeeder (PCS/KG/L/BOX/MTR)."
        )

    def _product_vals(self, item: dict, uom_id: int) -> dict:
        major = 0
        try:
            major = int(str(self.client.server_version).split(".")[0])
        except (ValueError, IndexError):
            major = 0

        sale_ok = bool(item.get("sale_ok", True))
        vals: dict[str, Any] = {
            "name": item["name"],
            "default_code": item["default_code"],
            "sale_ok": sale_ok,
            "purchase_ok": item["category"] in ("RM", "PM"),
            "uom_id": uom_id,
        }

        if major >= 17:
            # Odoo 17+: type combo/consu/service + is_storable; uom_po_id tidak lagi di product.product
            vals["type"] = "consu"
            vals["is_storable"] = item["category"] in ("FG", "RM", "PM")
        else:
            # Odoo ≤16
            vals["type"] = "product"
            vals["uom_po_id"] = uom_id

        return vals

    def seed_partners(self) -> None:
        model = "res.partner"
        for item in PARTNERS:
            ref = item["ref"]
            name = item["name"]
            try:
                existing = self.client.search_read(
                    model,
                    ["|", ("ref", "=", ref), ("name", "=", name)],
                    ["id", "ref", "name"],
                    limit=1,
                )
                if existing and not self.force:
                    self.partner_cache[ref] = int(existing[0]["id"])
                    self._bump(model, "skipped")
                    eprint(f"[partner] skip existing {ref} id={existing[0]['id']}")
                    continue

                if existing and self.force:
                    self.client.unlink(model, [int(r["id"]) for r in existing])

                if self.dry_run:
                    self._bump(model, "created")  # dry-run: skor "akan create"
                    eprint(f"[partner] DRY-RUN would create {ref} {name}")
                    continue

                vals = {
                    "name": name,
                    "ref": ref,
                    "is_company": True,
                    "supplier_rank": 1 if item.get("is_vendor") else 0,
                    "customer_rank": 1 if item.get("is_customer") else 0,
                }
                pid = self.client.create(model, vals)
                self.partner_cache[ref] = pid
                self._bump(model, "created")
                eprint(f"[partner] created {ref} id={pid}")
            except Exception as exc:  # noqa: BLE001
                self._error(model, f"{ref}: {exc}")

    def seed_products(self) -> None:
        model = "product.product"
        for item in PRODUCTS:
            code = item["default_code"]
            try:
                uom_id = self.resolve_uom(item["uom"])
                existing = self.client.search_read(
                    model,
                    [("default_code", "=", code)],
                    ["id", "default_code", "name"],
                    limit=1,
                )
                if existing and not self.force:
                    self.product_cache[code] = int(existing[0]["id"])
                    self._bump(model, "skipped")
                    eprint(f"[product] skip existing {code} id={existing[0]['id']}")
                    continue

                if existing and self.force:
                    self.client.unlink(model, [int(r["id"]) for r in existing])

                vals = self._product_vals(item, uom_id)

                if self.dry_run:
                    self._bump(model, "created")
                    eprint(f"[product] DRY-RUN would create {code} {item['name']}")
                    continue

                pid = self.client.create(model, vals)
                self.product_cache[code] = pid
                self._bump(model, "created")
                eprint(f"[product] created {code} id={pid}")
            except Exception as exc:  # noqa: BLE001
                self._error(model, f"{code}: {exc}")

    def _ensure_product_id(self, code: str) -> int:
        if code in self.product_cache:
            return self.product_cache[code]
        rows = self.client.search_read(
            "product.product",
            [("default_code", "=", code)],
            ["id"],
            limit=1,
        )
        if not rows:
            raise RuntimeError(f"Produk Odoo '{code}' belum ada. Jalankan seed master dulu.")
        self.product_cache[code] = int(rows[0]["id"])
        return self.product_cache[code]

    def _ensure_partner_id(self, ref: str) -> int:
        if ref in self.partner_cache:
            return self.partner_cache[ref]
        rows = self.client.search_read(
            "res.partner",
            ["|", ("ref", "=", ref), ("name", "=", ref)],
            ["id"],
            limit=1,
        )
        if not rows:
            raise RuntimeError(f"Partner Odoo '{ref}' belum ada. Jalankan seed master dulu.")
        self.partner_cache[ref] = int(rows[0]["id"])
        return self.partner_cache[ref]

    # -- transactions -----------------------------------------------------

    def seed_boms(self) -> None:
        model = "mrp.bom"
        for bom in BOMS:
            code = bom["product_default_code"]
            try:
                product_id = self._ensure_product_id(code)
                tmpl_rows = self.client.search_read(
                    "product.product",
                    [("default_code", "=", code)],
                    ["product_tmpl_id"],
                    limit=1,
                )
                if not tmpl_rows:
                    raise RuntimeError(f"product_tmpl untuk {code} tidak ditemukan")
                tmpl_id = tmpl_rows[0]["product_tmpl_id"]
                if isinstance(tmpl_id, (list, tuple)):
                    tmpl_id = tmpl_id[0]

                existing = self.client.search_read(
                    model,
                    [("product_tmpl_id", "=", int(tmpl_id))],
                    ["id"],
                    limit=1,
                )
                if existing and not self.force:
                    self._bump(model, "skipped")
                    eprint(f"[bom] skip existing for {code}")
                    continue
                if existing and self.force:
                    self.client.unlink(model, [int(r["id"]) for r in existing])

                bom_lines = []
                for line in bom["lines"]:
                    mat_id = self._ensure_product_id(line["material"])
                    bom_lines.append(
                        (0, 0, {"product_id": mat_id, "product_qty": float(line["qty"])})
                    )

                if self.dry_run:
                    self._bump(model, "created")
                    eprint(f"[bom] DRY-RUN would create for {code}")
                    continue

                bid = self.client.create(
                    model,
                    {
                        "product_tmpl_id": int(tmpl_id),
                        "product_qty": 1.0,
                        "type": "normal",
                        "bom_line_ids": bom_lines,
                    },
                )
                self._bump(model, "created")
                eprint(f"[bom] created for {code} id={bid} (product_id={product_id})")
            except Exception as exc:  # noqa: BLE001
                self._error(model, f"{code}: {exc}")

    def seed_manufacturing_orders(self) -> None:
        model = "mrp.production"
        days = next_workdays(len(MANUFACTURING_ORDERS))

        for item, day in zip(MANUFACTURING_ORDERS, days):
            name = item["name"]
            try:
                product_id = self._ensure_product_id(item["product"])
                existing = self.client.search_read(
                    model,
                    [("name", "=", name)],
                    ["id", "name", "state"],
                    limit=1,
                )
                if existing and not self.force:
                    self._bump(model, "skipped")
                    eprint(f"[mo] skip existing {name} state={existing[0].get('state')}")
                    continue
                if existing and self.force:
                    old_id = int(existing[0]["id"])
                    try:
                        self.client.call_button(model, "action_cancel", old_id)
                    except Exception:  # noqa: BLE001
                        pass
                    self.client.unlink(model, [old_id])

                if self.dry_run:
                    self._bump(model, "created")
                    eprint(
                        f"[mo] DRY-RUN would create {name} origin={item['origin']} "
                        f"state={item['state']} date={day.isoformat()}"
                    )
                    continue

                vals = {
                    "product_id": product_id,
                    "product_qty": float(item["qty"]),
                    "origin": item["origin"],
                    "date_start": day.isoformat(),
                    "date_finished": day.isoformat(),
                }
                mo_id = self.client.create(model, vals)
                state = item["state"]

                if state in ("confirmed", "progress"):
                    try:
                        self.client.call_button(model, "action_confirm", mo_id)
                    except Exception as exc:  # noqa: BLE001
                        self._error(model, f"{name} action_confirm: {exc}")
                elif state == "cancel":
                    try:
                        self.client.call_button(model, "action_cancel", mo_id)
                    except Exception as exc:  # noqa: BLE001
                        self._error(model, f"{name} action_cancel: {exc}")

                self._bump(model, "created")
                eprint(f"[mo] created {name} id={mo_id} state_target={state}")
            except Exception as exc:  # noqa: BLE001
                self._error(model, f"{name}: {exc}")

    def seed_scraps(self) -> None:
        model = "stock.scrap"
        if not self.client.model_exists(model):
            self._error(
                model,
                "Model stock.scrap tidak ada di DB Odoo ini (custom build?). "
                "Seed scrap dilewati — pakai input Scrap Material manual di LinePulse.",
            )
            return
        for item in SCRAPS:
            label = f"{item['origin']}|{item['product']}|{item['qty']}"
            try:
                product_id = self._ensure_product_id(item["product"])
                existing = self.client.search_read(
                    model,
                    [
                        ("origin", "=", item["origin"]),
                        ("product_id", "=", product_id),
                    ],
                    ["id", "origin", "scrap_qty"],
                    limit=5,
                )
                already = any(
                    abs(float(r.get("scrap_qty") or 0) - float(item["qty"])) < 0.001
                    for r in existing
                )
                if (existing and already) and not self.force:
                    self._bump(model, "skipped")
                    eprint(f"[scrap] skip existing {label}")
                    continue
                if existing and self.force:
                    self.client.unlink(model, [int(r["id"]) for r in existing])

                if self.dry_run:
                    self._bump(model, "created")
                    eprint(f"[scrap] DRY-RUN would create {label}")
                    continue

                uom_rows = self.client.search_read(
                    "product.product",
                    [("default_code", "=", item["product"])],
                    ["uom_id"],
                    limit=1,
                )
                uom_id = None
                if uom_rows and uom_rows[0].get("uom_id"):
                    uom_id = uom_rows[0]["uom_id"]
                    if isinstance(uom_id, (list, tuple)):
                        uom_id = uom_id[0]

                vals = {
                    "product_id": product_id,
                    "scrap_qty": float(item["qty"]),
                    "origin": item["origin"],
                }
                if uom_id:
                    vals["product_uom_id"] = int(uom_id)

                scrap_id = self.client.create(model, vals)
                if item.get("state") == "done":
                    try:
                        self.client.call_button(model, "action_validate", scrap_id)
                    except Exception as exc:  # noqa: BLE001
                        self._error(model, f"scrap action_validate: {exc}")

                self._bump(model, "created")
                eprint(f"[scrap] created {label} id={scrap_id}")
            except Exception as exc:  # noqa: BLE001
                self._error(model, f"{label}: {exc}")

    def seed_sale_orders(self) -> None:
        model = "sale.order"
        for item in SALE_ORDERS:
            name = item["name"]
            try:
                partner_id = self._ensure_partner_id(item["partner"])
                product_id = self._ensure_product_id(item["line_product"])

                existing = self.client.search_read(
                    model,
                    [("name", "=", name)],
                    ["id", "name", "state"],
                    limit=1,
                )
                if existing and not self.force:
                    self._bump(model, "skipped")
                    eprint(f"[so] skip existing {name}")
                    continue
                if existing and self.force:
                    old_id = int(existing[0]["id"])
                    try:
                        self.client.call_button(model, "action_cancel", old_id)
                    except Exception:  # noqa: BLE001
                        pass
                    self.client.unlink(model, [old_id])

                if self.dry_run:
                    self._bump(model, "created")
                    eprint(f"[so] DRY-RUN would create {name}")
                    continue

                vals = {
                    "partner_id": partner_id,
                    "date_order": date.today().isoformat(),
                    "order_line": [
                        (
                            0,
                            0,
                            {
                                "product_id": product_id,
                                "product_uom_qty": float(item["line_qty"]),
                                "price_unit": float(item["price_unit"]),
                            },
                        )
                    ],
                }
                so_id = self.client.create(model, vals)
                if item.get("state") == "sale":
                    try:
                        self.client.call_button(model, "action_confirm", so_id)
                    except Exception as exc:  # noqa: BLE001
                        self._error(model, f"{name} action_confirm: {exc}")

                self._bump(model, "created")
                eprint(f"[so] created {name} id={so_id}")
            except Exception as exc:  # noqa: BLE001
                self._error(model, f"{name}: {exc}")

    def run(self, only: str) -> dict[str, Any]:
        only = (only or "all").lower()
        if only not in ("all", "master", "transactions"):
            raise RuntimeError(f"Nilai --only tidak valid: {only!r} (pakai all|master|transactions)")

        # Auth dulu — gagal lebih awal sebelum loop
        self.client.authenticate()

        if only in ("all", "master"):
            self.seed_partners()
            self.seed_products()

        if only in ("all", "transactions"):
            # pastikan product cache terisi walau master dilewati
            for item in PRODUCTS:
                if item["default_code"] not in self.product_cache:
                    rows = self.client.search_read(
                        "product.product",
                        [("default_code", "=", item["default_code"])],
                        ["id"],
                        limit=1,
                    )
                    if rows:
                        self.product_cache[item["default_code"]] = int(rows[0]["id"])
            for p in PARTNERS:
                if p["ref"] not in self.partner_cache:
                    rows = self.client.search_read(
                        "res.partner",
                        [("ref", "=", p["ref"])],
                        ["id"],
                        limit=1,
                    )
                    if rows:
                        self.partner_cache[p["ref"]] = int(rows[0]["id"])

            self.seed_boms()
            self.seed_manufacturing_orders()
            self.seed_scraps()
            self.seed_sale_orders()

        return {
            "success": True,
            "host": self.client.host,
            "db": self.client.db,
            "server_version": self.client.server_version,
            "dry_run": self.dry_run,
            "force": self.force,
            "only": only,
            "results": list(self.results.values()),
        }


# ---------------------------------------------------------------------------
# Config + CLI
# ---------------------------------------------------------------------------


def load_config(args: argparse.Namespace) -> dict[str, Any]:
    cfg: dict[str, Any] = {
        "host": os.environ.get("ODOO_HOST", "").strip(),
        "db": os.environ.get("ODOO_DB", "").strip(),
        "username": os.environ.get("ODOO_USERNAME", "").strip(),
        "api_key": os.environ.get("ODOO_API_KEY", "").strip(),
        "timeout": int(os.environ.get("ODOO_TIMEOUT", "30") or 30),
        "only": args.only,
        "dry_run": args.dry_run,
        "force": args.force,
    }

    # stdin JSON (artisan) — override env
    if not sys.stdin.isatty():
        raw = sys.stdin.read()
        if raw and raw.strip():
            try:
                payload = json.loads(raw)
            except json.JSONDecodeError as exc:
                raise RuntimeError(f"stdin JSON tidak valid: {exc}") from exc
            if not isinstance(payload, dict):
                raise RuntimeError("stdin JSON harus berupa object")
            for key in ("host", "db", "username", "api_key"):
                if payload.get(key):
                    cfg[key] = str(payload[key]).strip()
            if payload.get("timeout"):
                cfg["timeout"] = int(payload["timeout"])
            if args.only == "all" and payload.get("only"):
                cfg["only"] = str(payload["only"])
            if not args.dry_run and payload.get("dry_run") is not None:
                cfg["dry_run"] = bool(payload["dry_run"])
            if not args.force and payload.get("force") is not None:
                cfg["force"] = bool(payload["force"])

    # CLI override untuk host/db/username/only (api_key TIDAK lewat argv)
    if args.host:
        cfg["host"] = args.host.rstrip("/")
    if args.db:
        cfg["db"] = args.db
    if args.username:
        cfg["username"] = args.username

    missing = [k for k in ("host", "db", "username", "api_key") if not cfg.get(k)]
    if missing:
        raise RuntimeError(
            "Kredensial belum lengkap: "
            + ", ".join(missing)
            + ". Set env ODOO_HOST, ODOO_DB, ODOO_USERNAME, ODOO_API_KEY "
            + "atau kirim JSON via stdin. API key jangan lewat argv (terlihat di ps)."
        )

    if not cfg["host"].startswith("http"):
        cfg["host"] = "https://" + cfg["host"]

    return cfg


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        prog="odoo_seed.py",
        description="Seed data demo/integrasi ke Odoo via JSON-RPC (stdlib only).",
    )
    p.add_argument(
        "--only",
        choices=["all", "master", "transactions"],
        default="all",
        help="Scope seed (default: all)",
    )
    p.add_argument("--dry-run", action="store_true", help="Preview tanpa create")
    p.add_argument("--force", action="store_true", help="Re-create data seed lama")
    p.add_argument("--host", default=None, help="Override ODOO_HOST")
    p.add_argument("--db", default=None, help="Override ODOO_DB")
    p.add_argument("--username", default=None, help="Override ODOO_USERNAME")
    # api_key sengaja TIDAK ada di argparse
    return p


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    try:
        cfg = load_config(args)
        client = OdooClient(
            host=cfg["host"],
            db=cfg["db"],
            username=cfg["username"],
            api_key=cfg["api_key"],
            timeout=cfg["timeout"],
        )
        seeder = Seeder(client, dry_run=cfg["dry_run"], force=cfg["force"])
        summary = seeder.run(cfg["only"])
        print(json.dumps(summary, ensure_ascii=False, indent=2))
        eprint("[odoo] Seed selesai.")
        return 0
    except Exception as exc:  # noqa: BLE001
        err = {
            "success": False,
            "error": str(exc),
            "results": [],
        }
        print(json.dumps(err, ensure_ascii=False, indent=2))
        eprint(f"[odoo] GAGAL: {exc}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
