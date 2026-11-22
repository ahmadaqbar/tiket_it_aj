<?php

namespace App\Services\Ai;

/**
 * Abstraksi provider AI. Setiap provider menangani format request/response-nya sendiri.
 */
interface AiProvider
{
    public function name(): string;

    public function model(): string;

    /**
     * @throws AiProviderException
     */
    public function complete(string $system, string $prompt, int $maxTokens): AiResult;
}
