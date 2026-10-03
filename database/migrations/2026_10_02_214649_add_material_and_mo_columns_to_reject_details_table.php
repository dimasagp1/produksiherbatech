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
        Schema::table('reject_details', function (Blueprint $table) {
            $table->string('material_name')->nullable()->after('jenis_reject');
            $table->string('material_uom', 50)->nullable()->after('material_name');
            $table->unsignedBigInteger('odoo_mo_id')->nullable()->after('odoo_scrap_id');
            $table->string('odoo_mo_name')->nullable()->after('odoo_mo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reject_details', function (Blueprint $table) {
            $table->dropColumn(['material_name', 'material_uom', 'odoo_mo_id', 'odoo_mo_name']);
        });
    }
};
