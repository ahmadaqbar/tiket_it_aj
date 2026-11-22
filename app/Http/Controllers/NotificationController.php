<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Tandai dibaca lalu buka tiket terkait sesuai role (hak akses tetap dicek Policy di halaman tujuan). */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $ticket = Ticket::where('public_id', $notification->data['ticket_public_id'] ?? '')->first();
        if (! $ticket || ! $request->user()->can('view', $ticket)) {
            return redirect()->route('notifications.index')->with('warning', 'Tiket tidak lagi tersedia.');
        }

        return redirect()->route($request->user()->isItStaff() ? 'it.tickets.show' : 'user.tickets.show', $ticket);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
