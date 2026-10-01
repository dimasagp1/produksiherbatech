<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pondasi Phase C: stock ledger
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produks');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('batch_number')->nullable()->index();
            $table->date('expired_date')->nullable();
            $table->string('location')->default('GUDANG-UTAMA');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['produk_id', 'batch_number', 'location'], 'inv_stocks Produk_batch_loc_unique');
        });

        Schema::create('material_usages', function (Blueprint $table) {
            $table->id();
            $table->string('usage_number')->unique();
            $table->foreignId('weekly_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('usage_date');
            $table->string('shift', 10)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_usage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_usage_id')->constrained('material_usages')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produks')->nullOnDelete();
            $table->string('material_name');
            $table->decimal('quantity_used', 12, 3)->default(0);
            $table->decimal('quantity_standard', 12, 3)->default(0);
            $table->decimal('variance', 12, 3)->default(0);
            $table->foreignId('uom_id')->nullable()->constrained('scm_uoms')->nullOnDelete();
            $table->timestamps();
        });

        // Scrap material (input BOM) — terpisah dari reject_details (output produksi)
        Schema::create('material_scraps', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number');
            $table->foreignId('weekly_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produks')->nullOnDelete();
            $table->string('material_name');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->foreignId('uom_id')->nullable()->constrained('scm_uoms')->nullOnDelete();
            $table->string('source', 10)->default('manual'); // odoo|manual
            $table->string('defect_reason')->nullable();
            $table->unsignedBigInteger('odoo_scrap_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_scraps');
        Schema::dropIfExists('material_usage_items');
        Schema::dropIfExists('material_usages');
        Schema::dropIfExists('inventory_stocks');
    }
};
