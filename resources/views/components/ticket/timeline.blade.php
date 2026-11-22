@props(['histories'])
{{-- $histories harus sudah difilter dengan TicketHistory::visibleTo() oleh controller. --}}
<ol class="space-y-4 border-l border-gray-200 pl-4">
    @foreach ($histories as $history)
        <li class="relative">
            <span @class([
                'absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white',
                'bg-amber-500' => $history->action === \App\Models\TicketHistory::INTERNAL_NOTE,
                'bg-brand-500' => $history->action !== \App\Models\TicketHistory::INTERNAL_NOTE,
            ])></span>
            <p class="text-sm font-medium text-gray-800">
                {{ $history->label() }}
                @if ($history->old_value || $history->new_value)
                    <span class="font-normal text-gray-500">
                        @if ($history->old_value){{ $history->displayValue($history->old_value) }} → @endif{{ $history->displayValue($history->new_value) ?? '—' }}
                    </span>
                @endif
            </p>
            <p class="text-xs text-gray-500">{{ $history->user?->name ?? 'Sistem' }} · {{ $history->created_at->translatedFormat('d M Y H:i') }}</p>
            @if ($history->note)<p class="mt-1 text-xs text-gray-600">{{ $history->note }}</p>@endif
        </li>
    @endforeach
</ol>
