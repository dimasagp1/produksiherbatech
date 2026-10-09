<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->enum('shift', ['shift1', 'shift2'])
                ->nullable()
                ->after('proses')
                ->comment('Shift: shift1 (pagi) atau shift2 (siang)');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
