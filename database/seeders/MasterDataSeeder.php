<?php

namespace Database\Seeders;

use App\Models\AlasanDowntime;
use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // --- Produk (Fase 1 MVP) ---
        $produks = [
            ['kode_produk' => 'DB01', 'nama_produk' => 'Diabalance', 'proses_default' => 'filling'],
            ['kode_produk' => 'VB01', 'nama_produk' => 'Vitablend', 'proses_default' => 'mixing'],
            ['kode_produk' => 'EV01', 'nama_produk' => 'Eyovit', 'proses_default' => 'packing'],
            ['kode_produk' => 'HM01', 'nama_produk' => 'HerbaMix', 'proses_default' => 'mixing'],
            ['kode_produk' => 'FT01', 'nama_produk' => 'FitTea', 'proses_default' => 'filling'],
        ];
        foreach ($produks as $p) {
            Produk::updateOrCreate(['kode_produk' => $p['kode_produk']], $p);
        }

        // --- Mesin (Nama + CT) --- sesuai mockup LinePulse
        $mesins = [
            ['nama_mesin' => 'Sachet Tiger', 'ct' => 23.6],
            ['nama_mesin' => 'Sachet Kilo', 'ct' => 19.4],
            ['nama_mesin' => 'Mixer Alpha', 'ct' => 31.2],
            ['nama_mesin' => 'Mixer Beta', 'ct' => 28.5],
            ['nama_mesin' => 'Packer Nova', 'ct' => 27.8],
            ['nama_mesin' => 'Packer Rho', 'ct' => 25.0],
        ];
        foreach ($mesins as $m) {
            Mesin::updateOrCreate(['nama_mesin' => $m['nama_mesin']], $m);
        }

        // --- Line ---
        $lines = [
            ['kode_line' => 'LN-A', 'nama_line' => 'Line A'],
            ['kode_line' => 'LN-B', 'nama_line' => 'Line B'],
            ['kode_line' => 'LN-C', 'nama_line' => 'Line C'],
        ];
        foreach ($lines as $l) {
            Line::updateOrCreate(['kode_line' => $l['kode_line']], $l);
        }

        // --- Alasan Downtime (Manual vs Default/Hardcode) ---
        $reasons = [
            ['nama_alasan' => 'Istirahat', 'tipe_input' => 'default_hardcode', 'durasi_default_menit' => 60],
            ['nama_alasan' => 'Briefing', 'tipe_input' => 'default_hardcode', 'durasi_default_menit' => 15],
            ['nama_alasan' => 'Line Clearance', 'tipe_input' => 'default_hardcode', 'durasi_default_menit' => 15],
            ['nama_alasan' => 'Setup / Changeover', 'tipe_input' => 'manual', 'durasi_default_menit' => null],
            ['nama_alasan' => 'Mesin Rusak', 'tipe_input' => 'manual', 'durasi_default_menit' => null],
            ['nama_alasan' => 'Keterlambatan Supply', 'tipe_input' => 'manual', 'durasi_default_menit' => null],
            ['nama_alasan' => 'Keterlambatan Material', 'tipe_input' => 'manual', 'durasi_default_menit' => null],
        ];
        foreach ($reasons as $r) {
            AlasanDowntime::updateOrCreate(['nama_alasan' => $r['nama_alasan']], $r);
        }
    }
}
