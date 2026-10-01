<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->string('timer_status')->default('draft')->after('status');
            $table->datetime('pause_started_at')->nullable()->after('timer_status');
            $table->integer('total_pause_menit')->default(0)->after('pause_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->dropColumn(['timer_status', 'pause_started_at', 'total_pause_menit']);
        });
    }
};
