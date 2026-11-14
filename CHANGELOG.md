# CHANGELOG — ALMEGA IT TICKETING SYSTEM

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/).

## [Unreleased]

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
