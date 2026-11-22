<?php

namespace App\Services\Ai;

use App\Models\AiRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Satu-satunya jalur ke provider AI: cek aktif, rate limit per user, batas harian, timeout, error handling,
 * dan pencatatan penggunaan (tanpa isi prompt/jawaban).
 */
class AiGateway
{
    public function __construct(private readonly AiManager $manager) {}

    /**
     * @throws AiUnavailableException
     */
    public function complete(string $feature, User $user, string $system, string $prompt, ?int $ticketId = null, ?int $maxTokens = null): AiResult
    {
        if ($reason = $this->manager->unavailableReason()) {
            $this->log($feature, $user, $ticketId, 'disabled');
            throw new AiUnavailableException($reason);
        }

        $key = 'ai:user:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, max(1, (int) config('ai.rate_per_user_per_hour', 30)))) {
            $this->log($feature, $user, $ticketId, 'rate_limited');
            throw new AiUnavailableException('Batas penggunaan AI per jam tercapai. Coba lagi nanti.', 429);
        }
        $daily = (int) config('ai.daily_limit', 300);
        if ($daily > 0 && AiRequest::where('status', 'success')->where('created_at', '>=', now()->startOfDay())->count() >= $daily) {
            $this->log($feature, $user, $ticketId, 'rate_limited', errorCode: 'daily_limit');
            throw new AiUnavailableException('Batas penggunaan AI harian organisasi tercapai.', 429);
        }
        RateLimiter::hit($key, 3600);

        $provider = app(AiProvider::class);
        $started = microtime(true);
        try {
            $result = $provider->complete($system, $prompt, $maxTokens ?? (int) config('ai.max_output_tokens', 1200));
        } catch (AiProviderException $e) {
            $this->log($feature, $user, $ticketId, 'error', $provider, $started, errorCode: $e->errorCode);
            throw new AiUnavailableException($this->friendly($e->errorCode));
        } catch (Throwable $e) {
            // Jangan pernah mencatat pesan mentah (bisa memuat URL/header); cukup kelas exception.
            Log::warning('AI provider failure', ['feature' => $feature, 'exception' => $e::class]);
            $this->log($feature, $user, $ticketId, 'error', $provider, $started, errorCode: 'unexpected');
            throw new AiUnavailableException('Layanan AI sedang tidak dapat dihubungi. Silakan lanjutkan tanpa AI.');
        }

        $this->log($feature, $user, $ticketId, 'success', $provider, $started, $result);

        return $result;
    }

    public function markInvalid(string $feature, User $user, ?int $ticketId = null): void
    {
        $this->log($feature, $user, $ticketId, 'invalid_response', errorCode: 'invalid_json');
    }

    private function friendly(string $code): string
    {
        return match (true) {
            $code === 'connection' => 'Tidak dapat terhubung ke layanan AI (timeout/jaringan). Silakan coba lagi.',
            in_array($code, ['http_401', 'http_403'], true) => 'Konfigurasi API key AI ditolak oleh provider. Hubungi IT Admin.',
            $code === 'http_429' => 'Provider AI sedang membatasi permintaan. Coba lagi beberapa saat.',
            str_starts_with($code, 'http_5') => 'Layanan AI sedang gangguan. Silakan coba lagi nanti.',
            default => 'Layanan AI tidak memberikan jawaban yang dapat dipakai. Silakan coba lagi.',
        };
    }

    private function log(string $feature, User $user, ?int $ticketId, string $status, ?AiProvider $provider = null, ?float $started = null, ?AiResult $result = null, ?string $errorCode = null): void
    {
        AiRequest::create([
            'user_id' => $user->id,
            'ticket_id' => $ticketId,
            'feature' => $feature,
            'provider' => $provider?->name() ?? $this->manager->providerName(),
            'model' => $result?->model ?? $provider?->model() ?? $this->manager->model(),
            'status' => $status,
            'input_tokens' => $result?->inputTokens ?? 0,
            'output_tokens' => $result?->outputTokens ?? 0,
            'duration_ms' => $started ? (int) round((microtime(true) - $started) * 1000) : 0,
            'error_code' => $errorCode,
        ]);
    }
}
