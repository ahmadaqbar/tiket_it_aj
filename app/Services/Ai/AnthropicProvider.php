<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Messages API (POST /v1/messages).
 */
class AnthropicProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly string $version,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(string $system, string $prompt, int $maxTokens): AiResult
    {
        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout(10)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => $this->version,
                ])
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (ConnectionException) {
            throw new AiProviderException('connection');
        }

        if ($response->failed()) {
            throw new AiProviderException('http_'.$response->status());
        }

        $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode("\n");
        if (trim($text) === '') {
            throw new AiProviderException('empty_response');
        }

        return new AiResult(
            $text,
            (int) $response->json('usage.input_tokens', 0),
            (int) $response->json('usage.output_tokens', 0),
            $response->json('model', $this->model),
        );
    }
}
