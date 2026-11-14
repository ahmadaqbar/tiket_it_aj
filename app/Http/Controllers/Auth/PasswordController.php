<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $request->session()->regenerate();
        // Sesi di perangkat lain diputus; sesi saat ini dipertahankan.
        $this->users->changeOwnPassword($user, $request->validated('password'), $request->session()->getId());

        return redirect()->route($user->homeRoute())->with('success', 'Password berhasil diubah.');
    }
}
