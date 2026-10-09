<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mps_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')
                ->constrained('produks')
                ->cascadeOnDelete();
            $table->foreignId('work_center_id')
                ->constrained('work_centers')
                ->cascadeOnDelete();
            $table->string('month_year', 7)->comment('Format: YYYY-MM');
            $table->enum('status', ['draft', 'approved', 'active', 'closed'])
                ->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['produk_id', 'work_center_id', 'month_year'], 'mps_plan_unique');
            $table->index(['month_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mps_plans');
    }
};
