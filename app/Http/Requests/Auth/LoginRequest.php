<?php

namespace App\Http\Requests\Auth;

use App\Services\AuditService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['login' => 'username / email', 'password' => 'password'];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(AuditService $audit): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $this->input('password')], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
            $audit->log('auth.login_failed', description: 'Login gagal untuk "'.Str::limit($login, 100).'"');

            throw ValidationException::withMessages([
                'login' => 'Username/email atau password salah.',
            ]);
        }

        $user = Auth::user();
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $audit->log('auth.login_blocked', $user, 'Login ditolak: akun nonaktif', userId: $user->id);

            throw ValidationException::withMessages([
                'login' => 'Akun Anda tidak aktif. Silakan hubungi IT Admin.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('login')).'|'.$this->ip());
    }
}
