<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1c — percakapan tiket, riwayat assignment, template balasan cepat.
 * Aditif: hanya menambah tabel dan satu foreign key pada ticket_attachments.ticket_reply_id
 * (kolom sudah ada sejak Phase 1b dan selalu NULL sampai fase ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('message');
            $table->string('visibility', 10)->default('PUBLIC');
            $table->timestamps();
            $table->index(['ticket_id', 'visibility', 'created_at']);
        });

        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->foreign('ticket_reply_id')->references('id')->on('ticket_replies')->nullOnDelete();
        });

        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['ticket_id', 'unassigned_at']);
        });

        Schema::create('quick_reply_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('message');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_reply_templates');
        Schema::dropIfExists('ticket_assignments');
        Schema::table('ticket_attachments', fn (Blueprint $table) => $table->dropForeign(['ticket_reply_id']));
        Schema::dropIfExists('ticket_replies');
    }
};
