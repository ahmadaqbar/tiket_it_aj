<?php

namespace App\Policies;

use App\Models\KbArticle;
use App\Models\User;
use App\Services\SettingService;

/**
 * IT & IT Admin: semua artikel (termasuk draft/arsip) dan dapat menulis.
 * USER: hanya artikel PUBLISHED + PUBLIC, dan hanya bila pusat bantuan diaktifkan (setting kb.public_enabled).
 */
class KbArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isItStaff() || (bool) app(SettingService::class)->get('kb.public_enabled', true);
    }

    public function view(User $user, KbArticle $article): bool
    {
        if ($user->isItStaff()) {
            return true;
        }

        return $this->viewAny($user) && $article->isPublished() && $article->isPublic();
    }

    public function create(User $user): bool
    {
        return $user->isItStaff();
    }

    public function update(User $user, KbArticle $article): bool
    {
        return $user->isItStaff();
    }
}
