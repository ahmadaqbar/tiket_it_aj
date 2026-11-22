# CHANGELOG — ALMEGA IT TICKETING SYSTEM

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/).

## [Unreleased]

## [0.8.0] — 2026-11-22 — Phase 7: Analytics, Recurring Issues, Reporting
### Ditambahkan
- `AnalyticsService` (agregasi SQL, definisi metrik terdokumentasi), `ReportPeriod` (Hari ini, Minggu ini, Bulan ini, Bulan lalu,
  Tahun ini, Bulan tertentu, Kustom; granularitas harian/mingguan/bulanan otomatis).
- Halaman **Analytics**: ringkasan status, rata-rata respons & penyelesaian, kepatuhan SLA, tren masuk vs selesai, per unit/kategori/
  status/prioritas (Chart.js sebagai entry Vite terpisah), top 5 unit, aktivitas tim IT, isu berulang + saran pencegahan
  + penanda artikel KB, rekomendasi tindak lanjut berbasis aturan data.
- Halaman **Laporan**: Operasional & Manajemen, PDF (dompdf; grafik sebagai bar CSS) dan Excel (openspout; manajemen 7 sheet),
  filter periode/bulan & unit, audit `report.generated`. Catatan internal tidak pernah dimasukkan.
- `DemoTicketSeeder` (khusus development, menolak production) untuk demo & uji performa.

## [0.7.0] — 2026-11-22 — Phase 6: Product Configuration
### Ditambahkan
- **System Settings** (IT Admin): nama aplikasi/singkat, organisasi, tim IT, kontak (email/telepon/alamat), warna utama (CSS variable
  override tanpa build ulang), logo (disimpan privat, disajikan via `/branding/logo` tanpa `storage:link`), prefix nomor tiket,
  auto-close, batas lampiran, pusat bantuan, Asisten AI (+ status provider/model, statistik token, tanpa menampilkan API key).
- Kelola template balasan cepat; tombol **Jalankan Tugas Terjadwal** untuk hosting tanpa Cron (khusus IT Admin, diaudit).
- Kontak IT tampil di halaman login & dashboard user; manifest PWA mengikuti warna & nama.
- Edisi `product` (`HELPDESK_EDITION=product`): default branding generik & 1 unit awal `HQ`. Edisi `almega` tidak berubah.

## [0.6.0] — 2026-11-22 — Phase 3–5: AI Foundation, AI Ticket Assistance, AI Knowledge Base
### Ditambahkan
- `config/ai.php`, tabel `ai_requests` (metadata saja), `AiProvider` (Anthropic, OpenAI-compatible, Fake), `AiManager`, `AiGateway`
  (aktif/nonaktif, rate limit per user & harian, timeout, error handling), `AiContextBuilder` (redaksi, data tak tepercaya), `AiJson`.
- Asisten AI di detail tiket IT: klasifikasi (isi ke form, tidak tersimpan otomatis), troubleshooting dengan rujukan KB tervalidasi,
  ringkasan, draf balasan 4 gaya (masuk ke form, tidak terkirim otomatis).
- Tanya Knowledge Base (`/kb/tanya`) dengan referensi tervalidasi dan penanda "saran umum" bila KB tidak relevan.
- Setting `ai.enabled` (default nonaktif); variabel `.env` AI_*; `AI_INTEGRATION_GUIDE.md`.
### Diperbaiki
- Artikel terkait tiket tidak lagi memunculkan artikel tak relevan dari kata umum judul (pencarian teks hanya sebagai fallback).

## [0.5.0] — 2026-11-22 — Phase 2: Knowledge Base
### Ditambahkan
- Tabel `kb_articles` (FULLTEXT index), `kb_tags`, `kb_article_tag`; kategori memakai tabel `categories` tiket.
- Menu **Knowledge Base** (IT) dan **Bantuan** (User, bila `kb.public_enabled`), pencarian FULLTEXT + fallback LIKE, filter kategori/tag/status/visibilitas.
- Siklus Draft → Published → Archived, audit log, hitung tampilan per sesi.
- Panel "Artikel KB Terkait" dan "Jadikan artikel" di detail tiket IT (tanpa catatan internal).
- `KNOWLEDGE_BASE_GUIDE.md`.

## [0.4.0] — 2026-11-22 — Phase 1d: QR & Notifikasi
### Ditambahkan
- `/qr/{token}` (publik, rate limit 30/menit): unit tersimpan di sesi → login → form laporan dengan unit terisi; sumber tiket `QR`.
  IT yang scan diarahkan ke antrean unit; user dari unit lain mendapat peringatan.
- QR Management (IT Admin): preview SVG, unduh PNG, PDF A5 per unit & semua unit (dompdf), halaman cetak, regenerate token.
- Notifikasi in-app (Laravel database notification, tanpa queue/Redis): tiket baru → semua IT aktif; balasan IT → pelapor;
  balasan pelapor → PIC/semua IT; perubahan status/resolved/closed → pelapor; reopen/konfirmasi → PIC; assignment → PIC.
  Catatan internal tidak memicu notifikasi. Pelaku tidak menerima notifikasinya sendiri.
