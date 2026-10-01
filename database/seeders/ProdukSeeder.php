<?php

namespace Database\Seeders;

use App\Models\Produk;
use Illuminate\Database\Seeder;

class ProdukSeeder extends Seeder
{
    public function run(): void
    {
        $produks = [
            ['kode_produk' => 'DB01', 'nama_produk' => 'Diabalance', 'proses_default' => 'filling'],
            ['kode_produk' => 'DB02', 'nama_produk' => 'Diabalance Forte', 'proses_default' => 'filling'],
            ['kode_produk' => 'DB03', 'nama_produk' => 'Diabalance Lite', 'proses_default' => 'filling'],
            ['kode_produk' => 'VB01', 'nama_produk' => 'Vitablend', 'proses_default' => 'mixing'],
            ['kode_produk' => 'VB02', 'nama_produk' => 'Vitablend Plus', 'proses_default' => 'mixing'],
            ['kode_produk' => 'VB03', 'nama_produk' => 'Vitablend Kids', 'proses_default' => 'mixing'],
            ['kode_produk' => 'EV01', 'nama_produk' => 'Eyovit', 'proses_default' => 'packing'],
            ['kode_produk' => 'EV02', 'nama_produk' => 'Eyovit Gold', 'proses_default' => 'packing'],
            ['kode_produk' => 'EV03', 'nama_produk' => 'Eyovit Advance', 'proses_default' => 'packing'],
            ['kode_produk' => 'HM01', 'nama_produk' => 'HerbaMix', 'proses_default' => 'mixing'],
            ['kode_produk' => 'HM02', 'nama_produk' => 'HerbaMix Premium', 'proses_default' => 'mixing'],
            ['kode_produk' => 'FT01', 'nama_produk' => 'FitTea', 'proses_default' => 'filling'],
            ['kode_produk' => 'FT02', 'nama_produk' => 'FitTea Detox', 'proses_default' => 'filling'],
            ['kode_produk' => 'FT03', 'nama_produk' => 'FitTea Green', 'proses_default' => 'filling'],
            ['kode_produk' => 'CB01', 'nama_produk' => 'CurcumaBlend', 'proses_default' => 'mixing'],
            ['kode_produk' => 'CB02', 'nama_produk' => 'CurcumaBlend X', 'proses_default' => 'mixing'],
            ['kode_produk' => 'IM01', 'nama_produk' => 'ImunMax', 'proses_default' => 'packing'],
            ['kode_produk' => 'IM02', 'nama_produk' => 'ImunMax Plus', 'proses_default' => 'packing'],
            ['kode_produk' => 'SB01', 'nama_produk' => 'SlimBio', 'proses_default' => 'filling'],
            ['kode_produk' => 'SB02', 'nama_produk' => 'SlimBio Pro', 'proses_default' => 'filling'],
            ['kode_produk' => 'NK01', 'nama_produk' => 'NutriKit', 'proses_default' => 'mixing'],
            ['kode_produk' => 'NK02', 'nama_produk' => 'NutriKit Junior', 'proses_default' => 'mixing'],
            ['kode_produk' => 'OB01', 'nama_produk' => 'ObatHerbal Plus', 'proses_default' => 'packing'],
            ['kode_produk' => 'OB02', 'nama_produk' => 'ObatHerbal Max', 'proses_default' => 'packing'],
            ['kode_produk' => 'GR01', 'nama_produk' => 'GreenExtract', 'proses_default' => 'mixing'],
            ['kode_produk' => 'GR02', 'nama_produk' => 'GreenExtract Organic', 'proses_default' => 'mixing'],
            ['kode_produk' => 'CL01', 'nama_produk' => 'CleanVita', 'proses_default' => 'filling'],
            ['kode_produk' => 'CL02', 'nama_produk' => 'CleanVita Immune', 'proses_default' => 'filling'],
            ['kode_produk' => 'RX01', 'nama_produk' => 'RexPower', 'proses_default' => 'packing'],
            ['kode_produk' => 'RX02', 'nama_produk' => 'RexPower Extra', 'proses_default' => 'packing'],
        ];

        foreach ($produks as $p) {
            Produk::updateOrCreate(['kode_produk' => $p['kode_produk']], $p);
        }
    }
}
