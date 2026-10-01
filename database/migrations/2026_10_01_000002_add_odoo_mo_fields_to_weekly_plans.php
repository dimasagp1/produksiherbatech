<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('weekly_plans', 'odoo_mo_id')) {
                $table->unsignedBigInteger('odoo_mo_id')->nullable()->index()->after('batch_number');
            }
            if (! Schema::hasColumn('weekly_plans', 'mo_status')) {
                $table->string('mo_status', 20)->default('pending')->after('odoo_mo_id');
            }
            if (! Schema::hasColumn('weekly_plans', 'target_output')) {
                $table->integer('target_output')->default(0)->after('mo_status');
            }
            if (! Schema::hasColumn('weekly_plans', 'mp_count')) {
                $table->integer('mp_count')->default(0)->after('target_output');
            }
            if (! Schema::hasColumn('weekly_plans', 'multiplier')) {
                $table->integer('multiplier')->default(2000)->after('mp_count');
            }
            if (! Schema::hasColumn('weekly_plans', 'packing_hold')) {
                $table->boolean('packing_hold')->default(false)->after('multiplier');
            }
            if (! Schema::hasColumn('weekly_plans', 'line_id')) {
                $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete()->after('produk_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            foreach (['odoo_mo_id', 'mo_status', 'target_output', 'mp_count', 'multiplier', 'packing_hold', 'line_id'] as $column) {
                if (Schema::hasColumn('weekly_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
