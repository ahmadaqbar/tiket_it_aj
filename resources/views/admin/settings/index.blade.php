<x-layouts::app title="System Settings">
    <x-page-header title="System Settings" subtitle="Identitas, pengaturan tiket, fitur, dan template. Rahasia (password DB, API key) tetap di file .env server." />

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')

        <section class="card p-5 sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Identitas & Branding</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-form.input name="app_name" label="Nama Aplikasi" :value="$values['app_name']" required />
                <x-form.input name="short_name" label="Nama Singkat (sidebar, tab, PWA)" :value="$values['short_name']" required maxlength="30" />
                <x-form.input name="organization_name" label="Nama Organisasi" :value="$values['organization_name']" required />
                <x-form.input name="support_team" label="Nama Tim IT" :value="$values['support_team']" required />
                <x-form.input name="contact_email" label="Email Kontak IT" type="email" :value="$values['contact_email']" />
                <x-form.input name="contact_phone" label="Telepon / WhatsApp IT" :value="$values['contact_phone']" />
                <div class="sm:col-span-2"><x-form.input name="contact_address" label="Alamat / Lokasi Tim IT" :value="$values['contact_address']" /></div>
                <div>
                    <label class="form-label" for="primary_color">Warna Utama</label>
                    <div class="flex items-center gap-2" x-data="{ c: '{{ old('primary_color', $values['primary_color']) }}' }">
                        <input type="color" x-model="c" class="h-10 w-14 rounded border border-gray-300" aria-label="Pilih warna">
                        <input id="primary_color" name="primary_color" x-model="c" class="form-input font-mono" maxlength="7" required>
                    </div>
                    <p class="form-hint">Default {{ \App\Support\Branding::DEFAULT_PRIMARY }} (hijau tua).</p>
                </div>
                <div>
                    <label class="form-label" for="logo">Logo (PNG/JPG/WEBP, maks. 1 MB)</label>
                    @if ($logoUrl)
                        <div class="mb-2 flex items-center gap-3"><img src="{{ $logoUrl }}" alt="Logo" class="h-10 w-auto rounded border border-gray-200 bg-white p-1">
                            <label class="flex items-center gap-1 text-xs text-red-600"><input type="checkbox" name="remove_logo" value="1"> Hapus logo</label></div>
                    @endif
                    <input id="logo" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5">
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Tiket & Lampiran</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-form.input name="ticket_number_prefix" label="Prefix Nomor Tiket" :value="$values['ticket_number_prefix']" required maxlength="10" hint="Format: PREFIX-UNIT-YYYYMMDD-NNN. Hanya berlaku untuk tiket baru." />
                <x-form.input name="auto_close_days" label="Auto-close Resolved (hari)" type="number" min="0" max="60" :value="$values['auto_close_days']" hint="0 = nonaktif" />
                <x-form.input name="upload_max_kb" label="Maks. ukuran lampiran (KB)" type="number" min="100" max="20480" :value="$values['upload_max_kb']" hint="Juga dibatasi upload_max_filesize PHP hosting" />
                <x-form.input name="upload_max_files" label="Maks. lampiran per kiriman" type="number" min="1" max="10" :value="$values['upload_max_files']" />
            </div>
        </section>

        <section class="card p-5 sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Fitur</h2>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                <div>
                    <x-form.checkbox name="kb_public_enabled" label="Pusat Bantuan untuk User (artikel KB Publik)" :checked="$values['kb_public_enabled']" />
                </div>
                <div class="space-y-3">
                    <x-form.checkbox name="ai_enabled" label="Asisten AI (opsional)" :checked="$values['ai_enabled']" />
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg bg-gray-50 p-3 text-xs">
                        <dt class="text-gray-500">Provider</dt><dd>{{ $ai['provider'] }}</dd>
                        <dt class="text-gray-500">Model</dt><dd>{{ $ai['model'] ?? '—' }}</dd>
                        <dt class="text-gray-500">API key (.env)</dt><dd>{{ $ai['key_configured'] ? 'Terkonfigurasi' : 'Belum diisi' }}</dd>
                        <dt class="text-gray-500">Status</dt><dd @class(['font-medium', 'text-green-700' => ! $ai['reason'], 'text-amber-700' => $ai['reason']])>{{ $ai['reason'] ?? 'Siap digunakan' }}</dd>
                        <dt class="text-gray-500">Hari ini</dt><dd>{{ (int) $ai['today']->total }} request ({{ (int) $ai['today']->failed }} gagal) · {{ number_format((int) $ai['today']->input_tokens + (int) $ai['today']->output_tokens, 0, ',', '.') }} token</dd>
                        <dt class="text-gray-500">30 hari</dt><dd>{{ (int) $ai['month']->total }} request · {{ number_format((int) $ai['month']->input_tokens + (int) $ai['month']->output_tokens, 0, ',', '.') }} token</dd>
                        <dt class="text-gray-500">Batas</dt><dd>{{ $ai['limits']['per_user_hour'] }}/user/jam · {{ $ai['limits']['daily'] }}/hari</dd>
                    </dl>
                    <p class="text-xs text-gray-500">API key & model diatur di .env (lihat AI_INTEGRATION_GUIDE.md).</p>
                </div>
            </div>
        </section>

        <div class="flex justify-end"><button class="btn-primary" type="submit">Simpan Pengaturan</button></div>
    </form>

    <section class="card mt-6 p-5 sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">Template Balasan Cepat</h2>
        <div class="mt-4 space-y-3">
            @foreach ($templates as $template)
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 p-3 sm:flex-row sm:items-start">
                    <form method="POST" action="{{ route('admin.templates.update', $template) }}" class="grid flex-1 gap-2 sm:grid-cols-6">
                        @csrf @method('PUT')
                        <input name="title" value="{{ $template->title }}" class="form-input py-1.5 sm:col-span-1" maxlength="100" required aria-label="Judul">
                        <input name="message" value="{{ $template->message }}" class="form-input py-1.5 sm:col-span-3" maxlength="2000" required aria-label="Isi">
                        <input type="number" name="sort_order" value="{{ $template->sort_order }}" class="form-input py-1.5" min="0" aria-label="Urutan">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-gray-300 text-brand-700"> Aktif</label>
                            <button class="btn-secondary btn-sm" type="submit">Simpan</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.templates.destroy', $template) }}" onsubmit="return confirm('Hapus template?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline" type="submit">Hapus</button></form>
                </div>
            @endforeach
            <form method="POST" action="{{ route('admin.templates.store') }}" class="grid gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-6">
                @csrf
                <input name="title" placeholder="Judul" class="form-input py-1.5" maxlength="100" required aria-label="Judul baru">
                <input name="message" placeholder="Isi template…" class="form-input py-1.5 sm:col-span-3" maxlength="2000" required aria-label="Isi baru">
                <input type="hidden" name="is_active" value="1">
                <input type="number" name="sort_order" value="{{ $templates->max('sort_order') + 1 }}" class="form-input py-1.5" aria-label="Urutan baru">
                <button class="btn-secondary btn-sm" type="submit"><x-icon name="plus" class="h-3.5 w-3.5" /> Tambah</button>
            </form>
        </div>
    </section>

    <section class="card mt-6 p-5 sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">Sistem</h2>
        <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <div class="flex justify-between"><dt class="text-gray-500">Edisi</dt><dd>{{ $system['edition'] === 'almega' ? 'Almega Internal' : 'Produk' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Timezone (.env APP_TIMEZONE)</dt><dd>{{ $system['timezone'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">PHP</dt><dd>{{ $system['php'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Laravel</dt><dd>{{ $system['laravel'] }}</dd></div>
        </dl>
        <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
            <p class="font-medium text-gray-800">Tugas terjadwal (auto-close tiket, antrean)</p>
            <p class="mt-1">Disarankan Cron Job tiap menit: <code class="break-all">{{ $system['cron_hint'] }}</code></p>
            <p class="mt-1">Bila hosting tidak menyediakan Cron, jalankan manual secara berkala:</p>
            <form method="POST" action="{{ route('admin.settings.run-tasks') }}" class="mt-2">@csrf<button class="btn-secondary btn-sm" type="submit">Jalankan Tugas Terjadwal Sekarang</button></form>
        </div>
    </section>
</x-layouts::app>
