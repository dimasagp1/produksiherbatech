<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            if (! Schema::hasColumn('laporan_harians', 'shift')) {
                $table->enum('shift', ['shift1', 'shift2'])->default('shift1')->after('tanggal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            if (Schema::hasColumn('laporan_harians', 'shift')) {
                $table->dropColumn('shift');
            }
        });
    }
};
