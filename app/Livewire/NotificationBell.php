<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Lonceng notifikasi dengan polling ringan (wire:poll) — pengganti WebSocket untuk shared hosting.
 */
class NotificationBell extends Component
{
    public function render()
    {
        $user = Auth::user();

        return view('livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
