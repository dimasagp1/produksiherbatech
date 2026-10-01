<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            if (! Schema::hasColumn('produks', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained('scm_categories')->nullOnDelete()->after('proses_default');
            }
            if (! Schema::hasColumn('produks', 'uom_id')) {
                $table->foreignId('uom_id')->nullable()->constrained('scm_uoms')->nullOnDelete()->after('category_id');
            }
            if (! Schema::hasColumn('produks', 'safety_stock')) {
                $table->decimal('safety_stock', 14, 3)->default(0)->after('uom_id');
            }
            if (! Schema::hasColumn('produks', 'min_stock')) {
                $table->decimal('min_stock', 14, 3)->default(0)->after('safety_stock');
            }
            if (! Schema::hasColumn('produks', 'max_stock')) {
                $table->decimal('max_stock', 14, 3)->default(0)->after('min_stock');
            }
            if (! Schema::hasColumn('produks', 'item_type')) {
                $table->string('item_type', 10)->default('fg')->after('max_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            foreach (['category_id', 'uom_id', 'safety_stock', 'min_stock', 'max_stock', 'item_type'] as $column) {
                if (Schema::hasColumn('produks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
