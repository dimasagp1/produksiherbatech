<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mps_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mps_plan_id')
                ->constrained('mps_plans')
                ->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('line_id')
                ->nullable()
                ->constrained('lines')
                ->nullOnDelete();
            $table->foreignId('work_center_id')
                ->nullable()
                ->constrained('work_centers')
                ->nullOnDelete();
            $table->enum('shift', ['shift1', 'shift2']);
            $table->unsignedInteger('mp_count')->default(0);
            $table->unsignedInteger('target_qty')->default(0);
            $table->boolean('cleaning')->default(false);
            $table->unsignedInteger('adjusted_qty')->nullable();
            $table->enum('gap_reason', ['cleaning', 'capacity_limit', 'material_shortage', 'manual'])
                ->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mps_plan_id', 'tanggal']);
            $table->index(['tanggal', 'line_id', 'shift']);
            $table->unique(['mps_plan_id', 'tanggal', 'line_id', 'shift'], 'mps_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mps_items');
    }
};
