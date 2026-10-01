<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ScmNumberService
{
    /**
     * Format nomor dokumen SCM: PREFIX/MM/YYYY/0001
     */
    public function next(string $prefix, string $table, string $column): string
    {
        $month = now()->format('m');
        $year = now()->format('Y');
        $pattern = "{$prefix}/{$month}/{$year}/%";

        $last = DB::table($table)
            ->where($column, 'like', $pattern)
            ->orderByDesc($column)
            ->value($column);

        $seq = 1;
        if ($last) {
            $parts = explode('/', $last);
            $seq = ((int) ($parts[3] ?? 0)) + 1;
        }

        return sprintf('%s/%s/%s/%04d', $prefix, $month, $year, $seq);
    }

    public function materialUsage(): string
    {
        return $this->next('MU', 'material_usages', 'usage_number');
    }

    public function stockOpname(): string
    {
        return $this->next('SO', 'stock_opnames', 'opname_number');
    }

    public function delivery(): string
    {
        return $this->next('DL', 'delivery_plans', 'delivery_number');
    }
}
