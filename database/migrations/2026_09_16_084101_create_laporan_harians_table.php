<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_harians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('weekly_plan_id')->constrained('weekly_plans');
            $table->foreignId('produk_id')->constrained('produks');
            $table->enum('proses', ['mixing', 'filling', 'packing']);
            $table->string('batch_number');
            $table->foreignId('mesin_id')->constrained('mesins');
            $table->decimal('ct', 10, 2)->comment('Cycle Time from mesin');
            $table->foreignId('line_id')->constrained('lines');
            $table->date('tanggal');

            // Target
            $table->decimal('target_mp', 10, 2)->comment('Target per Man Power');
            $table->integer('total_mp')->comment('Total Man Power');

            // Jam Kerja
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('gross_time_menit')->comment('Calculated: (End - Start) * 1440');

            // Counter Fisik
            $table->integer('capacity_fisik')->comment('Total unit from counter');
            $table->integer('output_fisik')->comment('Unit that passed/good');

            // Kalkulasi
            $table->integer('waktu_bersih_menit')->comment('MAX(0, Gross Time - Total Downtime)');
            $table->decimal('target_teoritis', 10, 2)->comment('Waktu Bersih * CT');
            $table->decimal('yield_persen', 5, 2)->comment('Output / Capacity * 100');
            $table->decimal('availability_persen', 5, 2)->comment('Waktu Bersih / Gross Time * 100');
            $table->decimal('performance_persen', 5, 2)->comment('Output / Target Teoritis * 100');
            $table->decimal('oee_persen', 5, 2)->comment('Availability * Performance * Yield');
            $table->decimal('produktivitas_persen', 5, 2)->comment('Output / (Target/MP * Total MP) * 100');

            // Status
            $table->enum('status', ['draft', 'submitted', 'locked'])->default('draft');
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tanggal', 'status']);
            $table->index(['user_id', 'tanggal']);
            $table->index(['produk_id', 'tanggal']);
            $table->index(['batch_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_harians');
    }
};
