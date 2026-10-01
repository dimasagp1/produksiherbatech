<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reject_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_harian_id')->constrained('laporan_harians')->cascadeOnDelete();
            $table->string('jenis_reject');
            $table->integer('jumlah')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['laporan_harian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reject_details');
    }
};
