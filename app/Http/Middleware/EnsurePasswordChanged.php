<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa user mengganti password sementara yang dibuat IT Admin.
 */
class EnsurePasswordChanged
{
    private const ALLOWED_ROUTES = ['password.edit', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('password.edit')
                ->with('warning', 'Demi keamanan, silakan ganti password sementara Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
