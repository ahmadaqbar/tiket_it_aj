<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log penggunaan AI — sengaja TANPA isi prompt/jawaban (privasi). Hanya metadata untuk biaya & audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('feature', 40);
            $table->string('provider', 40)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('status', 20); // success | error | disabled | rate_limited | invalid_response
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error_code', 50)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
