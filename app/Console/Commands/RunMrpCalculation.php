<?php

namespace App\Console\Commands;

use App\Models\MpsPlan;
use App\Services\MrpCalculationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mrp:calculate {--mps= : MPS plan id}')]
#[Description('Recalculate MRP (MPS x BOM) and set Red Warning')]
class RunMrpCalculation extends Command
{
    public function handle(MrpCalculationService $svc): int
    {
        $mps = $this->option('mps');
        if ($mps) {
            $plan = MpsPlan::findOrFail($mps);
            $r = $svc->calculate($plan);
            $this->info("MRP {$mps}: ".json_encode($r));
        } else {
            $r = $svc->recalculateAllActive();
            $this->info('MRP all: '.json_encode($r));
        }

        return self::SUCCESS;
    }
}
