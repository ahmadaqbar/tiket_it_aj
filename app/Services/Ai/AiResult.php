<?php

namespace App\Services\Ai;

final class AiResult
{
    public function __construct(
        public readonly string $text,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $model = null,
    ) {}
}
