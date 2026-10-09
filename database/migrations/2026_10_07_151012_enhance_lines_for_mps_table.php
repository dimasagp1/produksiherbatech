<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->foreignId('default_work_center_id')
                ->nullable()
                ->after('id')
                ->constrained('work_centers')
                ->nullOnDelete()
                ->comment('Default Work Center untuk Line ini');

            $table->unsignedInteger('effective_ct_seconds')
                ->nullable()
                ->after('default_work_center_id')
                ->comment('Override CT per detik untuk Line ini (jika beda dari Work Center)');

            $table->json('can_run_work_centers')
                ->nullable()
                ->after('effective_ct_seconds')
                ->comment('Array tipe Work Center yang bisa dijalankan Line ini, e.g. ["mixing","filling"]');

            $table->index('default_work_center_id');
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dropForeign(['default_work_center_id']);
            $table->dropIndex(['default_work_center_id']);
            $table->dropColumn(['default_work_center_id', 'effective_ct_seconds', 'can_run_work_centers']);
        });
    }
};
