<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->foreignId('mps_plan_id')
                ->nullable()
                ->after('work_center_id')
                ->constrained('mps_plans')
                ->nullOnDelete();
            $table->index('mps_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropForeign(['mps_plan_id']);
            $table->dropIndex(['mps_plan_id']);
            $table->dropColumn('mps_plan_id');
        });
    }
};
