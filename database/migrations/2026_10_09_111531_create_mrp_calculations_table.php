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
        Schema::create('mrp_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mps_plan_id')->constrained('mps_plans')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnDelete();
            $table->foreignId('material_produk_id')->nullable()->constrained('produks')->nullOnDelete();
            $table->string('material_name');
            $table->decimal('needed_qty', 14, 4);
            $table->decimal('beginning_stock', 14, 3)->default(0);
            $table->decimal('incoming_qty', 14, 3)->default(0);
            $table->decimal('available_qty', 14, 3)->default(0);
            $table->decimal('shortage_qty', 14, 3)->default(0);
            $table->decimal('rop', 14, 3)->default(0);
            $table->enum('status', ['ok', 'warning', 'critical'])->default('ok');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->index(['mps_plan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mrp_calculations');
    }
};
