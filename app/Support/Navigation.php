<?php

namespace App\Support;

use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu sidebar per role. Item hanya ditampilkan bila route-nya sudah terdaftar,
 * sehingga modul sprint berikutnya muncul otomatis setelah diimplementasikan.
 * Catatan: menu hanyalah pelengkap — otorisasi tetap di middleware/policy.
 */
class Navigation
{
    /**
     * @return array<int, array{heading:?string, items: array<int, array{label:string, route:string, icon:string, params?:array, active?:string}>}>
     */
    public static function sectionsFor(User $user): array
    {
        if ($user->isUser()) {
            $sections = [[
                'heading' => null,
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'user.dashboard', 'icon' => 'home'],
                    ['label' => 'Buat Laporan', 'route' => 'user.tickets.create', 'icon' => 'plus'],
                    ['label' => 'Tiket Saya', 'route' => 'user.tickets.index', 'icon' => 'ticket'],
                    ['label' => 'Riwayat', 'route' => 'user.tickets.history', 'icon' => 'clock'],
                    ['label' => 'Bantuan', 'route' => 'kb.index', 'icon' => 'book', 'active' => 'kb.*', 'gate' => 'viewAny-kb'],
                    ['label' => 'Profil', 'route' => 'profile.show', 'icon' => 'user'],
                ],
            ]];
        } else {
            $sections = [[
                'heading' => null,
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'it.dashboard', 'icon' => 'home'],
                    ['label' => 'Semua Tiket', 'route' => 'it.tickets.index', 'icon' => 'inbox'],
                    ['label' => 'Tiket Baru', 'route' => 'it.tickets.index', 'params' => ['status' => 'NEW'], 'icon' => 'sparkles'],
                    ['label' => 'Dalam Proses', 'route' => 'it.tickets.index', 'params' => ['status' => 'IN_PROGRESS'], 'icon' => 'wrench'],
                    ['label' => 'Pending', 'route' => 'it.tickets.index', 'params' => ['status' => 'PENDING'], 'icon' => 'pause'],
                    ['label' => 'Resolved', 'route' => 'it.tickets.index', 'params' => ['status' => 'RESOLVED'], 'icon' => 'check'],
                    ['label' => 'Closed', 'route' => 'it.tickets.index', 'params' => ['status' => 'CLOSED'], 'icon' => 'archive'],
                    ['label' => 'Knowledge Base', 'route' => 'kb.index', 'icon' => 'book', 'active' => 'kb.*'],
                    ['label' => 'Analytics', 'route' => 'it.analytics', 'icon' => 'chart'],
                    ['label' => 'Reports', 'route' => 'it.reports.index', 'icon' => 'document'],
                ],
            ]];

            if ($user->isItAdmin()) {
                $sections[] = [
                    'heading' => 'Administrasi',
                    'items' => [
                        ['label' => 'User Management', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => 'admin.users.*'],
                        ['label' => 'Unit Management', 'route' => 'admin.units.index', 'icon' => 'building', 'active' => 'admin.units.*'],
                        ['label' => 'Category Management', 'route' => 'admin.categories.index', 'icon' => 'tag', 'active' => 'admin.categories.*'],
                        ['label' => 'SLA Settings', 'route' => 'admin.sla.index', 'icon' => 'timer', 'active' => 'admin.sla.*'],
                        ['label' => 'QR Management', 'route' => 'admin.qr.index', 'icon' => 'qr', 'active' => 'admin.qr.*'],
                        ['label' => 'System Settings', 'route' => 'admin.settings.index', 'icon' => 'cog', 'active' => 'admin.settings.*'],
                        ['label' => 'Audit Log', 'route' => 'admin.audit-logs.index', 'icon' => 'shield', 'active' => 'admin.audit-logs.*'],
                    ],
                ];
            }
        }

        foreach ($sections as &$section) {
            $section['items'] = array_values(array_filter($section['items'], fn ($item) => Route::has($item['route'])
                && (! isset($item['gate']) || $user->can('viewAny', KbArticle::class))));
        }

        return array_values(array_filter($sections, fn ($s) => $s['items'] !== []));
    }

    /** Navigasi bawah (mobile) untuk role USER. */
    public static function mobileFor(User $user): array
    {
        if (! $user->isUser()) {
            return [];
        }

        return array_values(array_filter([
            ['label' => 'Dashboard', 'route' => 'user.dashboard', 'icon' => 'home'],
            ['label' => 'Buat Laporan', 'route' => 'user.tickets.create', 'icon' => 'plus'],
            ['label' => 'Tiket Saya', 'route' => 'user.tickets.index', 'icon' => 'ticket'],
            ['label' => 'Profil', 'route' => 'profile.show', 'icon' => 'user'],
        ], fn ($item) => Route::has($item['route'])));
    }

    public static function isActive(array $item): bool
    {
        $request = request();

        if (isset($item['params'])) {
            return $request->routeIs($item['route'])
                && collect($item['params'])->every(fn ($v, $k) => $request->query($k) === $v);
        }

        if ($item['route'] === 'it.tickets.index') {
            return $request->routeIs('it.tickets.index') && ! $request->filled('status');
        }

        return $request->routeIs($item['active'] ?? $item['route']);
    }
}
