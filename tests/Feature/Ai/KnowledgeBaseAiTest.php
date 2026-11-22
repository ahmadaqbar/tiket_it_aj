<?php

namespace Tests\Feature\Ai;

use App\Services\KnowledgeBaseService;

class KnowledgeBaseAiTest extends AiTestCase
{
    public function test_answer_with_validated_kb_references(): void
    {
        $it = $this->makeIt();
        $article = app(KnowledgeBaseService::class)->create(['title' => 'Printer kasir tidak bisa print', 'problem' => 'Printer kasir tidak bisa print',
            'steps' => "1. Cek kertas\n2. Restart", 'visibility' => 'INTERNAL', 'publish' => true], $it);
        $this->enableAi([json_encode(['answer' => 'Periksa kertas dan restart printer.', 'steps' => ['Cek kertas'], 'source_ids' => [$article->id, 777]])]);

        $this->actingAs($it)->postJson('/kb/tanya', ['question' => 'Bagaimana menangani printer kasir yang tidak bisa print?'])->assertOk()
            ->assertJsonPath('data.based_on_knowledge_base', true)
            ->assertJsonCount(1, 'data.sources')
            ->assertJsonPath('data.sources.0.title', 'Printer kasir tidak bisa print');
    }

    public function test_without_relevant_articles_answer_is_marked_general_even_if_model_claims_sources(): void
    {
        $this->enableAi([json_encode(['answer' => 'Menurut artikel #5 ...', 'source_ids' => [5]])]);

        $this->actingAs($this->makeIt())->postJson('/kb/tanya', ['question' => 'Bagaimana konfigurasi VPN kantor cabang?'])->assertOk()
            ->assertJsonPath('data.based_on_knowledge_base', false)
            ->assertJsonPath('data.sources', [])
            ->assertJsonPath('data.articles_found', 0);
        $this->assertStringContainsString('tidak ada artikel', $this->fake->lastPrompt());
    }

    public function test_draft_and_archived_articles_are_never_sent_to_ai(): void
    {
        $it = $this->makeIt();
        $kb = app(KnowledgeBaseService::class);
        $kb->create(['title' => 'Printer draft rahasia', 'problem' => 'Printer kasir', 'steps' => 'LANGKAH-DRAFT', 'visibility' => 'INTERNAL'], $it);
        $archived = $kb->create(['title' => 'Printer lama', 'problem' => 'Printer kasir', 'steps' => 'LANGKAH-ARSIP', 'visibility' => 'INTERNAL', 'publish' => true], $it);
        $kb->archive($archived, $it);
        $this->enableAi(['{"answer":"Saran umum","source_ids":[]}']);

        $this->actingAs($it)->postJson('/kb/tanya', ['question' => 'printer kasir tidak bisa print'])->assertOk();

        $this->assertStringNotContainsString('LANGKAH-DRAFT', $this->fake->lastPrompt());
        $this->assertStringNotContainsString('LANGKAH-ARSIP', $this->fake->lastPrompt());
    }

    public function test_question_validation_and_page(): void
    {
        $this->enableAi();
        $it = $this->makeIt();

        $this->actingAs($it)->get('/kb/tanya')->assertOk()->assertSee('Tanyakan');
        $this->actingAs($it)->postJson('/kb/tanya', ['question' => 'hi'])->assertStatus(422);
    }

    public function test_disabled_ai_page_shows_message(): void
    {
        $this->actingAs($this->makeIt())->get('/kb/tanya')->assertOk()->assertSee('Asisten AI dinonaktifkan');
    }
}
