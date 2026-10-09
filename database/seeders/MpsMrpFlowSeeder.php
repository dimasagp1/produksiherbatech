<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\InventoryStock;
use App\Models\Line;
use App\Models\MpsItem;
use App\Models\MpsPlan;
use App\Models\MrpCalculation;
use App\Models\Produk;
use App\Models\ScmUom;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use App\Services\MpsWeeklyPlanGenerator;
use App\Services\MrpCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MpsMrpFlowSeeder extends Seeder
{
    public function run(): void
    {
        $ppic = User::role('ppic')->first() ?? User::first();
        $monthYear = now()->format('Y-m');
        $daysInMonth = Carbon::parse($monthYear.'-01')->daysInMonth;

        $fg = Produk::where('item_type', 'fg')->where('status_aktif', true)->first()
            ?? Produk::where('status_aktif', true)->first()
            ?? Produk::first();
        if (! $fg) {
            $this->command->error('No produk found. Run MasterDataSeeder first.');

            return;
        }

        $wc = WorkCenter::where('type', 'mixing')->where('is_active', true)->first()
            ?? WorkCenter::where('is_active', true)->first();
        $lines = Line::where('status_aktif', true)->orderBy('kode_line')->get();
        if ($lines->isEmpty()) {
            $this->command->error('No lines found.');

            return;
        }

        $uomPcs = ScmUom::where('code', 'PCS')->first()?->id;
        $uomKg = ScmUom::where('code', 'KG')->first()?->id;

        $rm1 = Produk::firstOrCreate(['kode_produk' => 'RM-DEMO-01'], [
            'nama_produk' => 'Ekstrak Jahe Bubuk',
            'proses_default' => 'mixing',
            'item_type' => 'rm',
            'status_aktif' => true,
            'uom_id' => $uomKg,
            'safety_stock' => 50,
            'min_stock' => 30,
            'rop' => 100,
            'lead_time_days' => 7,
            'safety_stock_days' => 3,
        ]);
        $rm2 = Produk::firstOrCreate(['kode_produk' => 'PM-DEMO-01'], [
            'nama_produk' => 'Botol PET 1000ml',
            'proses_default' => 'filling',
            'item_type' => 'pm',
            'status_aktif' => true,
            'uom_id' => $uomPcs,
            'safety_stock' => 200,
            'min_stock' => 100,
            'rop' => 500,
            'lead_time_days' => 5,
            'safety_stock_days' => 2,
        ]);
        $rm3 = Produk::firstOrCreate(['kode_produk' => 'RM-DEMO-02'], [
            'nama_produk' => 'Gula Aren Cair',
            'proses_default' => 'mixing',
            'item_type' => 'rm',
            'status_aktif' => true,
            'uom_id' => $uomKg,
            'safety_stock' => 80,
            'min_stock' => 40,
            'rop' => 150,
        ]);

        foreach ([$rm1, $rm2, $rm3] as $rm) {
            InventoryStock::updateOrCreate(
                ['produk_id' => $rm->id, 'location' => 'GUDANG-UTAMA'],
                ['quantity' => match ($rm->kode_produk) {
                    'RM-DEMO-01' => 40,
                    'PM-DEMO-01' => 600,
                    'RM-DEMO-02' => 200,
                    default => 100,
                }, 'is_baseline' => true, 'snapshot_date' => now()->toDateString()]
            );
        }
        InventoryStock::updateOrCreate(
            ['produk_id' => $fg->id, 'location' => 'GUDANG-UTAMA'],
            ['quantity' => 2500, 'is_baseline' => true, 'snapshot_date' => now()->toDateString()]
        );

        $bom = Bom::firstOrCreate(['produk_id' => $fg->id, 'is_active' => true], [
            'version' => 'DEMO-1',
            'base_qty' => 1,
            'uom_id' => $uomPcs,
            'notes' => 'Demo BOM for MRP flow',
            'created_by' => $ppic->id,
        ]);
        BomItem::where('bom_id', $bom->id)->delete();
        foreach ([
            ['produk' => $rm1, 'qty' => 0.05, 'uom' => $uomKg, 'name' => $rm1->nama_produk],
            ['produk' => $rm2, 'qty' => 1, 'uom' => $uomPcs, 'name' => $rm2->nama_produk],
            ['produk' => $rm3, 'qty' => 0.02, 'uom' => $uomKg, 'name' => $rm3->nama_produk],
        ] as $it) {
            BomItem::create([
                'bom_id' => $it['produk']->id ? $bom->id : $bom->id,
                'material_produk_id' => $it['produk']->id,
                'material_name' => $it['name'],
                'quantity' => $it['qty'],
                'uom_id' => $it['uom'],
                'uom_name' => $it['uom'] ? ScmUom::find($it['uom'])?->code : 'PCS',
            ]);
        }

        $plan = MpsPlan::where('produk_id', $fg->id)->where('work_center_id', $wc->id)->where('month_year', $monthYear)->first();
        if ($plan) {
            WeeklyPlan::where('mps_plan_id', $plan->id)->delete();
            MrpCalculation::where('mps_plan_id', $plan->id)->delete();
            MpsItem::where('mps_plan_id', $plan->id)->withTrashed()->forceDelete();
            $plan->update(['status' => 'draft', 'notes' => 'Demo flow seeder — langkah 1 draft snapshot OH terkunci']);
        } else {
            $plan = MpsPlan::create(['produk_id' => $fg->id, 'work_center_id' => $wc->id, 'month_year' => $monthYear, 'status' => 'draft', 'notes' => 'Demo flow seeder — langkah 1 draft snapshot OH terkunci', 'created_by' => $ppic->id]);
        }
        $plan->snapshotBeginningStock();

        $demoDays = min(12, $daysInMonth);
        foreach (range(1, $demoDays) as $d) {
            $date = $monthYear.'-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $isCleaning = $d === 7;
            foreach ($lines->take(2) as $line) {
                foreach (['shift1', 'shift2'] as $shift) {
                    if ($isCleaning) {
                        MpsItem::create([
                            'mps_plan_id' => $plan->id,
                            'tanggal' => $date,
                            'line_id' => $line->id,
                            'work_center_id' => $line->default_work_center_id ?? $wc->id,
                            'shift' => $shift,
                            'mp_count' => 0,
                            'target_qty' => 0,
                            'cleaning' => true,
                            'gap_reason' => 'cleaning',
                            'notes' => 'Full-day cleaning',
                        ]);

                        continue;
                    }
                    $mp = $shift === 'shift1' ? 4 : 3;
                    $item = MpsItem::create([
                        'mps_plan_id' => $plan->id,
                        'tanggal' => $date,
                        'line_id' => $line->id,
                        'work_center_id' => $line->default_work_center_id ?? $wc->id,
                        'shift' => $shift,
                        'mp_count' => $mp,
                        'cleaning' => false,
                        'notes' => $d === 3 && $shift === 'shift1' ? 'Kapasitas terbatas' : null,
                    ]);
                    $item->recalculateTarget();
                    if ($d === 3 && $shift === 'shift1') {
                        $item->update(['adjusted_qty' => (int) ($item->target_qty * 0.7), 'gap_reason' => 'capacity_limit']);
                    }
                    if ($d === 10 && $shift === 'shift2') {
                        $item->update(['adjusted_qty' => $item->target_qty + 500, 'gap_reason' => 'manual']);
                    }
                }
            }
        }

        $plan->refresh();
        $totalTarget = (int) $plan->items()->where('cleaning', false)->get()->sum(fn ($i) => $i->adjusted_qty ?? $i->target_qty);
        if ($totalTarget <= 0) {
            $this->command->warn('MPS grid total 0 — seeder tidak generate target');

            return;
        }

        $plan->update(['status' => 'approved']);

        $gen = app(MpsWeeklyPlanGenerator::class)->generate($plan);
        $plan->update(['status' => 'active']);

        $mrp = app(MrpCalculationService::class)->calculate($plan);

        $this->command->info("MPS Plan {$plan->id} {$fg->kode_produk} {$monthYear} — snapshot OH {$plan->beginning_stock_snapshot} — items {$plan->items()->count()} — total ".number_format($totalTarget));
        $this->command->info('Weekly generated: '.json_encode($gen));
        $this->command->info('MRP: '.json_encode($mrp).' — available=stock OH+incoming(0) shortage=max(0,need+ROP-available) critical=shortage>0&&available<ROP');
        $this->command->info('Buka: /ppic/mps/'.$plan->id.' (Grid) → /ppic/mrp?mps_plan_id='.$plan->id.' (Critical/Warning/OK) → Detail → Recalculate → Export Excel');
    }
}
