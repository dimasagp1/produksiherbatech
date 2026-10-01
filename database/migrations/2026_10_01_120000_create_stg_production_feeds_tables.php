<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stg_production_feeds')) {
            Schema::create('stg_production_feeds', function (Blueprint $table) {
                $table->id();
                $table->string('period', 10)->index();
                $table->unsignedSmallInteger('year')->index();
                $table->unsignedTinyInteger('month')->index();
                $table->decimal('total_output_fisik', 15, 2)->default(0);
                $table->decimal('total_target_plan', 15, 2)->default(0);
                $table->decimal('achievement_pct', 6, 2)->default(0);
                $table->decimal('total_reject_pcs', 15, 2)->default(0);
                $table->decimal('total_material_loss_qty', 15, 2)->default(0);
                $table->decimal('total_machine_hours', 10, 2)->default(0);
                $table->decimal('total_downtime_hours', 10, 2)->default(0);
                $table->decimal('overall_oee_percent', 6, 2)->default(0);
                $table->decimal('overall_yield_percent', 6, 2)->default(0);
                $table->json('stock_opname_variance_summary')->nullable();
                $table->string('source_system')->default('SISTEM_PRODUKSI');
                $table->enum('status', ['Received', 'Reconciled', 'Archived'])->default('Received');
                $table->timestamp('synced_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stg_production_feed_items')) {
            Schema::create('stg_production_feed_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('feed_id')->constrained('stg_production_feeds')->onDelete('cascade');
                $table->string('sku_code')->index();
                $table->string('sku_name');
                $table->string('item_type', 20)->default('FG');
                $table->decimal('output_qty', 15, 2)->default(0);
                $table->decimal('reject_qty', 15, 2)->default(0);
                $table->decimal('loss_qty', 15, 2)->default(0);
                $table->string('uom', 20)->default('Pcs');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stg_production_feed_items');
        Schema::dropIfExists('stg_production_feeds');
    }
};
