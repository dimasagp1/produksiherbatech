<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->foreignId('work_center_id')
                ->nullable()
                ->after('line_id')
                ->constrained('work_centers')
                ->nullOnDelete();
            $table->index('work_center_id');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->dropForeign(['work_center_id']);
            $table->dropIndex(['work_center_id']);
            $table->dropColumn('work_center_id');
        });
    }
};