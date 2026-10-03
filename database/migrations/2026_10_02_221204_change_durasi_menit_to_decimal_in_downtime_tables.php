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
        Schema::table('downtime_details', function (Blueprint $table) {
            $table->decimal('durasi_menit', 8, 2)->default(0)->change();
        });

        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->decimal('total_pause_menit', 8, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('downtime_details', function (Blueprint $table) {
            $table->integer('durasi_menit')->default(0)->change();
        });

        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->integer('total_pause_menit')->default(0)->change();
        });
    }
};
