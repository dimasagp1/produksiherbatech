<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reject_details', function (Blueprint $table) {
            if (! Schema::hasColumn('reject_details', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('laporan_harian_id')->constrained('users')->nullOnDelete();
                $table->index(['created_by']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reject_details', function (Blueprint $table) {
            if (Schema::hasColumn('reject_details', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
