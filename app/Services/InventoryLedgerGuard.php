<?php

namespace App\Services;

use App\Models\StockOpname;

class InventoryLedgerGuard
{
    /**
     * Check if a location is currently frozen due to an ongoing stock opname.
     */
    public static function isLocationFrozen(string $location): bool
    {
        return StockOpname::where('location', $location)
            ->whereIn('status', ['initiated', 'counted', 'recalculated', 'posted'])
            ->exists();
    }

    /**
     * Get active opname for a location if frozen.
     */
    public static function getActiveOpname(string $location): ?StockOpname
    {
        return StockOpname::where('location', $location)
            ->whereIn('status', ['initiated', 'counted', 'recalculated', 'posted'])
            ->first();
    }
}
