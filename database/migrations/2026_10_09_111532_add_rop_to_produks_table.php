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
        Schema::table('produks', function (Blueprint $table) {
            $table->decimal('rop', 14, 3)->default(0)->after('max_stock');
            $table->integer('lead_time_days')->default(7)->after('rop');
            $table->integer('safety_stock_days')->default(3)->after('lead_time_days');
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropColumn(['rop', 'lead_time_days', 'safety_stock_days']);
        });
    }
};
