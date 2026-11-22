<x-layouts::app title="Reports">
    <x-page-header title="Laporan" subtitle="Unduh laporan Operasional atau Manajemen dalam format PDF atau Excel. Catatan internal tidak pernah dimasukkan ke laporan." />

    <form method="GET" action="{{ route('it.reports.download') }}" class="card max-w-4xl space-y-5 p-5 sm:p-6">
        <fieldset>
            <legend class="form-label">Jenis Laporan</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                    <input type="radio" name="type" value="operational" checked class="mt-1 text-brand-700">
                    <span><span class="font-medium text-gray-900">Operasional</span><span class="block text-xs text-gray-500">Daftar tiket: nomor, unit, pelapor, kategori, deskripsi, respons IT, status, tanggal, durasi penanganan.</span></span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                    <input type="radio" name="type" value="management" class="mt-1 text-brand-700">
                    <span><span class="font-medium text-gray-900">Manajemen</span><span class="block text-xs text-gray-500">Ringkasan, statistik unit & kategori, tren, aktivitas IT, waktu respons & penyelesaian, isu berulang, rekomendasi.</span></span>
                </label>
            </div>
        </fieldset>

        <x-period-filter :units="$units" :filters="['period' => 'month', 'month' => $lastMonth]" default="month" />

        <div class="flex flex-col gap-2 border-t border-gray-100 pt-5 sm:flex-row">
            <button name="format" value="pdf" class="btn-primary" type="submit"><x-icon name="document" class="h-4 w-4" /> Unduh PDF</button>
            <button name="format" value="xlsx" class="btn-secondary" type="submit"><x-icon name="document" class="h-4 w-4" /> Unduh Excel</button>
        </div>
        <p class="text-xs text-gray-500">Laporan bulanan: pilih Periode "Bulan Tertentu". PDF operasional dibatasi {{ \App\Services\ReportService::PDF_MAX_ROWS }} tiket — gunakan Excel untuk data lengkap.</p>
        @if ($errors->any())<p class="form-error">{{ $errors->first() }}</p>@endif
    </form>
</x-layouts::app>
