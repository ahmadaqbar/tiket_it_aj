<x-layouts::app title="Notifikasi">
    <x-page-header title="Notifikasi">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-secondary" type="submit">Tandai semua dibaca</button></form>
        </x-slot:actions>
    </x-page-header>
    <div class="card overflow-hidden">
        @forelse ($notifications as $n)
            <a href="{{ route('notifications.open', $n->id) }}" @class(['flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-3 hover:bg-gray-50', 'bg-brand-50/40' => ! $n->read_at])>
                <span class="min-w-0">
                    <span @class(['block text-sm', 'font-medium text-gray-900' => ! $n->read_at, 'text-gray-600' => $n->read_at])>{{ $n->data['message'] ?? '' }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ $n->data['title'] ?? '' }}</span>
                </span>
                <span class="shrink-0 text-xs text-gray-400">{{ $n->created_at->translatedFormat('d M H:i') }}</span>
            </a>
        @empty
            <x-empty-state icon="bell" message="Belum ada notifikasi." />
        @endforelse
        <div class="px-4 py-3">{{ $notifications->links() }}</div>
    </div>
</x-layouts::app>
