# ALMEGA IT TICKETING SYSTEM

Sistem IT Helpdesk & Ticket Management terpusat untuk **PT Almega Jaya Group** (15 unit operasional).
Production: **https://it.almegajaya.com** (shared hosting).

> **Status:** Foundation + Core Ticketing (pembuatan laporan) selesai. Antrean IT, balasan, dan status menyusul (Phase 1c).
> Proyek sedang dikembangkan menjadi **AI IT Ticketing & Helpdesk Kit** — lihat [PROJECT_STATUS.md](PROJECT_STATUS.md),
> [PROJECT_AUDIT.md](PROJECT_AUDIT.md), dan [PRODUCTIZATION_PLAN.md](PRODUCTIZATION_PLAN.md).

## Stack

Laravel 13 · PHP ≥ 8.3 · MySQL 8 / MariaDB 10.6+ · Blade + Livewire 4 + Alpine.js · Tailwind CSS 4 ·
dompdf · openspout · chillerlan/php-qrcode. Tanpa Redis, Supervisor, Docker, atau Node.js di server.

## Fitur yang Sudah Berfungsi (Sprint 1)

- Login dengan **username atau email**, logout, "ingat saya", rate limiting (5 percobaan/menit per akun+IP, 20/menit per IP)
- Wajib ganti password sementara saat login pertama / setelah reset admin
- Ganti password (memutus sesi di perangkat lain), profil (nama, email, HP)
- 3 level akses **USER / IT / IT_ADMIN**: middleware `role:`, Gate `access-it` / `access-admin`, Policy per model
- Manajemen user (multi-unit + unit utama, aktif/nonaktif, reset password, proteksi admin terakhir)
- Manajemen unit (15 unit via seeder, token QR aman per unit, kode terkunci setelah dipakai tiket, tidak bisa dihapus)
- Audit log append-only (login, logout, login gagal, user, unit, password) + halaman filter khusus IT Admin
- Layout responsif: sidebar desktop, drawer + bottom navigation mobile, Bahasa Indonesia

**Core Ticketing (Phase 1b):** form Buat Laporan satu halaman, nomor tiket otomatis `IT-{UNIT}-{YYYYMMDD}-{SEQ}`,
lampiran privat, Tiket Saya/Riwayat/Detail, dashboard dengan data nyata, Category Management, SLA Settings.

Belum tersedia: antrean & respons IT, status, QR flow, notifikasi, analytics, laporan, Knowledge Base, AI.

## Setup Development (Windows — Laragon/XAMPP)

Prasyarat: PHP 8.3 (ext: pdo_mysql, mbstring, fileinfo, zip, gd, intl, xml), Composer 2, Node.js 20+, MySQL 8 / MariaDB 10.6+.

```bash
git clone <repo> almega-it && cd almega-it
composer install
copy .env.example .env        # Linux/macOS: cp .env.example .env
php artisan key:generate
```

Buat database `almega_it_ticketing` (utf8mb4_unicode_ci), isi `DB_*` dan `INITIAL_ADMIN_*` di `.env`, lalu:

```bash
php artisan migrate --seed                  # master data + 1 akun IT Admin
php artisan db:seed --class=DemoSeeder      # OPSIONAL, lokal saja: user contoh (password: password123)
npm install
npm run build                               # atau: npm run dev
php artisan serve
```

Buka http://localhost:8000 dan login dengan akun IT Admin dari `.env`.

> Bungkus nilai `.env` yang mengandung `#`, spasi, atau `$` dengan tanda kutip ganda.

## Testing

Test memakai database MySQL/MariaDB terpisah `almega_it_ticketing_test` (lihat `phpunit.xml`).

```bash
php artisan test
```

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md) | Audit, stack, rencana & status sprint |
| [DATABASE_DESIGN.md](DATABASE_DESIGN.md) | Skema, relasi, penomoran tiket, aturan status |
| [SYSTEM_ARCHITECTURE.md](SYSTEM_ARCHITECTURE.md) | Arsitektur & alur request |
| [HOSTING_REQUIREMENTS.md](HOSTING_REQUIREMENTS.md) | Kebutuhan shared hosting |
| [DEPLOYMENT_SHARED_HOSTING.md](DEPLOYMENT_SHARED_HOSTING.md) | Langkah deployment, cron, troubleshooting |
| [SECURITY_GUIDE.md](SECURITY_GUIDE.md) | Kontrol keamanan |
| [BACKUP_RESTORE_GUIDE.md](BACKUP_RESTORE_GUIDE.md) | Prosedur backup & restore |
| [USER_GUIDE.md](USER_GUIDE.md) · [IT_ADMIN_GUIDE.md](IT_ADMIN_GUIDE.md) | Panduan pengguna |
| [TESTING_REPORT.md](TESTING_REPORT.md) · [CHANGELOG.md](CHANGELOG.md) | Hasil uji & riwayat perubahan |
