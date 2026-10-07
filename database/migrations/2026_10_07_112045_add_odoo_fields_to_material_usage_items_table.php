<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_usage_items', function (Blueprint $table) {
            $table->decimal('odoo_actual_qty', 12, 3)->nullable()->after('variance');
            $table->decimal('odoo_variance_persen', 8, 2)->nullable()->after('odoo_actual_qty');
            $table->decimal('local_vs_odoo_variance_persen', 8, 2)->nullable()->after('odoo_variance_persen');
        });
    }

    public function down(): void
    {
        Schema::table('material_usage_items', function (Blueprint $table) {
            $table->dropColumn(['odoo_actual_qty', 'odoo_variance_persen', 'local_vs_odoo_variance_persen']);
        });
    }
};