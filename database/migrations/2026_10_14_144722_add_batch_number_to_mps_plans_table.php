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
            if (! Schema::hasColumn('mps_plans', 'batch_number')) {
                $table->string('batch_number', 100)->nullable()->after('work_center_id');
                $table->index('batch_number', 'mps_plans_batch_number_idx');
            }

            $table->dropUnique('mps_plan_revision_unique');
            $table->unique(['produk_id', 'work_center_id', 'month_year', 'batch_number', 'revision'], 'mps_plan_batch_revision_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mps_plans', function (Blueprint $table) {
            $table->dropUnique('mps_plan_batch_revision_unique');
            $table->unique(['produk_id', 'work_center_id', 'month_year', 'revision'], 'mps_plan_revision_unique');

            if (Schema::hasColumn('mps_plans', 'batch_number')) {
                $table->dropIndex('mps_plans_batch_number_idx');
                $table->dropColumn('batch_number');
            }
        });
    }
};
