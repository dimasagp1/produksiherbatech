<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            if (! Schema::hasColumn('produks', 'odoo_id')) {
                $table->unsignedBigInteger('odoo_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('produks', 'odoo_uom')) {
                $table->string('odoo_uom')->nullable()->after('proses_default');
            }
            if (! Schema::hasColumn('produks', 'odoo_synced_at')) {
                $table->timestamp('odoo_synced_at')->nullable()->after('status_aktif');
            }
        });

        Schema::table('reject_details', function (Blueprint $table) {
            if (! Schema::hasColumn('reject_details', 'odoo_scrap_id')) {
                $table->unsignedBigInteger('odoo_scrap_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('reject_details', 'odoo_synced_at')) {
                $table->timestamp('odoo_synced_at')->nullable()->after('created_by');
            }
        });

        Schema::table('laporan_harians', function (Blueprint $table) {
            if (! Schema::hasColumn('laporan_harians', 'odoo_mo_id')) {
                $table->unsignedBigInteger('odoo_mo_id')->nullable()->index()->after('weekly_plan_id');
            }
            if (! Schema::hasColumn('laporan_harians', 'odoo_mo_name')) {
                $table->string('odoo_mo_name')->nullable()->after('odoo_mo_id');
            }
            if (! Schema::hasColumn('laporan_harians', 'odoo_synced_at')) {
                $table->timestamp('odoo_synced_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropColumn(['odoo_id', 'odoo_uom', 'odoo_synced_at']);
        });

        Schema::table('reject_details', function (Blueprint $table) {
            $table->dropColumn(['odoo_scrap_id', 'odoo_synced_at']);
        });

        Schema::table('laporan_harians', function (Blueprint $table) {
            $table->dropColumn(['odoo_mo_id', 'odoo_mo_name', 'odoo_synced_at']);
        });
    }
};
