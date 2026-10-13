<?php

namespace Tests\Unit;

use App\Models\WorkCenter;
use App\Services\TargetCalculationService;
use Tests\TestCase;

class TargetCalculationServiceTest extends TestCase
{
    public function test_calculates_target_accurately_based_on_ct_and_fit_mp(): void
    {
        // WorkCenter: 3 seconds/pcs -> 20 pcs/min. Shift: 6.5 hours = 390 min.
        // Total Line Target = 20 * 390 = 7800 pcs.
        // Fit MP = 4 -> Target per person = 7800 / 4 = 1950 pcs.
        $wc = new WorkCenter([
            'standard_ct_seconds' => 3,
            'shift_hours' => 6.5,
            'fit_mp' => 4,
        ]);

        $this->assertEquals(1950.0, TargetCalculationService::calculateTargetPerPerson($wc));

        // When standard fit MP (4 people) is staffed:
        $this->assertEquals(7800, TargetCalculationService::calculateTarget($wc, 4));

        // When understaffed (2 people):
        $this->assertEquals(3900, TargetCalculationService::calculateTarget($wc, 2));

        // When overstaffed (6 people):
        $this->assertEquals(11700, TargetCalculationService::calculateTarget($wc, 6));

        // Required MP calculation
        $this->assertEquals(4, TargetCalculationService::calculateRequiredMp($wc, 7800));
        $this->assertEquals(2, TargetCalculationService::calculateRequiredMp($wc, 3900));
    }

    public function test_returns_zero_when_mp_count_or_ct_is_invalid(): void
    {
        $wc = new WorkCenter([
            'standard_ct_seconds' => 0,
            'shift_hours' => 6.5,
            'fit_mp' => 4,
        ]);

        $this->assertEquals(0, TargetCalculationService::calculateTarget($wc, 4));
        $this->assertEquals(0, TargetCalculationService::calculateTarget($wc, 0));
    }
}
