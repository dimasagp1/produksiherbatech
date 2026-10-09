<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MrpExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(private $rows) {}

    public function array(): array
    {
        return $this->rows->map(fn ($r) => [$r->mpsPlan?->produk?->kode_produk, $r->mpsPlan?->month_year, $r->material_name, $r->needed_qty, $r->beginning_stock, $r->available_qty, $r->rop, $r->shortage_qty, $r->status])->toArray();
    }

    public function headings(): array
    {
        return ['Produk', 'Bulan', 'Material', 'Need', 'Stock OH', 'Available', 'ROP', 'Shortage', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
