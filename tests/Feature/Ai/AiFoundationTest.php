<?php

namespace Tests\Feature\Ai;

use App\Models\AiRequest;
use App\Services\Ai\AiManager;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\OpenAiCompatibleProvider;
use App\Services\SettingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class AiFoundationTest extends AiTestCase
{
    public function test_ai_is_disabled_by_default_and_ticketing_still_works(): void
    {
        $it = $this->makeIt();
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($it)->get("/it/tiket/{$ticket->public_id}")->assertOk()->assertSee('Asisten AI dinonaktifkan');
        $this->actingAs($it)->postJson("/it/tiket/{$ticket->public_id}/ai/classify")
            ->assertStatus(503)->assertJson(['ok' => false]);
        $this->actingAs($it)->put("/it/tiket/{$ticket->public_id}/status", ['status' => 'IN_PROGRESS'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_requests', ['feature' => 'classify', 'status' => 'disabled']);
    }

    public function test_incomplete_configuration_keeps_ai_disabled_even_if_setting_on(): void
    {
        app(SettingService::class)->set('ai.enabled', true);
        config(['ai.api_key' => null]);
        $this->assertStringContainsString('API key', app(AiManager::class)->unavailableReason());

        config(['ai.api_key' => 'x', 'ai.provider' => 'openai_compatible', 'ai.model' => null]);
        $this->assertStringContainsString('Model', app(AiManager::class)->unavailableReason());

        config(['ai.provider' => 'tidak-dikenal']);
        $this->assertStringContainsString('Provider', app(AiManager::class)->unavailableReason());
    }

    public function test_anthropic_provider_request_and_response_format(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"ok":true}']], 'model' => 'claude-opus-5-5',
            'usage' => ['input_tokens' => 321, 'output_tokens' => 45],
        ])]);

        $result = (new AnthropicProvider('sk-key', 'claude-opus-5-5', 'https://api.anthropic.com', '2023-06-01', 30))->complete('SYS', 'PROMPT', 500);

        $this->assertSame('{"ok":true}', $result->text);
        $this->assertSame([321, 45], [$result->inputTokens, $result->outputTokens]);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->hasHeader('x-api-key', 'sk-key') && $r->hasHeader('anthropic-version', '2023-06-01')
            && $r['model'] === 'claude-opus-5-5' && $r['system'] === 'SYS' && $r['messages'][0]['content'] === 'PROMPT' && $r['max_tokens'] === 500);
    }

    public function test_openai_compatible_provider_format(): void
    {
        Http::fake(['llm.example.test/*' => Http::response([
            'choices' => [['message' => ['content' => 'halo']]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 3],
        ])]);

        $result = (new OpenAiCompatibleProvider('key', 'model-x', 'https://llm.example.test/v1', 30))->complete('SYS', 'P', 100);

        $this->assertSame('halo', $result->text);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://llm.example.test/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer key') && $r['messages'][0]['role'] === 'system');
    }

    public function test_provider_http_error_is_handled_gracefully_and_logged_without_secrets(): void
    {
        config(['ai.api_key' => 'sk-test-SECRET-KEY-1234567890', 'ai.model' => 'claude-opus-5-5']);
        app(SettingService::class)->set('ai.enabled', true);
        Http::fake(['*' => Http::response(['error' => ['message' => 'invalid x-api-key sk-test-SECRET-KEY-1234567890']], 401)]);
        $ticket = $this->makeTicket($this->makeUser());

        $response = $this->actingAs($this->makeIt())->postJson("/it/tiket/{$ticket->public_id}/ai/classify");

        $response->assertStatus(503)->assertJson(['ok' => false])->assertJsonMissingPath('data');
        $this->assertStringNotContainsString('SECRET', $response->getContent());
        $log = AiRequest::latest('id')->firstOrFail();
        $this->assertSame(['error', 'http_401'], [$log->status, $log->error_code]);
    }

    public function test_connection_timeout_is_handled(): void
    {
        config(['ai.api_key' => 'k', 'ai.model' => 'm']);
        app(SettingService::class)->set('ai.enabled', true);
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($this->makeIt())->postJson("/it/tiket/{$ticket->public_id}/ai/summary")
            ->assertStatus(503)->assertJsonPath('message', 'Tidak dapat terhubung ke layanan AI (timeout/jaringan). Silakan coba lagi.');
        $this->assertDatabaseHas('ai_requests', ['status' => 'error', 'error_code' => 'connection']);
    }

    public function test_per_user_rate_limit(): void
    {
        config(['ai.rate_per_user_per_hour' => 2]);
        $this->enableAi(['{}', '{}', '{}']);
        $ticket = $this->makeTicket($this->makeUser());
        $it = $this->makeIt();

        $this->actingAs($it)->postJson("/it/tiket/{$ticket->public_id}/ai/summary")->assertOk();
        $this->actingAs($it)->postJson("/it/tiket/{$ticket->public_id}/ai/summary")->assertOk();
        $this->actingAs($it)->postJson("/it/tiket/{$ticket->public_id}/ai/summary")->assertStatus(429);
        $this->assertCount(2, $this->fake->requests);
    }

    public function test_daily_limit(): void
    {
        config(['ai.daily_limit' => 1]);
        $this->enableAi(['{}', '{}']);
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($this->makeIt())->postJson("/it/tiket/{$ticket->public_id}/ai/summary")->assertOk();
        $this->actingAs($this->makeIt())->postJson("/it/tiket/{$ticket->public_id}/ai/summary")->assertStatus(429);
    }

    public function test_usage_log_stores_metadata_only(): void
    {
        $this->enableAi(['{"problem":"x"}']);
        $ticket = $this->makeTicket($this->makeUser());
        $this->actingAs($this->makeIt())->postJson("/it/tiket/{$ticket->public_id}/ai/summary")->assertOk();

        $columns = Schema::getColumnListing('ai_requests');
        $this->assertEmpty(array_intersect($columns, ['prompt', 'response', 'content', 'message']));
        $this->assertDatabaseHas('ai_requests', ['feature' => 'summary', 'status' => 'success', 'input_tokens' => 100, 'output_tokens' => 50, 'ticket_id' => $ticket->id]);
    }

    public function test_invalid_json_from_model_is_reported_not_crashing(): void
    {
        $this->enableAi(['Maaf, saya tidak bisa.']);
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($this->makeIt())->postJson("/it/tiket/{$ticket->public_id}/ai/classify")->assertStatus(503)->assertJson(['ok' => false]);
        $this->assertDatabaseHas('ai_requests', ['status' => 'invalid_response']);
    }

    public function test_api_key_never_rendered_in_html(): void
    {
        $this->enableAi();
        $ticket = $this->makeTicket($this->makeUser());

        $this->actingAs($this->makeAdmin())->get("/it/tiket/{$ticket->public_id}")->assertOk()->assertDontSee('SECRET-KEY');
    }

    public function test_users_cannot_use_ticket_ai_or_kb_ai(): void
    {
        $this->enableAi(['{}']);
        $owner = $this->makeUser();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)->postJson("/it/tiket/{$ticket->public_id}/ai/reply")->assertForbidden();
        $this->actingAs($owner)->postJson('/kb/tanya', ['question' => 'printer tidak bisa print'])->assertForbidden();
        $this->assertCount(0, $this->fake->requests);
    }
}
