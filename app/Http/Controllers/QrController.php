<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Alur QR: scan → (login bila perlu) → form laporan dengan unit terisi.
 * QR hanya mengidentifikasi unit; tidak pernah memberi akses ke tiket tanpa autentikasi.
 */
class QrController extends Controller
{
    public function show(Request $request, string $token): RedirectResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{48}$/', $token) === 1, 404);
        $unit = Unit::query()->where('qr_token', $token)->first();
        abort_unless($unit && $unit->is_active, 404, 'QR Code tidak valid atau unit sudah tidak aktif.');

        $request->session()->put('qr_unit_id', $unit->id);
        $request->session()->put('qr_unit_name', $unit->label());

        $user = $request->user();
        if (! $user) {
            // Setelah login, user diarahkan kembali ke URL QR ini (intended) lalu ke form laporan.
            return redirect()->guest(route('login'));
        }

        if ($user->isItStaff()) {
            $request->session()->forget(['qr_unit_id', 'qr_unit_name']);

            return redirect()->route('it.tickets.index', ['unit_id' => $unit->id])
                ->with('status', "QR unit {$unit->label()}: menampilkan tiket unit tersebut.");
        }

        if (! $user->load('units')->activeUnits()->contains('id', $unit->id)) {
            $request->session()->forget(['qr_unit_id', 'qr_unit_name']);

            return redirect()->route('user.tickets.create')
                ->with('warning', "Anda tidak terdaftar di unit {$unit->label()}. Silakan pilih unit Anda atau hubungi IT Admin.");
        }

        return redirect()->route('user.tickets.create')
            ->with('status', "Laporan untuk unit {$unit->label()}.");
    }
}
