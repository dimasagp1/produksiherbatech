<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropUnique('weekly_plans_batch_number_unique');
            $table->unique(['batch_number', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropUnique(['batch_number', 'tanggal']);
            $table->unique('batch_number');
        });
    }
};
