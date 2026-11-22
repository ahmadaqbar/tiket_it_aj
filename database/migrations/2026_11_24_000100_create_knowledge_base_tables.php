<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Knowledge Base. Memakai tabel `categories`/`subcategories` yang sudah ada sebagai kategori artikel
 * (satu taksonomi dengan tiket → relevansi artikel ↔ tiket & konteks AI lebih akurat, tanpa tabel duplikat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('subcategories')->restrictOnDelete();
            $table->text('problem');
            $table->text('symptoms')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('steps');
            $table->text('resolution')->nullable();
            $table->string('visibility', 10)->default('INTERNAL')->index();
            $table->string('status', 10)->default('DRAFT')->index();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'visibility']);
            $table->fullText(['title', 'problem', 'symptoms', 'steps', 'resolution'], 'kb_articles_fulltext');
        });

        Schema::create('kb_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 60)->unique();
            $table->timestamps();
        });

        Schema::create('kb_article_tag', function (Blueprint $table) {
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('kb_tag_id')->constrained('kb_tags')->cascadeOnDelete();
            $table->primary(['kb_article_id', 'kb_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_tag');
        Schema::dropIfExists('kb_tags');
        Schema::dropIfExists('kb_articles');
    }
};
