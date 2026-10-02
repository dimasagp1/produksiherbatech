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
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropUnique('weekly_plans_batch_number_tanggal_unique');
            $table->index(['batch_number', 'tanggal'], 'weekly_plans_batch_number_tanggal_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropIndex('weekly_plans_batch_number_tanggal_index');
            $table->unique(['batch_number', 'tanggal'], 'weekly_plans_batch_number_tanggal_unique');
        });
    }
};
