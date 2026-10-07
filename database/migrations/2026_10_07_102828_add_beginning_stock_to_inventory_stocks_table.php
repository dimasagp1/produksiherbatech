<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->decimal('beginning_stock_monthly', 14, 3)
                ->nullable()
                ->after('quantity');
            $table->date('snapshot_date')->nullable()->after('beginning_stock_monthly');
            $table->boolean('is_baseline')->default(false)->after('snapshot_date');
            $table->index(['snapshot_date', 'is_baseline']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropIndex(['snapshot_date', 'is_baseline']);
            $table->dropColumn(['beginning_stock_monthly', 'snapshot_date', 'is_baseline']);
        });
    }
};