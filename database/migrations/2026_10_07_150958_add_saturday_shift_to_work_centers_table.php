<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->decimal('saturday_shift_hours', 4, 2)
                ->default(4.0)
                ->after('shift_hours')
                ->comment('Jam kerja shift Sabtu (default 4.0 jam)');
        });
    }

    public function down(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->dropColumn('saturday_shift_hours');
        });
    }
};
