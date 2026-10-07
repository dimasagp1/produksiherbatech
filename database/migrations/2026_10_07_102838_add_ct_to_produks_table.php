<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->unsignedInteger('ct_seconds')->nullable()->after('proses_default');
            $table->foreignId('work_center_id')
                ->nullable()
                ->after('ct_seconds')
                ->constrained('work_centers')
                ->nullOnDelete();
            $table->index('work_center_id');
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropForeign(['work_center_id']);
            $table->dropIndex(['work_center_id']);
            $table->dropColumn(['ct_seconds', 'work_center_id']);
        });
    }
};