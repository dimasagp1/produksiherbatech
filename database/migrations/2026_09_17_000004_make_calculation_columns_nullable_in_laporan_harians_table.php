<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->integer('gross_time_menit')->nullable()->change();
            $table->integer('output_fisik')->nullable()->change();
            $table->integer('waktu_bersih_menit')->nullable()->change();
            $table->decimal('target_teoritis', 10, 2)->nullable()->change();
            $table->decimal('yield_persen', 5, 2)->nullable()->change();
            $table->decimal('availability_persen', 5, 2)->nullable()->change();
            $table->decimal('performance_persen', 5, 2)->nullable()->change();
            $table->decimal('oee_persen', 5, 2)->nullable()->change();
            $table->decimal('produktivitas_persen', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->integer('gross_time_menit')->nullable(false)->change();
            $table->integer('output_fisik')->nullable(false)->change();
            $table->integer('waktu_bersih_menit')->nullable(false)->change();
            $table->decimal('target_teoritis', 10, 2)->nullable(false)->change();
            $table->decimal('yield_persen', 5, 2)->nullable(false)->change();
            $table->decimal('availability_persen', 5, 2)->nullable(false)->change();
            $table->decimal('performance_persen', 5, 2)->nullable(false)->change();
            $table->decimal('oee_persen', 5, 2)->nullable(false)->change();
            $table->decimal('produktivitas_persen', 5, 2)->nullable(false)->change();
        });
    }
};
