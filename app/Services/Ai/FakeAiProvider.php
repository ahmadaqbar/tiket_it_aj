<?php

namespace App\Services\Ai;

/**
 * Provider palsu untuk automated test — tidak ada panggilan API berbayar.
 * Menyimpan request agar test dapat memeriksa isi prompt (privasi, redaksi, prompt injection).
 */
class FakeAiProvider implements AiProvider
{
    /** @var array<int, array{system:string, prompt:string}> */
    public array $requests = [];

    /** @param array<int, string|\Throwable> $responses */
    public function __construct(private array $responses = []) {}

    public function push(string|\Throwable $response): static
    {
        $this->responses[] = $response;

        return $this;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function complete(string $system, string $prompt, int $maxTokens): AiResult
    {
        $this->requests[] = ['system' => $system, 'prompt' => $prompt];
        $next = array_shift($this->responses) ?? '{}';
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return new AiResult($next, 100, 50, 'fake-model');
    }

    public function lastPrompt(): string
    {
        return end($this->requests)['prompt'] ?? '';
    }
}
