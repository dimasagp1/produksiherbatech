<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->time('end_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->time('end_time')->nullable(false)->change();
        });
    }
};