- Lonceng notifikasi (komponen Livewire `NotificationBell`, `wire:poll.60s`), halaman Notifikasi, tandai semua dibaca.
- PWA: `manifest.webmanifest` (mengikuti branding), ikon 192/512, `sw.js` (hanya halaman offline, tidak meng-cache halaman berlogin).
- `config/helpdesk.php` (edisi `almega`/`product`) dan helper `Branding`; layout tidak lagi hardcode nama Almega
  (tampilan Edisi Almega tidak berubah). `config/almega.php` dipertahankan untuk kompatibilitas.

## [0.3.0] — 2026-11-22 — Phase 1c: IT Management

### Ditambahkan
- Migration aditif `ticket_replies`, `ticket_assignments`, `quick_reply_templates`, FK `ticket_attachments.ticket_reply_id`.
- Shared queue IT `/it/tiket`: filter unit, kategori, subkategori, status, prioritas, tanggal, PIC (termasuk "belum ada PIC"/"tiket saya"),
  nama pelapor, nomor/judul, "melewati SLA"; urutan; paginasi; tombol **Ambil**; export XLSX (openspout, tanpa catatan internal).
- Detail tiket IT: percakapan + catatan internal (ditandai jelas), quick reply yang dapat diedit, ubah status, PIC, klasifikasi, info SLA & durasi.
- Balasan pelapor dengan lampiran; konfirmasi selesai & buka kembali (dengan alasan) pada tiket Resolved.
- `TicketStatusService` (transisi tervalidasi, RESOLVED wajib catatan), `TicketReplyService`, `TicketAssignmentService`, `TicketService::reclassify`.
- Command `tickets:auto-close` (scheduler tiap jam) memakai setting `ticket.auto_close_days`.
- Dashboard IT: kartu status tertaut ke antrean, tiket saya, melewati SLA, tiket baru tanpa PIC.

## [0.2.0] — 2026-11-22 — Phase 0, 1a, 1b (lanjutan menuju AI IT Ticketing & Helpdesk Kit)

### Ditambahkan
- Dokumen audit & productization: `PROJECT_AUDIT.md`, `PROJECT_STATUS.md`, `PRODUCTIZATION_PLAN.md`, `DEVELOPMENT_LOG.md`.
- Core ticketing: migration `categories`, `subcategories`, `sla_policies`, `ticket_sequences`, `tickets`,
  `ticket_attachments`, `ticket_histories` (aditif, tanpa mengubah tabel lama).
- Seeder 8 kategori / 39 subkategori dan SLA default 4 prioritas; setting `ticket.number_prefix` (default `IT`).
- Penomoran tiket `IT-{UNIT}-{YYYYMMDD}-{SEQ}` dengan transaksi + row lock.
- Form Buat Laporan satu halaman (unit otomatis, subkategori dinamis, prioritas opsional, lampiran multi-file).
- Lampiran privat (`storage/app/private`), validasi berdasarkan isi file, unduh lewat Policy.
- Halaman Tiket Saya, Riwayat, Detail Tiket; dashboard user & IT memakai data nyata.
- Category Management (CRUD, nonaktif, larangan hapus bila terpakai) dan SLA Settings.
- `TicketPolicy`, `TicketAttachmentPolicy`, rate limit pembuatan tiket (10/menit/user).

### Diperbaiki
- BUG-01: audit log mencatat perubahan palsu `is_active 0 → false` dan `user.deactivated` palsu saat mengedit
  user/unit nonaktif. `AuditService::logChanges()` kini memakai `Model::getChanges()`.

### Keamanan
- Disk `local` tidak lagi menyajikan file lewat route bawaan (`serve => false`); lampiran hanya lewat controller + Policy.

## [0.1.0] — 2026-11-14 — Sprint 1: Foundation

### Ditambahkan
- Audit environment & dokumen fondasi: `DEVELOPMENT_PLAN.md`, `DATABASE_DESIGN.md`, `HOSTING_REQUIREMENTS.md`,
  `SYSTEM_ARCHITECTURE.md`, `DEPLOYMENT_SHARED_HOSTING.md`, `SECURITY_GUIDE.md`, `BACKUP_RESTORE_GUIDE.md`,
  `USER_GUIDE.md`, `IT_ADMIN_GUIDE.md`, `TESTING_REPORT.md`.
- Laravel 13 + Livewire 4 + Tailwind CSS 4; paket dompdf, openspout, php-qrcode (dipakai di sprint berikutnya).
- Konfigurasi shared hosting: session/cache/queue `database`, timezone `APP_TIMEZONE`, locale `id`, scheduler `queue:work --stop-when-empty`.
- Migration: `roles`, `units`, `users`, `user_units`, `sessions`, `audit_logs`, `settings` (+ cache/jobs bawaan).
- Seeder: 3 role, 15 unit (token QR acak), settings default, akun IT Admin awal dari `.env`; `DemoSeeder` khusus development.
- Autentikasi: login username/email, logout, remember me, rate limiting, paksa ganti password sementara, ganti password, profil.
- Otorisasi: middleware `role`, `active`, `password.changed`; Gate `access-it`, `access-admin`; Policy User/Unit/AuditLog.
- User Management: daftar + filter, tambah/ubah, multi-unit + unit utama, aktif/nonaktif, reset password, anti-lockout admin.
- Unit Management: daftar, tambah/ubah, nonaktif (tanpa hapus), kode unit terkunci setelah dipakai tiket, regenerate token QR (service).
- Audit log append-only + halaman filter.
- Layout responsif (sidebar desktop, drawer & bottom nav mobile), komponen Blade, halaman error Bahasa Indonesia, security headers.
- 76 automated test (Feature + Unit).
