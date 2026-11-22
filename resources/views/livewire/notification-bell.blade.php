<div wire:poll.60s class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" class="relative rounded-lg p-2 text-gray-600 hover:bg-gray-100" aria-label="Notifikasi ({{ $unread }} belum dibaca)">
        <x-icon name="bell" class="h-6 w-6" />
        @if ($unread > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak x-transition.opacity
         class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
            <p class="text-sm font-semibold text-gray-900">Notifikasi</p>
            <a href="{{ route('notifications.index') }}" class="text-xs text-brand-700 hover:underline">Lihat semua</a>
        </div>
        @forelse ($latest as $n)
            <a href="{{ route('notifications.open', $n->id) }}" @class(['block border-b border-gray-50 px-4 py-2.5 text-sm hover:bg-gray-50', 'bg-brand-50/50' => ! $n->read_at])>
                <span @class(['block', 'font-medium text-gray-900' => ! $n->read_at, 'text-gray-600' => $n->read_at])>{{ $n->data['message'] ?? '' }}</span>
                <span class="text-xs text-gray-400">{{ $n->created_at->diffForHumans() }}</span>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-sm text-gray-500">Belum ada notifikasi.</p>
        @endforelse
    </div>
</div>
