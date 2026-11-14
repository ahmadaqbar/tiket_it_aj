<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function show(Request $request): View
    {
        return view('auth.profile', [
            'user' => $request->user()->load(['role', 'units']),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $before = Arr::only($user->getAttributes(), ['name', 'email', 'phone']);
        $user->fill($request->validated())->save();
        $this->audit->logChanges('user.profile_updated', $user, $before);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
