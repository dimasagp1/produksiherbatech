<?php

namespace App\Services;

use App\Models\WorkCenter;

class TargetCalculationService
{
    /**
     * Calculate target output using Work Center CT-based formula.
     * Formula: (60 / CT_seconds) * 60 * shift_hours * mp_count
     *
     * @param WorkCenter $workCenter
     * @param int $mpCount
     * @return int
     */
    public static function calculateTarget(WorkCenter $workCenter, int $mpCount): int
    {
        if ($mpCount <= 0) {
            return 0;
        }

        $ctSeconds = $workCenter->standard_ct_seconds;
        if ($ctSeconds <= 0) {
            return 0;
        }

        $outputPerMinute = 60 / $ctSeconds;
        $minutesPerShift = $workCenter->shift_hours * 60;
        $targetPerPerson = $outputPerMinute * $minutesPerShift;

        return (int) round($targetPerPerson * $mpCount);
    }

    /**
     * Calculate target output per person (for reference/display).
     *
     * @param WorkCenter $workCenter
     * @return float
     */
    public static function calculateTargetPerPerson(WorkCenter $workCenter): float
    {
        $ctSeconds = $workCenter->standard_ct_seconds;
        if ($ctSeconds <= 0) {
            return 0;
        }

        $outputPerMinute = 60 / $ctSeconds;
        $minutesPerShift = $workCenter->shift_hours * 60;

        return round($outputPerMinute * $minutesPerShift, 2);
    }

    /**
     * Calculate MP count needed for a given target output.
     *
     * @param WorkCenter $workCenter
     * @param int $targetOutput
     * @return int
     */
    public static function calculateRequiredMp(WorkCenter $workCenter, int $targetOutput): int
    {
        if ($targetOutput <= 0) {
            return 0;
        }

        $targetPerPerson = self::calculateTargetPerPerson($workCenter);
        if ($targetPerPerson <= 0) {
            return 0;
        }

        return (int) ceil($targetOutput / $targetPerPerson);
    }

    /**
     * Get Work Center capacity info for display.
     *
     * @param WorkCenter $workCenter
     * @return array
     */
    public static function getCapacityInfo(WorkCenter $workCenter): array
    {
        $ctSeconds = $workCenter->standard_ct_seconds;
        $shiftHours = $workCenter->shift_hours;
        $fitMp = $workCenter->fit_mp;

        return [
            'ct_seconds' => $ctSeconds,
            'output_per_minute' => $ctSeconds > 0 ? round(60 / $ctSeconds, 4) : 0,
            'shift_hours' => $shiftHours,
            'shift_minutes' => $shiftHours * 60,
            'fit_mp' => $fitMp,
            'target_per_person_per_shift' => self::calculateTargetPerPerson($workCenter),
            'target_total_per_shift' => $fitMp > 0 ? self::calculateTarget($workCenter, $fitMp) : 0,
        ];
    }
};