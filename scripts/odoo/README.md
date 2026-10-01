# Odoo Seeder (Python)

Seed data integrasi LinePulse → Odoo Online (`pt-herbal`).

## Prasyarat

- Python 3 stdlib (macOS: `/usr/bin/python3` — **tanpa pip**)
- Odoo Online reachable + API Key valid
- Modules aktif di DB Odoo: `product`, `stock`, `mrp`, `sale`

## Setup env

```bash
export ODOO_HOST=https://pt-herbal.odoo.com
export ODOO_DB=pt-herbal
export ODOO_USERNAME=xiaomimob584@gmail.com
export ODOO_API_KEY=<API_KEY_BARU>   # rotate key lama; jangan commit
```

## Jalankan

```bash
# Preview
python3 scripts/odoo/odoo_seed.py --only master --dry-run

# Master: 2 partner + 9 produk
python3 scripts/odoo/odoo_seed.py --only master

# Transaksi: BOM + 3 MO + 2 scrap + 1 SO
python3 scripts/odoo/odoo_seed.py --only transactions

# Semua + re-create
python3 scripts/odoo/odoo_seed.py --only all --force
```

- **stdout** = JSON (untuk `php artisan odoo:seed`)
- **stderr** = log
- **exit** 0 sukses / 1 gagal

## Setelah seed

1. LinePulse → Admin → Odoo → **Sync Products**
2. PPIC → **Sync Odoo MO** (`Batch: BATCH-001` dst.)
3. Scrap Material → **Pull dari Odoo**
4. Delivery → **Sync SO**

Detail lengkap: `doc/ODOO_SEEDER_PLAN.md`
