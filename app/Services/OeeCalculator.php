<?php

namespace App\Services;

class OeeCalculator
{
    /**
     * Calculate OEE components and Theoretical Target according to industrial standards.
     *
     * @param float $grossTimeMenit Total gross work duration in minutes
     * @param float $totalDowntimeMenit Total downtime / pause duration in minutes
     * @param int $outputFisik Total good physical output produced
     * @param int $totalReject Total reject / scrap items
     * @param float $ctSeconds Effective cycle time in SECONDS per piece
     * @param float|null $manualQuality Optional manual quality percentage override (0-100)
     * @return array{
     *     waktu_bersih_menit: float,
     *     target_teoritis: int,
     *     availability_persen: float,
     *     performance_persen: float,
     *     quality_persen: float,
     *     oee_persen: float
     * }
     */
    public static function calculate(
        float $grossTimeMenit,
        float $totalDowntimeMenit,
        int $outputFisik,
        int $totalReject,
        float $ctSeconds,
        ?float $manualQuality = null
    ): array {
        $grossTime = max(0.0, $grossTimeMenit);
        $downtime = min($grossTime, max(0.0, $totalDowntimeMenit));
        $waktuBersih = max(0.0, $grossTime - $downtime);

        // 1. Availability (%) = Operating Time / Gross Operating Time
        $availability = $grossTime > 0.0
            ? min(100.0, max(0.0, ($waktuBersih / $grossTime) * 100.0))
            : 0.0;

        // 2. Performance (%) = Ideal Operating Time (min) / Net Operating Time (min)
        // Ideal Time (min) = (Output * CT in seconds) / 60 seconds
        $performance = 0.0;
        if ($waktuBersih > 0.0 && $ctSeconds > 0.0) {
            $idealTimeMenit = ($outputFisik * $ctSeconds) / 60.0;
            $performance = min(100.0, max(0.0, ($idealTimeMenit / $waktuBersih) * 100.0));
        }

        // 3. Quality (%) = Good Output / Total Output (or manual override)
        if ($manualQuality !== null) {
            $quality = min(100.0, max(0.0, (float) $manualQuality));
        } else {
            $totalOutput = $outputFisik + max(0, $totalReject);
            $quality = $totalOutput > 0
                ? min(100.0, max(0.0, ($outputFisik / $totalOutput) * 100.0))
                : 0.0;
        }

        // 4. OEE (%) = Availability * Performance * Quality
        $oee = min(100.0, max(0.0, ($availability / 100.0) * ($performance / 100.0) * ($quality / 100.0) * 100.0));

        // 5. Target Teoritis = (Waktu Bersih in seconds) / CT in seconds
        $targetTeoritis = 0;
        if ($ctSeconds > 0.0 && $waktuBersih > 0.0) {
            $targetTeoritis = (int) round(($waktuBersih * 60.0) / $ctSeconds);
        }

        return [
            'waktu_bersih_menit' => round($waktuBersih, 2),
            'target_teoritis' => $targetTeoritis,
            'availability_persen' => round($availability, 2),
            'performance_persen' => round($performance, 2),
            'quality_persen' => round($quality, 2),
            'oee_persen' => round($oee, 2),
        ];
    }
}
