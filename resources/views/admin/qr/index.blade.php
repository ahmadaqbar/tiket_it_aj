<x-layouts::app title="QR Management">
    <x-page-header title="QR Management" subtitle="Tempel QR di setiap unit. Scan → login → form laporan dengan unit terisi otomatis. QR tidak membuka data tiket tanpa login.">
        <x-slot:actions>
            <a href="{{ route('admin.qr.pdf-all') }}" class="btn-secondary"><x-icon name="document" class="h-4 w-4" /> PDF Semua Unit Aktif</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($units as $row)
            @php $unit = $row['unit']; @endphp
            <div @class(['card flex flex-col p-4', 'opacity-60' => ! $unit->is_active])>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $unit->code }} — {{ $unit->name }}</p>
                        <p class="text-xs text-gray-500">Token dibuat {{ $unit->qr_token_generated_at?->translatedFormat('d M Y H:i') ?? '—' }}</p>
                    </div>
                    <x-active-badge :active="$unit->is_active" />
                </div>
                <div class="mx-auto my-4 w-40 [&>svg]:h-auto [&>svg]:w-full" aria-label="QR {{ $unit->code }}">{!! $row['svg'] !!}</div>
                <p class="truncate text-center font-mono text-[11px] text-gray-400" title="{{ $row['url'] }}">{{ $row['url'] }}</p>
                <div class="mt-4 grid grid-cols-3 gap-2">
                    <a href="{{ route('admin.qr.png', $unit) }}" class="btn-secondary btn-sm">PNG</a>
                    <a href="{{ route('admin.qr.pdf', $unit) }}" class="btn-secondary btn-sm">PDF</a>
                    <a href="{{ route('admin.qr.print', $unit) }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">Cetak</a>
                </div>
                <form method="POST" action="{{ route('admin.qr.regenerate', $unit) }}" class="mt-2"
                      onsubmit="return confirm('Ganti token QR {{ $unit->code }}? QR yang sudah tercetak tidak akan berlaku lagi.')">
                    @csrf
                    <button class="w-full text-xs text-red-600 hover:underline" type="submit">Regenerate token</button>
                </form>
            </div>
        @endforeach
    </div>
</x-layouts::app>
