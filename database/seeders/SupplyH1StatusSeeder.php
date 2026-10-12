<?php

namespace Database\Seeders;

use App\Models\MaterialSupply;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SupplyH1StatusSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = User::role('warehouse_admin')->first() ?? User::first();
        $wc = WorkCenter::first();
        $wp = WeeklyPlan::whereIn('status', ['draft', 'aktif'])->first() ?? WeeklyPlan::first();
        $rms = Produk::whereIn('item_type', ['rm', 'pm'])->limit(2)->get();
        if ($rms->count() < 2) {
            $rms = Produk::limit(2)->get();
        }
        if ($rms->isEmpty() || ! $wp || ! $wc) {
            $this->command->warn('SupplyH1StatusSeeder: missing prereq (produk/wp/wc)');

            return;
        }

        MaterialSupply::where('notes', 'like', 'demo-status-%')->each(function ($s) {
            $s->items()->delete();
            $s->delete();
        });

        $now = Carbon::now();

        $cases = [
            [
                'status' => 'pending',
                'required_date' => Carbon::tomorrow()->toDateString(),
                'delivered_at' => null,
                'delivered_by' => null,
                'note' => 'demo-status-pending',
                'items' => [[$rms[0]->id, 100, 0], [$rms[1]->id ?? $rms[0]->id, 50, 0]],
            ],
            [
                'status' => 'delivered',
                'required_date' => Carbon::yesterday()->toDateString(),
                'delivered_at' => Carbon::yesterday()->setTime(14, 0),
                'delivered_by' => $warehouse->id,
                'note' => 'demo-status-delivered',
                'items' => [[$rms[0]->id, 100, 100], [$rms[1]->id ?? $rms[0]->id, 50, 50]],
            ],
            [
                'status' => 'partial',
                'required_date' => Carbon::tomorrow()->toDateString(),
                'delivered_at' => $now,
                'delivered_by' => $warehouse->id,
                'note' => 'demo-status-partial',
                'items' => [[$rms[0]->id, 100, 100], [$rms[1]->id ?? $rms[0]->id, 50, 10]],
            ],
            [
                'status' => 'late',
                'required_date' => $now->copy()->subDays(3)->toDateString(),
                'delivered_at' => $now,
                'delivered_by' => $warehouse->id,
                'note' => 'demo-status-late',
                'items' => [[$rms[0]->id, 80, 80], [$rms[1]->id ?? $rms[0]->id, 40, 40]],
            ],
        ];

        foreach ($cases as $c) {
            $s = MaterialSupply::create([
                'supply_number' => MaterialSupply::nextNumber(),
                'weekly_plan_id' => $wp->id,
                'work_center_id' => $wc->id,
                'required_date' => $c['required_date'],
                'status' => $c['status'],
                'delivered_at' => $c['delivered_at'],
                'delivered_by' => $c['delivered_by'],
                'created_by' => $warehouse->id,
                'notes' => $c['note'],
            ]);
            foreach ($c['items'] as [$pid, $req, $del]) {
                $s->items()->create(['produk_id' => $pid, 'required_qty' => $req, 'delivered_qty' => $del]);
            }
        }

        $this->command->info('Supply H-1 status demo: pending/delivered/partial/late seeded. H-1 = required -1 day.');
    }
}
