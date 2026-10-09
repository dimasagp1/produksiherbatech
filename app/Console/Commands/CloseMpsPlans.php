<?php

namespace App\Console\Commands;

use App\Models\MpsPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('mps:close-expired')]
#[Description('Auto-close MPS plans whose month has ended (active/approved -> closed)')]
class CloseMpsPlans extends Command
{
    public function handle(): int
    {
        $cutoff = Carbon::now()->startOfMonth();
        $count = MpsPlan::whereIn('status', ['active', 'approved'])->where('month_year', '<', $cutoff->format('Y-m'))->update(['status' => 'closed', 'closed_at' => now()]);
        $this->info("Closed {$count} MPS plan(s) before {$cutoff->format('Y-m')}");

        return self::SUCCESS;
    }
}
