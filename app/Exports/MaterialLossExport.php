<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaterialLossExport implements FromArray, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private array $rows,
        private array $summary,
    ) {}

    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['usage_number'],
            $r['usage_date'],
            $r['batch_number'],
            $r['material_name'],
            $r['standard'],
            $r['used'],
            $r['variance'],
            $r['ratio'],
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['No. Usage', 'Tanggal', 'Batch', 'Material', 'Standard', 'Actual', 'Variance', 'Ratio %'];
    }

    public function map($row): array
    {
        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
