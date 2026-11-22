<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiket inti. Lihat DATABASE_DESIGN.md §3.7–3.12.
 * Migration aditif — tidak mengubah tabel yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->date('ticket_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['unit_id', 'ticket_date']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('ticket_number', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('subcategories')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->string('priority', 10)->index();
            $table->string('status', 15)->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 10)->default('WEB');
            $table->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
            $table->index(['unit_id', 'created_at']);
            $table->index(['category_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index('assigned_to');
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            // FK ke ticket_replies ditambahkan pada migration balasan (Phase 1c).
            $table->unsignedBigInteger('ticket_reply_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('visibility', 10)->default('PUBLIC');
            $table->timestamps();
        });

        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_histories');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_sequences');
    }
};
