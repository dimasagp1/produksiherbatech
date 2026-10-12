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
        Schema::create('material_supply_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_supply_id')->constrained('material_supplies')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnDelete();
            $table->decimal('required_qty', 14, 4);
            $table->decimal('delivered_qty', 14, 4)->default(0);
            $table->decimal('variance_qty', 14, 4)->storedAs('delivered_qty - required_qty');
            $table->foreignId('uom_id')->nullable()->constrained('scm_uoms')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_supply_items');
    }
};
