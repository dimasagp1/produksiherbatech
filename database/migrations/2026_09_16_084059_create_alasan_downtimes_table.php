<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alasan_downtimes', function (Blueprint $table) {
            $table->id();
            $table->string('nama_alasan');
            $table->enum('tipe_input', ['manual', 'default_hardcode']);
            $table->integer('durasi_default_menit')->nullable()->comment('Only for default_hardcode type');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alasan_downtimes');
    }
};
