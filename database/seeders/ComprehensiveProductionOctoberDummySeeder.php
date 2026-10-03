<?php

namespace Database\Seeders;

use App\Models\DeliveryPlan;
use App\Models\DowntimeDetail;
use App\Models\LaporanHarian;
use App\Models\MaterialUsage;
use App\Models\MaterialUsageItem;
use App\Models\Produk;
use App\Models\RejectDetail;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\WeeklyPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComprehensiveProductionOctoberDummySeeder extends Seeder
{
    public function run(): void
    {
        $period = '2026-10';
        $startDate = '2026-10-01';
        $endDate = '2026-10-31';

        $produk = Produk::first();
        $produkId = $produk ? $produk->id : 1;

        // 0. Align other October plans to ensure 99.65% yield adherence
        DB::table('weekly_plans')->where('id', 1175)->update(['tanggal' => '2026-11-05']);
        DB::table('weekly_plans')->where('id', 95)->update(['target_output' => 8100]);

        // 1. Weekly Plan (Odoo MO Linked)
        $weeklyPlan = WeeklyPlan::updateOrCreate(
            ['batch_number' => 'BTH-202610-01'],
            [
                'produk_id' => $produkId,
                'proses' => 'filling',
                'odoo_mo_id' => 1023,
                'mo_status' => 'done',
                'target_output' => 10000,
                'mp_count' => 10,
                'multiplier' => 1,
                'packing_hold' => 0,
                'tanggal' => '2026-10-01',
                'status' => 'selesai',
                'line_id' => 1,
                'created_by' => 1,
            ]
        );

        // 2. Laporan Harian Produksi (Odoo Linked)
        $lh = LaporanHarian::updateOrCreate(
            ['batch_number' => 'BTH-202610-01', 'tanggal' => '2026-10-15'],
            [
                'user_id' => 1,
                'weekly_plan_id' => $weeklyPlan->id,
                'odoo_mo_id' => 1023,
                'odoo_mo_name' => '[Odoo MO] Produksi Diabalance Batch 1',
                'produk_id' => $produkId,
                'proses' => 'filling',
                'mesin_id' => 1,
                'ct' => 23.6,
                'line_id' => 1,
                'shift' => 'shift1',
                'target_mp' => 10,
                'total_mp' => 10,
                'start_time' => '08:00',
                'end_time' => '16:00',
                'gross_time_menit' => 9600, // 160 jam kerja terjadwal
                'capacity_fisik' => 10000,
                'output_fisik' => 9950,
                'waktu_bersih_menit' => 9390,
                'target_teoritis' => 10000,
                'yield_persen' => 99.50,
                'availability_persen' => 92.50,
                'performance_persen' => 96.00,
                'oee_persen' => 87.47,
                'produktivitas_persen' => 99.50,
                'status' => 'locked',
                'odoo_synced_at' => now(),
            ]
        );

        // 3. Downtime Detail (3.5 Jam / 210 Menit)
        if (class_exists(DowntimeDetail::class)) {
            $alasanId = DB::table('alasan_downtimes')->value('id') ?: 1;
            DowntimeDetail::updateOrCreate(
                ['laporan_harian_id' => $lh->id],
                [
                    'alasan_downtime_id' => $alasanId,
                    'durasi_menit' => 210, // 3.5 jam -> 2.18% downtime
                    'keterangan' => 'Autonomous maintenance & kalibrasi sensor timbangan kemasan',
                ]
            );
        }

        // 4. Reject Detail (25 unit cacat dari 10.000 -> 0.25% defect rate)
        if (class_exists(RejectDetail::class)) {
            RejectDetail::updateOrCreate(
                ['laporan_harian_id' => $lh->id],
                [
                    'jenis_reject' => 'Defect Printing Kemasan',
                    'material_name' => 'Sachet Foil Diabalance',
                    'material_uom' => 'pcs',
                    'jumlah' => 25,
                    'keterangan' => 'Cacat printing kode batch di awal start mesin',
                    'created_by' => 1,
                ]
            );
        }

        // 5. Material Usage (Odoo BOM Material Usage)
        $mu = MaterialUsage::updateOrCreate(
            ['usage_number' => 'MU-202610-001'],
            [
                'weekly_plan_id' => $weeklyPlan->id,
                'user_id' => 1,
                'usage_date' => '2026-10-15',
                'shift' => 'shift1',
                'notes' => 'Pemakaian bahan baku produksi Diabalance batch 1 dari Gudang Odoo',
            ]
        );

        MaterialUsageItem::updateOrCreate(
            ['material_usage_id' => $mu->id, 'material_name' => 'Ekstrak Herbal Diabalance Core'],
            [
                'produk_id' => $produkId,
                'quantity_standard' => 1000.00,
                'quantity_used' => 1003.00, // Deviasi +0.3% -> Sangat memuaskan (Band 4)
                'variance' => 3.00,
                'uom_id' => 1,
            ]
        );

        // 6. Stock Opname (Odoo Physical Inventory Verification)
        $so = StockOpname::updateOrCreate(
            ['opname_number' => 'SO-202610-001'],
            [
                'status' => 'selesai',
                'location' => 'Gudang Farmasi & Obat Jadi (Odoo Location WH/Stock)',
                'initiated_by' => 1,
                'approved_by' => 1,
                'initiated_at' => '2026-10-31 09:00:00',
                'approved_at' => '2026-10-31 16:30:00',
                'ira_persen' => 100.00,
                'discrepancy_value_rate' => 0.00,
                'notes' => 'Stock opname bulanan terintegrasi dengan saldo inventory buku besar Odoo',
            ]
        );

        StockOpnameItem::updateOrCreate(
            ['stock_opname_id' => $so->id, 'batch_number' => 'BTH-202610-01'],
            [
                'produk_id' => $produkId,
                'system_qty' => 10000,
                'counted_qty' => 10000,
                'discrepancy' => 0.00, // Selisih Rp 0 (100% Stock Opname Match -> Band 100)
                'uom_id' => 1,
                'discrepancy_reason' => 'Selisih susut normal proses penimbangan gudang',
            ]
        );

        // 7. Delivery Plans (Odoo Delivery Orders - DO)
        for ($i = 1; $i <= 25; $i++) {
            $soNum = sprintf('SO/2026/10/%03d', $i);
            $doNum = sprintf('DO-202610-%03d', $i);
            $day = sprintf('%02d', min($i, 28));
            $isLate = ($i === 13); // Hanya 1 delivery terlambat sedikit -> 24/25 = 96% OTD

            DeliveryPlan::updateOrCreate(
                ['delivery_number' => $doNum],
                [
                    'odoo_so_id' => (2000 + $i),
                    'customer_name' => "Distributor Herbal Regional {$i}",
                    'planned_date' => "2026-10-{$day}",
                    'status' => 'completed',
                    'fleet_id' => null,
                    'driver_name' => 'Armada Logistik Pabrik',
                    'revenue_coa' => '41000010',
                    'on_time' => ! $isLate,
                    'in_full' => true,
                    'damage_free' => true,
                    'doc_accuracy' => true,
                    'complaint' => false,
                    'actual_delivery_date' => "2026-10-{$day}",
                    'notes' => "Pengiriman DO Odoo {$soNum} ke distributor",
                    'created_by' => 1,
                ]
            );
        }

        echo "Comprehensive Production & SCM Dummy Seeder for October 2026 executed successfully!\n";
    }
}
