<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->string('kode_armada')->unique();
            $table->string('nama_armada');
            $table->string('type')->default('truk');
            $table->string('plat_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_plans', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->unsignedBigInteger('odoo_so_id')->nullable()->index();
            $table->string('customer_name');
            $table->date('planned_date');
            $table->string('status', 20)->default('planned'); // planned|in_transit|delivered|delayed
            $table->foreignId('fleet_id')->nullable()->constrained('fleets')->nullOnDelete();
            $table->string('driver_name')->nullable();
            $table->string('revenue_coa')->nullable();
            $table->boolean('on_time')->nullable();
            $table->boolean('in_full')->nullable();
            $table->boolean('damage_free')->nullable();
            $table->boolean('doc_accuracy')->nullable();
            $table->boolean('complaint')->default(false);
            $table->date('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_plan_id')->constrained('delivery_plans')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produks')->nullOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('uom_name')->nullable();
            $table->unsignedBigInteger('so_line_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('delivery_plans');
        Schema::dropIfExists('fleets');
    }
};
