<?php

namespace App\Exports;

use App\Models\Line;
use App\Models\MpsPlan;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MpsPlanExport implements FromArray, WithEvents
{
    use Exportable;

    public function __construct(private MpsPlan $plan) {}

    public function array(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $plan = $this->plan->load(['produk', 'workCenter', 'items.line', 'items.workCenter']);
                $items = $plan->items;
                $daysInMonth = Carbon::parse($plan->month_year.'-01')->daysInMonth;
                $monthLabel = Carbon::parse($plan->month_year.'-01')->translatedFormat('F Y');
                $wc = $plan->workCenter;
                $regCap = $wc && $wc->standard_ct_seconds > 0 ? (int) round(60 / $wc->standard_ct_seconds * 60 * $wc->shift_hours) : 0;
                $satCap = $wc && $wc->standard_ct_seconds > 0 ? (int) round(60 / $wc->standard_ct_seconds * 60 * $wc->saturday_shift_hours) : 0;

                $map = [];
                foreach ($items as $it) {
                    $d = substr((string) $it->tanggal, 0, 10);
                    $key = $d.'-'.$it->line_id.'-'.$it->shift;
                    $map[$key] = $it;
                }

                $lines = Line::aktif()->orderBy('kode_line')->get();
                if ($lines->isEmpty()) {
                    $lines = collect([(object) ['id' => 0, 'kode_line' => '-', 'nama_line' => '-']]);
                }

                $title = 'MPS - '.$plan->produk->nama_produk.' ('.$plan->produk->kode_produk.') - '.$wc->name.' ('.$wc->code.') - '.$plan->month_year;
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells('A1:'.Coordinate::stringFromColumnIndex(3 + $daysInMonth + 7).'1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A2', 'Bulan: '.$monthLabel.' | Status: '.$plan->status.' | Stock OH (GUDANG-UTAMA): '.number_format($plan->beginning_stock_oh, 0, ',', '.').' | Kapasitas Reguler/shift: '.number_format($regCap, 0, ',', '.').' pcs | Sabtu: '.number_format($satCap, 0, ',', '.').' pcs | CT: '.$wc->standard_ct_seconds.'s | MP Std: '.$wc->fit_mp.' | Jam Shift: '.$wc->shift_hours.' | Sabtu: '.$wc->saturday_shift_hours);
                $sheet->mergeCells('A2:'.Coordinate::stringFromColumnIndex(3 + $daysInMonth + 7).'2');
                $sheet->getStyle('A2')->getFont()->setSize(8)->setItalic(true);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A3', 'SCD-A Layout — per Line x Shift (S1=Pagi, S2=Siang) — Cleaning = full-day block — GAP = Adjusted - Target');
                $sheet->mergeCells('A3:'.Coordinate::stringFromColumnIndex(3 + $daysInMonth + 7).'3');
                $sheet->getStyle('A3')->getFont()->setSize(7)->setColor(new Color('FF6B7280'));
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $headers = ['No', 'LINE', 'Shift'];
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $headers[] = (string) $d;
                }
                $headers = array_merge($headers, ['W1', 'W2', 'W3', 'W4', 'W5', 'TOTAL', 'ADJ']);

                $colCount = count($headers);
                $headerRow = 5;
                foreach ($headers as $idx => $h) {
                    $col = Coordinate::stringFromColumnIndex($idx + 1);
                    $sheet->setCellValue($col.$headerRow, $h);
                }
                $sheet->getStyle('A'.$headerRow.':'.Coordinate::stringFromColumnIndex($colCount).$headerRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 7, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(18);

                $dayNameRow = 6;
                foreach (range(1, $daysInMonth) as $d) {
                    $col = Coordinate::stringFromColumnIndex(3 + $d);
                    $dayName = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][Carbon::parse($plan->month_year.'-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT))->dayOfWeek];
                    $sheet->setCellValue($col.$dayNameRow, $dayName);
                }
                $sheet->getStyle('D'.$dayNameRow.':'.Coordinate::stringFromColumnIndex(3 + $daysInMonth).$dayNameRow)->applyFromArray([
                    'font' => ['size' => 6, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $row = 7;
                $no = 1;
                $grandPlan = 0;
                $grandAdj = 0;
                foreach ($lines as $line) {
                    foreach (['shift1', 'shift2'] as $shift) {
                        $sheet->setCellValue('A'.$row, $no);
                        $sheet->setCellValue('B'.$row, $line->nama_line ?? $line->kode_line ?? '-');
                        $sheet->setCellValue('C'.$row, $shift === 'shift1' ? 'S1' : 'S2');
                        $sheet->getStyle('A'.$row.':C'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setSize(7);

                        $weekly = [0, 0, 0, 0, 0];
                        $rowPlan = 0;
                        $rowAdj = 0;
                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $col = Coordinate::stringFromColumnIndex(3 + $d);
                            $dateStr = $plan->month_year.'-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                            $key = $dateStr.'-'.$line->id.'-'.$shift;
                            $it = $map[$key] ?? null;
                            if ($it) {
                                if ($it->cleaning) {
                                    $sheet->setCellValue($col.$row, 'CLEANING');
                                    $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                                    $sheet->getStyle($col.$row)->getFont()->setSize(6)->setBold(true)->getColor()->setRGB('92400E');
                                } else {
                                    $val = $it->target_qty;
                                    $sheet->setCellValue($col.$row, $val);
                                    $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode('#,##0');
                                    $sheet->getStyle($col.$row)->getFont()->setSize(7);
                                    $rowPlan += $val;
                                    $adj = $it->adjusted_qty;
                                    if ($adj !== null) {
                                        $rowAdj += $adj;
                                    } else {
                                        $rowAdj += $val;
                                    }
                                    $wIdx = $d <= 7 ? 0 : ($d <= 14 ? 1 : ($d <= 21 ? 2 : ($d <= 28 ? 3 : 4)));
                                    $weekly[$wIdx] += $val;
                                }
                            } else {
                                $sheet->setCellValue($col.$row, '-');
                                $sheet->getStyle($col.$row)->getFont()->setSize(7)->getColor()->setRGB('9CA3AF');
                                $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            }
                            $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $sheet->getStyle($col.$row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                        }

                        $baseCol = 3 + $daysInMonth;
                        foreach ($weekly as $wIdx => $wVal) {
                            $col = Coordinate::stringFromColumnIndex($baseCol + 1 + $wIdx);
                            $sheet->setCellValue($col.$row, $wVal > 0 ? $wVal : '-');
                            $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode('#,##0');
                            $sheet->getStyle($col.$row)->getFont()->setSize(7)->setBold(true);
                            $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
                        }
                        $colTotal = Coordinate::stringFromColumnIndex($baseCol + 6);
                        $colAdj = Coordinate::stringFromColumnIndex($baseCol + 7);
                        $sheet->setCellValue($colTotal.$row, $rowPlan > 0 ? $rowPlan : '-');
                        $sheet->setCellValue($colAdj.$row, $rowAdj > 0 ? $rowAdj : '-');
                        foreach ([$colTotal, $colAdj] as $c) {
                            $sheet->getStyle($c.$row)->getNumberFormat()->setFormatCode('#,##0');
                            $sheet->getStyle($c.$row)->getFont()->setSize(7)->setBold(true);
                            $sheet->getStyle($c.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $sheet->getStyle($c.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
                        }

                        $sheet->getRowDimension($row)->setRowHeight(14);
                        $grandPlan += $rowPlan;
                        $grandAdj += $rowAdj;
                        $row++;
                        $no++;
                    }
                }

                $sheet->setCellValue('A'.$row, 'TOTAL');
                $sheet->mergeCells('A'.$row.':C'.$row);
                $sheet->getStyle('A'.$row.':C'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true)->setSize(7);
                $sheet->getStyle('A'.$row.':C'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');
                $sheet->getStyle('A'.$row.':C'.$row)->getFont()->getColor()->setRGB('FFFFFF');

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $col = Coordinate::stringFromColumnIndex(3 + $d);
                    $sum = 0;
                    foreach ($lines as $line) {
                        foreach (['shift1', 'shift2'] as $shift) {
                            $dateStr = $plan->month_year.'-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                            $it = $map[$dateStr.'-'.$line->id.'-'.$shift] ?? null;
                            if ($it && ! $it->cleaning) {
                                $sum += $it->target_qty;
                            }
                        }
                    }
                    $sheet->setCellValue($col.$row, $sum > 0 ? $sum : '-');
                    $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle($col.$row)->getFont()->setBold(true)->setSize(7);
                    $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
                }
                $baseCol = 3 + $daysInMonth;
                for ($w = 0; $w < 5; $w++) {
                    $col = Coordinate::stringFromColumnIndex($baseCol + 1 + $w);
                    $start = $w * 7 + 1;
                    $end = $w === 4 ? $daysInMonth : ($w + 1) * 7;
                    $sum = 0;
                    for ($d = $start; $d <= $end; $d++) {
                        $colD = Coordinate::stringFromColumnIndex(3 + $d);
                        $v = $sheet->getCell($colD.$row)->getValue();
                        if (is_numeric($v)) {
                            $sum += (int) $v;
                        }
                    }
                    $sheet->setCellValue($col.$row, $sum > 0 ? $sum : '-');
                    $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle($col.$row)->getFont()->setBold(true)->setSize(7);
                    $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1D5DB');
                }
                $colTotal = Coordinate::stringFromColumnIndex($baseCol + 6);
                $colAdj = Coordinate::stringFromColumnIndex($baseCol + 7);
                $sheet->setCellValue($colTotal.$row, $grandPlan > 0 ? $grandPlan : '-');
                $sheet->setCellValue($colAdj.$row, $grandAdj > 0 ? $grandAdj : '-');
                foreach ([$colTotal, $colAdj] as $c) {
                    $sheet->getStyle($c.$row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle($c.$row)->getFont()->setBold(true)->setSize(7);
                    $sheet->getStyle($c.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($c.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C7D2FE');
                }
                $sheet->getRowDimension($row)->setRowHeight(16);

                $gap = $grandAdj - $grandPlan;
                $row++;
                $sheet->setCellValue('A'.$row, 'GAP (Adj - Plan): '.number_format($gap, 0, ',', '.').' | W1=1-7 W2=8-14 W3=15-21 W4=22-28 W5=29-'.$daysInMonth);
                $sheet->mergeCells('A'.$row.':'.Coordinate::stringFromColumnIndex($colCount).$row);
                $sheet->getStyle('A'.$row)->getFont()->setSize(7)->setItalic(true);
                $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $lastCol = Coordinate::stringFromColumnIndex($colCount);
                $sheet->getStyle('A'.$headerRow.':'.$lastCol.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('FFD1D5DB'));

                $sheet->freezePane('D7');
                $sheet->setAutoFilter('A'.$headerRow.':'.$lastCol.$headerRow);
                foreach (range(1, $colCount) as $cIdx) {
                    $colStr = Coordinate::stringFromColumnIndex($cIdx);
                    $width = $cIdx <= 3 ? 10 : 8;
                    if ($cIdx === 2) {
                        $width = 14;
                    }
                    $sheet->getColumnDimension($colStr)->setWidth($width);
                }
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getStyle('A1:'.$lastCol.$row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getSheetView()->setZoomScale(85);
                $sheet->setTitle(substr('MPS '.$plan->month_year, 0, 31));
            },
        ];
    }
}
