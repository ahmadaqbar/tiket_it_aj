<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Endpoint kompatibel OpenAI Chat Completions (POST {base}/chat/completions).
 */
class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return 'openai_compatible';
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
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new AiProviderException('connection');
        }

        if ($response->failed()) {
            throw new AiProviderException('http_'.$response->status());
        }

        $text = (string) $response->json('choices.0.message.content', '');
        if (trim($text) === '') {
            throw new AiProviderException('empty_response');
        }

        return new AiResult(
            $text,
            (int) $response->json('usage.prompt_tokens', 0),
            (int) $response->json('usage.completion_tokens', 0),
            $response->json('model', $this->model),
        );
    }
}
