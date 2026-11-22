@php
    $statusTitle = $filter->get('status') ? \App\Enums\TicketStatus::from($filter->get('status'))->label() : null;
    $title = $statusTitle ? 'Tiket '.$statusTitle : 'Semua Tiket';
    $categoryData = $categories->map(fn ($c) => ['id' => $c->id, 'subs' => $c->subcategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()])->values();
@endphp
<x-layouts::app :title="$title">
    <x-page-header :title="$title" :subtitle="'Shared queue seluruh unit · '.$tickets->total().' tiket'">
        <x-slot:actions>
            <a href="{{ route('it.tickets.export', request()->query()) }}" class="btn-secondary"><x-icon name="document" class="h-4 w-4" /> Export Excel</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-4 p-4" x-data="{ more: {{ $filter->isFiltered() ? 'true' : 'false' }}, categoryId: '{{ $filter->get('category_id') }}', subId: '{{ $filter->get('subcategory_id') }}', cats: @js($categoryData) }">
        @if ($filter->get('status'))<input type="hidden" name="status" value="{{ $filter->get('status') }}">@endif
        <div class="flex flex-col gap-2 sm:flex-row">
            <input name="q" value="{{ $filter->get('q') }}" placeholder="Cari nomor tiket atau judul…" class="form-input" aria-label="Cari">
            <select name="sort" class="form-input sm:max-w-[13rem]" aria-label="Urutkan">
                @foreach ($sorts as $key => $label)
                    <option value="{{ $key }}" @selected(($filter->get('sort') ?? 'newest') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn-secondary" @click="more = !more">Filter</button>
            <button type="submit" class="btn-primary"><x-icon name="search" class="h-4 w-4" /> Terapkan</button>
        </div>

        <div x-show="more" x-cloak class="mt-3 grid gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2 lg:grid-cols-4">
            <select name="status" class="form-input" aria-label="Status" @if ($filter->get('status')) disabled @endif>
                <option value="">Semua status</option>
                @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($filter->get('status') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
            <select name="priority" class="form-input" aria-label="Prioritas">
                <option value="">Semua prioritas</option>
                @foreach ($priorities as $p)<option value="{{ $p->value }}" @selected($filter->get('priority') === $p->value)>{{ $p->label() }}</option>@endforeach
            </select>
            <select name="unit_id" class="form-input" aria-label="Unit">
                <option value="">Semua unit</option>
                @foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) $filter->get('unit_id') === (string) $u->id)>{{ $u->code }} — {{ $u->name }}</option>@endforeach
            </select>
            <select name="assigned" class="form-input" aria-label="PIC">
                <option value="">Semua PIC</option>
                <option value="none" @selected($filter->get('assigned') === 'none')>Belum ada PIC</option>
                <option value="me" @selected($filter->get('assigned') === 'me')>Tiket saya</option>
                @foreach ($itStaff as $staff)<option value="{{ $staff->id }}" @selected((string) $filter->get('assigned') === (string) $staff->id)>{{ $staff->name }}</option>@endforeach
            </select>
            <select name="category_id" class="form-input" aria-label="Kategori" x-model="categoryId" @change="subId = ''">
                <option value="">Semua kategori</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            <select name="subcategory_id" class="form-input" aria-label="Subkategori" x-model="subId">
                <option value="">Semua subkategori</option>
                <template x-for="s in (cats.find(c => String(c.id) === String(categoryId))?.subs ?? [])" :key="s.id">
                    <option :value="s.id" x-text="s.name" :selected="String(s.id) === String(subId)"></option>
                </template>
            </select>
            <input name="reporter" value="{{ $filter->get('reporter') }}" placeholder="Nama pelapor" class="form-input" aria-label="Nama pelapor">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="overdue" value="1" @checked($filter->get('overdue')) class="rounded border-gray-300 text-brand-700"> Melewati SLA
            </label>
            <div><label class="form-label text-xs" for="date_from">Dari tanggal</label><input id="date_from" type="date" name="date_from" value="{{ $filter->get('date_from') }}" class="form-input"></div>
            <div><label class="form-label text-xs" for="date_to">Sampai tanggal</label><input id="date_to" type="date" name="date_to" value="{{ $filter->get('date_to') }}" class="form-input"></div>
            <div class="flex items-end"><a href="{{ route('it.tickets.index', array_filter(['status' => $filter->get('status')])) }}" class="text-sm text-gray-500 hover:underline">Reset filter</a></div>
        </div>
        @if ($errors->any())<p class="form-error mt-2">{{ $errors->first() }}</p>@endif
    </form>

    @if ($tickets->isEmpty())
        <div class="card"><x-empty-state icon="inbox" message="Tidak ada tiket yang cocok dengan filter." /></div>
    @else
        {{-- Desktop --}}
        <div class="card hidden overflow-hidden lg:block">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>No. Tiket</th><th>Tanggal</th><th>Unit</th><th>Pelapor</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>PIC</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($tickets as $t)
                            <tr>
                                <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('it.tickets.show', $t) }}" class="text-brand-700 hover:underline">{{ $t->ticket_number }}</a></td>
                                <td class="text-xs whitespace-nowrap text-gray-500">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                                <td class="font-semibold">{{ $t->unit->code }}</td>
                                <td>{{ $t->reporter?->name }}</td>
                                <td class="max-w-xs"><a href="{{ route('it.tickets.show', $t) }}" class="line-clamp-2 hover:underline">{{ $t->title }}</a>
                                    @if ($t->isOverdue())<span class="text-xs font-medium text-red-600">Melewati SLA</span>@endif</td>
                                <td class="text-xs">{{ $t->category->name }}@if ($t->subcategory)<span class="block text-gray-500">{{ $t->subcategory->name }}</span>@endif</td>
                                <td><x-priority-badge :priority="$t->priority" /></td>
                                <td><x-status-badge :status="$t->status" /></td>
                                <td class="text-xs">{{ $t->assignee?->name ?? '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @if (! $t->assigned_to && ! $t->isClosed())
                                        <form method="POST" action="{{ route('it.tickets.take', $t) }}" class="inline">@csrf<button class="btn-secondary btn-sm" type="submit">Ambil</button></form>
                                    @endif
                                    <a href="{{ route('it.tickets.show', $t) }}" class="btn-primary btn-sm">Buka</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile --}}
        <ul class="space-y-3 lg:hidden">
            @foreach ($tickets as $t)
                <li>
                    <a href="{{ route('it.tickets.show', $t) }}" class="card block p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-gray-500">{{ $t->ticket_number }}</span>
                            <x-status-badge :status="$t->status" /><x-priority-badge :priority="$t->priority" />
                            @if ($t->isOverdue())<span class="text-xs font-medium text-red-600">Melewati SLA</span>@endif
                        </div>
                        <p class="mt-1.5 font-medium text-gray-900">{{ $t->title }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $t->unit->code }} · {{ $t->reporter?->name }} · {{ $t->category->name }} · PIC: {{ $t->assignee?->name ?? '—' }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</x-layouts::app>
