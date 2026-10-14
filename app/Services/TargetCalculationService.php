<?php

namespace App\Services;

use App\Models\WorkCenter;

class TargetCalculationService
{
    /**
     * Calculate target output using Work Center CT-based formula.
     * Formula:
     *   Output/min = 60 / standard_ct_seconds
     *   Effective minutes = (customHours ?? shift_hours) * 60 (or config('production.shift_minutes'))
     *   Target/person = (Output/min * Effective minutes) / fit_mp
     *   Target output = floor(Target/person * mp_count)
     *
     * Example:
     *   Secondary Line: CT = 3s (20 pcs/min), 6.5 hours (390 mins), fit_mp = 6
     *   Target per person = (20 * 390) / 6 = 1,300 pcs
     *   For 6 MP => 1,300 * 6 = 7,800 pcs
     *
     * @param  float|null  $customHours  Optional custom hours (e.g. Saturday shift)
     */
    public static function calculateTarget(WorkCenter $workCenter, int $mpCount, ?float $customHours = null): int
    {
        if ($mpCount <= 0 || $workCenter->standard_ct_seconds <= 0) {
            return 0;
        }

        $targetPerPerson = self::calculateTargetPerPerson($workCenter, $customHours);
        if ($targetPerPerson <= 0) {
            return 0;
        }

        return (int) floor($targetPerPerson * $mpCount);
    }

    /**
     * Calculate target output per person.
     */
    public static function calculateTargetPerPerson(WorkCenter $workCenter, ?float $customHours = null): float
    {
        $ctSeconds = $workCenter->standard_ct_seconds;
        if ($ctSeconds <= 0) {
            return 0;
        }

        $outputPerMinute = 60 / $ctSeconds;
        $minutesPerShift = self::getEffectiveShiftMinutes($workCenter, $customHours);
        $lineTarget = $outputPerMinute * $minutesPerShift;
        $fitMp = $workCenter->fit_mp > 0 ? $workCenter->fit_mp : 1;

        return $lineTarget / $fitMp;
    }

    /**
     * Get effective minutes per shift.
     */
    public static function getEffectiveShiftMinutes(WorkCenter $workCenter, ?float $customHours = null): float
    {
        if ($customHours !== null && $customHours > 0) {
            return (float) ($customHours * 60);
        }

        $configMinutes = config('production.shift_minutes');
        if ($configMinutes !== null && is_numeric($configMinutes) && $configMinutes > 0) {
            return (float) $configMinutes;
        }

        return (float) ($workCenter->shift_hours * 60);
    }

    /**
     * Get max theoretical capacity for a Work Center line shift.
     */
    public static function getMaxLineCapacity(WorkCenter $workCenter, ?float $customHours = null): int
    {
        if ($workCenter->standard_ct_seconds <= 0) {
            return 0;
        }

        $outputPerMinute = 60 / $workCenter->standard_ct_seconds;
        $minutesPerShift = self::getEffectiveShiftMinutes($workCenter, $customHours);

        return (int) floor($outputPerMinute * $minutesPerShift);
    }

    /**
     * Calculate MP count needed for a given target output.
     */
    public static function calculateRequiredMp(WorkCenter $workCenter, int $targetOutput, ?float $customHours = null): int
    {
        if ($targetOutput <= 0) {
            return 0;
        }

        $targetPerPerson = self::calculateTargetPerPerson($workCenter, $customHours);
        if ($targetPerPerson <= 0) {
            return 0;
        }

        return (int) ceil($targetOutput / $targetPerPerson);
    }

    /**
     * Get Work Center capacity info for display.
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
            'shift_minutes' => self::getEffectiveShiftMinutes($workCenter),
            'fit_mp' => $fitMp,
            'target_per_person_per_shift' => round(self::calculateTargetPerPerson($workCenter), 2),
            'target_total_per_shift' => $fitMp > 0 ? self::calculateTarget($workCenter, $fitMp) : 0,
            'max_line_capacity' => self::getMaxLineCapacity($workCenter),
            'benchmark_pcs_per_mp' => (int) config('production.benchmark_pcs_per_mp', 2000),
        ];
    }
}
