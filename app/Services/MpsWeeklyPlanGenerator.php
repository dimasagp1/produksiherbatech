<?php

namespace App\Services;

use App\Models\MpsItem;
use App\Models\MpsPlan;
use App\Models\WeeklyPlan;
use Illuminate\Support\Carbon;

class MpsWeeklyPlanGenerator
{
    /**
     * Generate WeeklyPlan drafts from MPS items
     *
     * @return array{created:int, skipped:int, errors:array<string>}
     */
    public function generate(MpsPlan $plan): array
    {
        $summary = ['created' => 0, 'skipped' => 0, 'errors' => []];

        $plan->load(['produk', 'workCenter', 'items.line', 'items.workCenter']);

        if ($plan->status !== 'approved' && $plan->status !== 'active') {
            $summary['errors'][] = 'Plan must be approved or active to generate Weekly Plans';

            return $summary;
        }

        $items = $plan->items()
            ->where('cleaning', false)
            ->where('target_qty', '>', 0)
            ->with(['line', 'workCenter'])
            ->get();

        if ($items->isEmpty()) {
            $summary['errors'][] = 'No valid MPS items to generate (all cleaning or zero target)';

            return $summary;
        }

        foreach ($items as $item) {
            try {
                // Check if WeeklyPlan already exists for this item
                $existing = WeeklyPlan::where('produk_id', $plan->produk_id)
                    ->where('tanggal', $item->tanggal)
                    ->where('line_id', $item->line_id)
                    ->where('shift', $item->shift)
                    ->where('work_center_id', $item->work_center_id ?? $plan->work_center_id)
                    ->where('mps_plan_id', $plan->id)
                    ->first();

                if ($existing) {
                    $summary['skipped']++;

                    continue;
                }

                $wc = $item->workCenter ?? $plan->workCenter;
                if (! $wc) {
                    $summary['errors'][] = "Item {$item->id}: No Work Center defined";

                    continue;
                }

                $batchNumber = $this->generateBatchNumber($plan, $item);

                WeeklyPlan::create([
                    'produk_id' => $plan->produk_id,
                    'line_id' => $item->line_id,
                    'work_center_id' => $item->work_center_id ?? $plan->work_center_id,
                    'mps_plan_id' => $plan->id,
                    'proses' => $wc->type,
                    'batch_number' => $batchNumber,
                    'odoo_mo_id' => null,
                    'mo_status' => WeeklyPlan::MO_STATUS_PENDING,
                    'target_output' => $item->target_qty,
                    'mp_count' => $item->mp_count,
                    'multiplier' => $wc->fit_mp ?? 1,
                    'packing_hold' => false,
                    'tanggal' => $item->tanggal,
                    'status' => 'draft',
                    'created_by' => $plan->created_by,
                    'shift' => $item->shift,
                ]);

                $summary['created']++;
            } catch (\Exception $e) {
                $summary['errors'][] = "Item {$item->id}: {$e->getMessage()}";
            }
        }

        return $summary;
    }

    /**
     * Generate batch number format: MPS-{PRODUK_CODE}-{YYYYMMDD}-{SHIFT}-{SEQ}
     */
    private function generateBatchNumber(MpsPlan $plan, MpsItem $item): string
    {
        $produkCode = $plan->produk->kode_produk ?? 'PRD';
        $dateStr = Carbon::parse($item->tanggal)->format('Ymd');
        $shiftCode = $item->shift === 'shift1' ? 'S1' : 'S2';

        // Count existing MPS-generated plans for same product/date/shift
        $seq = WeeklyPlan::where('mps_plan_id', $plan->id)
            ->where('batch_number', 'like', "MPS-{$produkCode}-{$dateStr}-{$shiftCode}-%")
            ->count() + 1;

        return "MPS-{$produkCode}-{$dateStr}-{$shiftCode}-".str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
