@props(['files'])
@if ($files->isNotEmpty())
    <ul {{ $attributes->merge(['class' => 'grid gap-2 sm:grid-cols-2']) }}>
        @foreach ($files as $file)
            <li class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                <span class="min-w-0 truncate" title="{{ $file->original_name }}">{{ $file->original_name }}</span>
                <span class="flex shrink-0 items-center gap-2 text-xs">
                    <span class="text-gray-400">{{ $file->humanSize() }}</span>
                    @if ($file->isImage())
                        <a href="{{ route('attachments.show', [$file, 'inline' => 1]) }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">Lihat</a>
                    @endif
                    <a href="{{ route('attachments.show', $file) }}" class="text-brand-700 hover:underline">Unduh</a>
                </span>
            </li>
        @endforeach
    </ul>
@endif
