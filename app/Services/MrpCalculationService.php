<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\MpsPlan;
use App\Models\MrpCalculation;
use App\Models\Produk;
use Illuminate\Support\Carbon;

class MrpCalculationService
{
    public function calculate(MpsPlan $mps): array
    {
        $mps->load(['produk', 'items']);
        $totalQty = (int) $mps->items()->where('cleaning', false)->get()->sum(fn ($i) => $i->adjusted_qty ?? $i->target_qty);
        if ($totalQty <= 0) {
            return ['count' => 0, 'critical' => 0, 'warning' => 0, 'ok' => 0, 'error' => 'Grid kosong / target 0 — isi MPS terlebih dahulu'];
        }

        $bom = Bom::where('produk_id', $mps->produk_id)->where('is_active', true)->first();
        if (! $bom) {
            MrpCalculation::where('mps_plan_id', $mps->id)->delete();

            return ['count' => 0, 'critical' => 0, 'warning' => 0, 'ok' => 0, 'error' => 'BOM belum ada untuk produk '.$mps->produk->kode_produk.' — buat BOM aktif terlebih dahulu'];
        }

        $breakdown = $bom->getBreakdown((float) $totalQty);
        $allMaterials = array_merge($breakdown['raw_materials'] ?? [], $breakdown['primary_packaging'] ?? [], $breakdown['secondary_packaging'] ?? []);

        MrpCalculation::where('mps_plan_id', $mps->id)->delete();

        $days = Carbon::parse($mps->month_year.'-01')->daysInMonth;
        $dailyNeed = $days > 0 ? $totalQty / $days : $totalQty;
        $critical = 0;
        $warning = 0;
        $ok = 0;
        foreach ($allMaterials as $mat) {
            $matProdukId = $mat['material_produk_id'] ?? null;
            $matName = $mat['material_name'];
            $neededBase = (float) $mat['quantity'];
            $neededUom = $mat['uom'] ?? 'g';
            $qtyPerUnitBase = (float) ($mat['quantity_per_unit'] ?? 0);
            $begin = 0;
            $rop = 0;
            if ($matProdukId) {
                $matProd = Produk::with('uom')->find($matProdukId);
                $stockQty = (float) InventoryStock::where('produk_id', $matProdukId)->sum('quantity');
                if ($stockQty == 0) {
                    $stockQty = (float) InventoryStock::where('produk_id', $matProdukId)->where('location', 'GUDANG-UTAMA')->sum('quantity');
                }
                $stockUom = strtolower(trim($matProd?->uom?->code ?? $matProd?->odoo_uom ?? $neededUom));
                $stockFactor = ['g' => 1, 'gram' => 1, 'gr' => 1, 'kg' => 1000, 'kilogram' => 1000, 'ml' => 1, 'l' => 1000, 'liter' => 1000, 'ltr' => 1000][$stockUom] ?? 1;
                $begin = $stockQty * $stockFactor;
                $prod = $matProd;
                $rop = (float) ($prod->rop ?? 0);
                if ($rop > 0) {
                    $rop *= $stockFactor;
                } elseif ($rop <= 0) {
                    if ((float) ($prod->safety_stock ?? 0) > 0) {
                        $rop = (float) $prod->safety_stock * $stockFactor;
                    } elseif ((float) ($prod->min_stock ?? 0) > 0) {
                        $rop = (float) $prod->min_stock * $stockFactor;
                    } elseif (($prod->safety_stock_days ?? 0) > 0) {
                        $rop = $dailyNeed * (float) $prod->safety_stock_days * ($qtyPerUnitBase ?: 1);
                    }
                }
            }
            $needed = $neededBase;
            $incoming = 0;
            $available = $begin + $incoming;
            $shortage = max(0, $needed + $rop - $available);
            if ($shortage > 0 && $rop > 0 && $available < $rop) {
                $status = 'critical';
                $critical++;
            } elseif ($shortage > 0) {
                $status = 'warning';
                $warning++;
            } else {
                $status = 'ok';
                $ok++;
            }

            MrpCalculation::create([
                'mps_plan_id' => $mps->id,
                'produk_id' => $mps->produk_id,
                'material_produk_id' => $matProdukId,
                'material_name' => $matName,
                'needed_qty' => $needed,
                'needed_uom' => $neededUom,
                'beginning_stock' => $begin,
                'incoming_qty' => $incoming,
                'available_qty' => $available,
                'shortage_qty' => $shortage,
                'rop' => $rop,
                'status' => $status,
                'calculated_at' => now(),
            ]);
        }

        return ['count' => count($allMaterials), 'critical' => $critical, 'warning' => $warning, 'ok' => $ok];
    }

    public function calculateAggregated(string $monthYear): array
    {
        $plans = MpsPlan::where('month_year', $monthYear)->whereIn('status', ['approved', 'active'])->get();
        $agg = [];
        foreach ($plans as $plan) {
            $this->calculate($plan);
            foreach (MrpCalculation::where('mps_plan_id', $plan->id)->get() as $row) {
                $key = ($row->material_produk_id ?? 'x').'_'.strtolower(trim($row->material_name));
                if (! isset($agg[$key])) {
                    $agg[$key] = ['material_name' => $row->material_name, 'needed_qty' => 0, 'shortage_qty' => 0, 'plans' => []];
                }
                $agg[$key]['needed_qty'] += (float) $row->needed_qty;
                $agg[$key]['shortage_qty'] += (float) $row->shortage_qty;
                $agg[$key]['plans'][] = $plan->id;
            }
        }

        return $agg;
    }

    public function recalculateAllActive(): array
    {
        $plans = MpsPlan::whereIn('status', ['approved', 'active'])->get();
        $sum = ['count' => 0, 'critical' => 0, 'warning' => 0, 'ok' => 0];
        foreach ($plans as $p) {
            $r = $this->calculate($p);
            $sum['count'] += $r['count'] ?? 0;
            $sum['critical'] += $r['critical'] ?? 0;
            $sum['warning'] += $r['warning'] ?? 0;
            $sum['ok'] += $r['ok'] ?? 0;
        }

        return $sum;
    }
}
