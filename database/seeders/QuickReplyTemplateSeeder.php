<?php

namespace Database\Seeders;

use App\Models\QuickReplyTemplate;
use Illuminate\Database\Seeder;

class QuickReplyTemplateSeeder extends Seeder
{
    public const TEMPLATES = [
        ['Diterima', 'Baik, laporan sudah kami terima.'],
        ['Pengecekan', 'Kami sedang melakukan pengecekan.'],
        ['Penanganan', 'Saat ini kendala sedang dalam penanganan.'],
        ['Butuh informasi', 'Kami membutuhkan informasi tambahan.'],
        ['Selesai ditangani', 'Kendala sudah kami tangani. Mohon dilakukan pengecekan kembali.'],
        ['Ditutup', 'Tiket telah kami tutup karena pekerjaan sudah selesai.'],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $i => [$title, $message]) {
            QuickReplyTemplate::firstOrCreate(['title' => $title], ['message' => $message, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
