<?php

namespace Tests\Unit;

use App\Services\OeeCalculator;
use Tests\TestCase;

class OeeCalculatorTest extends TestCase
{
    public function test_calculates_standard_oee_and_target_teoritis(): void
    {
        // Gross Time = 390 min (6.5h). Downtime = 90 min.
        // Waktu Bersih = 300 min.
        // CT = 3 seconds/pcs.
        // Good Output = 6000 pcs. Reject = 0.
        // Ideal Operating Time = (6000 * 3) / 60 = 300 min.
        $calc = OeeCalculator::calculate(
            grossTimeMenit: 390.0,
            totalDowntimeMenit: 90.0,
            outputFisik: 6000,
            totalReject: 0,
            ctSeconds: 3.0,
            manualQuality: null
        );

        $this->assertEquals(300.0, $calc['waktu_bersih_menit']);
        $this->assertEquals(76.92, $calc['availability_persen']); // 300 / 390 * 100
        $this->assertEquals(100.0, $calc['performance_persen']); // (300 / 300) * 100
        $this->assertEquals(100.0, $calc['quality_persen']);
        $this->assertEquals(76.92, $calc['oee_persen']);
        $this->assertEquals(6000, $calc['target_teoritis']); // (300 * 60) / 3
    }

    public function test_performance_drops_proportionally_when_speed_is_slow(): void
    {
        // In 300 min with CT = 3s (capacity 6000), only 3000 pcs produced -> Performance = 50%
        $calc = OeeCalculator::calculate(
            grossTimeMenit: 390.0,
            totalDowntimeMenit: 90.0,
            outputFisik: 3000,
            totalReject: 0,
            ctSeconds: 3.0
        );

        $this->assertEquals(50.0, $calc['performance_persen']);
        $this->assertEquals(38.46, $calc['oee_persen']); // 76.92% * 50% * 100%
    }

    public function test_manual_quality_override_recalculates_oee(): void
    {
        $calc = OeeCalculator::calculate(
            grossTimeMenit: 300.0,
            totalDowntimeMenit: 0.0,
            outputFisik: 6000,
            totalReject: 0,
            ctSeconds: 3.0,
            manualQuality: 90.0
        );

        $this->assertEquals(100.0, $calc['availability_persen']);
        $this->assertEquals(100.0, $calc['performance_persen']);
        $this->assertEquals(90.0, $calc['quality_persen']);
        $this->assertEquals(90.0, $calc['oee_persen']);
    }
}
