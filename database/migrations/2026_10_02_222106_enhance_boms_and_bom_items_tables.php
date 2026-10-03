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
        Schema::table('boms', function (Blueprint $table) {
            $table->string('version', 150)->default('1')->change();
            if (! Schema::hasColumn('boms', 'odoo_bom_id')) {
                $table->unsignedBigInteger('odoo_bom_id')->nullable()->index()->after('produk_id');
            }
            if (! Schema::hasColumn('boms', 'base_qty')) {
                $table->decimal('base_qty', 14, 4)->default(1.0)->after('version');
            }
            if (! Schema::hasColumn('boms', 'uom_id')) {
                $table->foreignId('uom_id')->nullable()->constrained('scm_uoms')->nullOnDelete()->after('base_qty');
            }
        });

        Schema::table('bom_items', function (Blueprint $table) {
            $table->decimal('quantity', 16, 6)->default(0)->change();
            if (! Schema::hasColumn('bom_items', 'uom_name')) {
                $table->string('uom_name', 50)->nullable()->after('uom_id');
            }
            if (! Schema::hasColumn('bom_items', 'category')) {
                $table->string('category', 50)->nullable()->after('uom_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('bom_items', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('bom_items', 'uom_name')) {
                $table->dropColumn('uom_name');
            }
            $table->decimal('quantity', 12, 3)->default(0)->change();
        });

        Schema::table('boms', function (Blueprint $table) {
            if (Schema::hasColumn('boms', 'uom_id')) {
                $table->dropForeign(['uom_id']);
                $table->dropColumn('uom_id');
            }
            if (Schema::hasColumn('boms', 'base_qty')) {
                $table->dropColumn('base_qty');
            }
            if (Schema::hasColumn('boms', 'odoo_bom_id')) {
                $table->dropColumn('odoo_bom_id');
            }
            $table->string('version', 20)->default('1')->change();
        });
    }
};
