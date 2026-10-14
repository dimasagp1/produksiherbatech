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
            if (! Schema::hasColumn('mps_plans', 'revision')) {
                $table->unsignedInteger('revision')->default(1)->after('month_year');
            }
            if (! Schema::hasColumn('mps_plans', 'is_active_revision')) {
                $table->boolean('is_active_revision')->default(true)->after('revision');
            }
            if (! Schema::hasColumn('mps_plans', 'parent_plan_id')) {
                $table->foreignId('parent_plan_id')->nullable()->constrained('mps_plans')->nullOnDelete()->after('is_active_revision');
            }

            $table->index('produk_id', 'mps_plans_produk_id_fk_idx');
            $table->dropUnique('mps_plan_unique');
            $table->unique(['produk_id', 'work_center_id', 'month_year', 'revision'], 'mps_plan_revision_unique');
            $table->index(['month_year', 'is_active_revision', 'status'], 'mps_plan_month_active_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mps_plans', function (Blueprint $table) {
            $table->dropUnique('mps_plan_revision_unique');
            $table->dropIndex('mps_plan_month_active_status_idx');

            $table->unique(['produk_id', 'work_center_id', 'month_year'], 'mps_plan_unique');
            $table->dropIndex('mps_plans_produk_id_fk_idx');

            if (Schema::hasColumn('mps_plans', 'parent_plan_id')) {
                $table->dropForeign(['parent_plan_id']);
                $table->dropColumn('parent_plan_id');
            }
            if (Schema::hasColumn('mps_plans', 'is_active_revision')) {
                $table->dropColumn('is_active_revision');
            }
            if (Schema::hasColumn('mps_plans', 'revision')) {
                $table->dropColumn('revision');
            }
        });
    }
};
