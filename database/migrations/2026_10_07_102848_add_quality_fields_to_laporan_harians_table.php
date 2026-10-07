<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->decimal('quality_input_persen', 5, 2)->nullable()->after('yield_persen');
            $table->enum('quality_method', ['auto', 'manual'])->default('auto')->after('quality_input_persen');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->dropColumn(['quality_input_persen', 'quality_method']);
        });
    }
};