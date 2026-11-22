@props(['ticket'])
{{-- $ticket->replies harus sudah difilter dengan TicketReply::visibleTo() oleh controller. --}}
@if ($ticket->replies->isEmpty())
    <p class="rounded-lg bg-gray-50 px-3 py-3 text-sm text-gray-500">Belum ada percakapan.</p>
@else
    <ol class="space-y-4">
        @foreach ($ticket->replies as $reply)
            @php
                $fromReporter = $reply->user_id === $ticket->user_id;
                $internal = $reply->isInternal();
            @endphp
            <li @class(['flex', 'justify-end' => $fromReporter])>
                <div @class([
                    'max-w-[92%] rounded-xl px-4 py-3 text-sm sm:max-w-[80%]',
                    'border border-amber-200 bg-amber-50' => $internal,
                    'bg-brand-50 ring-1 ring-brand-100' => ! $internal && $fromReporter,
                    'border border-gray-200 bg-white' => ! $internal && ! $fromReporter,
                ])>
                    <div class="mb-1 flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                        <span class="font-semibold text-gray-800">{{ $reply->author?->name ?? 'Pengguna' }}</span>
                        <span>{{ $fromReporter ? 'Pelapor' : 'Tim IT' }}</span>
                        @if ($internal)
                            <span class="rounded bg-amber-200/70 px-1.5 py-0.5 font-semibold text-amber-900">Catatan Internal — tidak terlihat pelapor</span>
                        @endif
                        <span title="{{ $reply->created_at->format('d/m/Y H:i') }}">{{ $reply->created_at->translatedFormat('d M Y H:i') }}</span>
                    </div>
                    <div class="leading-relaxed whitespace-pre-line text-gray-800">{{ $reply->message }}</div>
                    <x-ticket.attachment-list :files="$reply->attachments" class="mt-3" />
                </div>
            </li>
        @endforeach
    </ol>
@endif
