# AI IT TICKETING & HELPDESK KIT — Fitur Produk

Helpdesk & ticketing IT self-hosted untuk bisnis multi-unit (hotel, resort, restoran, sekolah, kantor), berjalan di **shared hosting PHP**.
Antarmuka Bahasa Indonesia, mobile-first. Daftar ini hanya memuat fitur yang sudah diimplementasikan & diuji (lihat TESTING_REPORT.md).

## Untuk Pengguna (staf unit)
- Lapor kendala dari HP dalam satu halaman, dengan foto/dokumen; nomor tiket otomatis (mis. `IT-HQ-20261122-001`)
- **QR Code per unit**: scan → login → unit langsung terisi
- Tiket Saya, Riwayat, percakapan dengan tim IT, konfirmasi selesai / buka kembali
- Notifikasi dalam aplikasi; Pusat Bantuan (artikel Knowledge Base publik); aplikasi dapat dipasang ke layar utama (PWA)

## Untuk Tim IT
- **Antrean bersama** seluruh unit: filter unit/kategori/status/prioritas/tanggal/PIC/pelapor, pencarian, urutan, "melewati SLA", ambil tiket
- Balasan publik & **catatan internal** (tidak pernah terlihat pelapor), lampiran, template balasan cepat
- Status dengan aturan transisi, catatan penyelesaian wajib, auto-close, PIC, klasifikasi ulang dengan SLA otomatis
- Riwayat aktivitas lengkap; export Excel
- **Knowledge Base**: artikel Draft/Published/Archived, internal/publik, tag, pencarian full-text, artikel terkait tiket, "jadikan artikel" dari tiket
- **Analytics**: ringkasan status, waktu respons & penyelesaian, kepatuhan SLA, tren, per unit/kategori/prioritas, aktivitas tim, isu berulang + saran pencegahan
- **Laporan** Operasional & Manajemen (PDF & Excel), termasuk laporan bulanan

## Asisten AI (opsional)
Klasifikasi tiket, langkah troubleshooting dengan rujukan Knowledge Base, ringkasan tiket, draf balasan (4 gaya), tanya-jawab Knowledge Base.
Provider: Anthropic Claude atau endpoint kompatibel OpenAI. Saran saja — tidak ada perubahan atau pengiriman otomatis; data pribadi
diredaksi; catatan internal tidak dikirim untuk fitur yang hasilnya dapat sampai ke pelapor. Sistem berjalan penuh tanpa AI.

## Untuk Administrator
- 3 level akses (User, IT, IT Admin), manajemen user multi-unit, unit, kategori, SLA, QR (PNG/PDF/cetak/regenerate)
- **System Settings**: nama, organisasi, kontak, warna tema, logo, prefix nomor tiket, batas lampiran, auto-close, pusat bantuan, AI
- Audit log append-only; tugas terjadwal manual untuk hosting tanpa Cron

## Teknis
Laravel 13 · PHP 8.3 · MySQL/MariaDB · Blade + Livewire + Alpine + Tailwind · tanpa Redis/Supervisor/Node server/WebSocket ·
lampiran privat · rate limiting · 255 automated test · paket instalasi siap upload.

## Belum Termasuk (versi ini)
WhatsApp/email notification, multi-tenant (beberapa organisasi dalam satu instalasi), lisensi online, manajemen aset, semantic/vector search.
