<?php

namespace App\Services\Ai;

use App\Services\SettingService;

/**
 * Status & pembuatan provider dari konfigurasi. Konfigurasi tidak lengkap → AI nonaktif dengan alasan yang jelas.
 */
class AiManager
{
    public function __construct(private readonly SettingService $settings) {}

    public function providerName(): string
    {
        return (string) config('ai.provider', 'anthropic');
    }

    public function model(): ?string
    {
        return config('ai.model') ?: config('ai.providers.'.$this->providerName().'.model');
    }

    /** Alasan AI tidak dapat dipakai, atau null bila siap. */
    public function unavailableReason(): ?string
    {
        if (! (bool) $this->settings->get('ai.enabled', false)) {
            return 'Asisten AI dinonaktifkan. IT Admin dapat mengaktifkannya di System Settings.';
        }
        if (! array_key_exists($this->providerName(), (array) config('ai.providers'))) {
            return 'Provider AI tidak dikenal. Periksa AI_PROVIDER di .env.';
        }
        if (blank(config('ai.api_key'))) {
            return 'API key AI belum dikonfigurasi (AI_API_KEY di .env).';
        }
        if (blank($this->model())) {
            return 'Model AI belum dikonfigurasi (AI_MODEL di .env).';
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function makeProvider(): AiProvider
    {
        $name = $this->providerName();
        $cfg = (array) config('ai.providers.'.$name, []);
        $baseUrl = config('ai.base_url') ?: ($cfg['base_url'] ?? '');
        $timeout = max(5, min(60, (int) config('ai.timeout', 30)));

        return match ($name) {
            'openai_compatible' => new OpenAiCompatibleProvider((string) config('ai.api_key'), (string) $this->model(), $baseUrl, $timeout),
            default => new AnthropicProvider((string) config('ai.api_key'), (string) $this->model(), $baseUrl, $cfg['version'] ?? '2023-06-01', $timeout),
        };
    }
}
