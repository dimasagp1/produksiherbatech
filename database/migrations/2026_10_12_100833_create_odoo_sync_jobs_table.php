<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('operation');
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('odoo_model')->nullable();
            $table->unsignedBigInteger('odoo_res_id')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_jobs');
    }
};
