<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mps_plans', function (Blueprint $table) {
            $table->decimal('beginning_stock_snapshot', 14, 3)->nullable()->after('notes');
            $table->date('beginning_stock_date')->nullable()->after('beginning_stock_snapshot');
            $table->timestamp('closed_at')->nullable()->after('beginning_stock_date');
        });
    }

    public function down(): void
    {
        Schema::table('mps_plans', function (Blueprint $table) {
            $table->dropColumn(['beginning_stock_snapshot', 'beginning_stock_date', 'closed_at']);
        });
    }
};
