<x-layouts::app title="Audit Log">
    <x-page-header title="Audit Log" subtitle="Catatan aktivitas penting. Hanya dapat dibaca — tidak dapat diubah atau dihapus." />

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <select name="action" class="form-input lg:col-span-2" aria-label="Aksi">
            <option value="">Semua aksi</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
            @endforeach
        </select>
        <select name="user_id" class="form-input" aria-label="User">
            <option value="">Semua user</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <select name="entity_type" class="form-input" aria-label="Entitas">
            <option value="">Semua entitas</option>
            @foreach ($entityTypes as $type)
                <option value="{{ $type }}" @selected(($filters['entity_type'] ?? '') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input" aria-label="Dari tanggal">
        <div class="flex gap-2">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input" aria-label="Sampai tanggal">
            <button class="btn-secondary" type="submit" aria-label="Terapkan filter"><x-icon name="search" class="h-4 w-4" /></button>
        </div>
        @error('date_to')<p class="form-error lg:col-span-6">{{ $message }}</p>@enderror
    </form>

    <div class="card overflow-hidden">
        @if ($logs->isEmpty())
            <x-empty-state icon="shield" message="Belum ada catatan audit." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Entitas</th><th>Keterangan</th><th>IP / Perangkat</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($logs as $log)
                            <tr x-data="{ open: false }">
                                <td class="text-xs whitespace-nowrap text-gray-500">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td><span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs">{{ $log->action }}</span></td>
                                <td class="text-xs">{{ $log->entity_type }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                                <td class="max-w-sm">
                                    {{ $log->description }}
                                    @if ($log->old_values || $log->new_values)
                                        <button type="button" class="ml-1 text-xs text-brand-700 underline" @click="open = !open">detail</button>
                                        <div x-cloak x-show="open" class="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                                            <pre class="overflow-x-auto rounded bg-red-50 p-2 text-red-900">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            <pre class="overflow-x-auto rounded bg-green-50 p-2 text-green-900">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-xs text-gray-500">
                                    <span class="font-mono">{{ $log->ip_address }}</span>
                                    <span class="block max-w-[14rem] truncate" title="{{ $log->user_agent }}">{{ $log->user_agent }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </div>
</x-layouts::app>
