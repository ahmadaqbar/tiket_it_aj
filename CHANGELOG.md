# CHANGELOG — ALMEGA IT TICKETING SYSTEM

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/).

## [Unreleased]

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
