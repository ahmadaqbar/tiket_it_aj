# UPDATE GUIDE

Prosedur memperbarui instalasi ke versi baru tanpa kehilangan data.

## Prinsip
- Semua migration bersifat **aditif** (menambah tabel/kolom/index). Tidak ada `migrate:fresh`/`reset` di production.
- Data konfigurasi (setting, unit, kategori, SLA, template) tidak ditimpa seeder (`firstOrCreate`).
- Baca [CHANGELOG.md](CHANGELOG.md) sebelum update.

## Langkah
1. **Backup** database + `storage/app/private` + `.env` ([BACKUP_RESTORE_GUIDE.md](BACKUP_RESTORE_GUIDE.md)).
2. **Maintenance mode**: `php artisan down` (cron sekali jalan / SSH).
3. **Upload** isi paket versi baru, **kecuali** `.env` dan folder `storage/`. Timpa `app/`, `bootstrap/` (kecuali `bootstrap/cache/*.php`),
   `config/`, `database/`, `lang/`, `public/` (termasuk `public/build/`), `resources/`, `routes/`, `vendor/`, file root.
4. Hapus cache lama: `php artisan optimize:clear`
5. **Migrasi**: `php artisan migrate --force`
6. **Seeder master (aman diulang)**: `php artisan db:seed --force` — hanya menambah data master yang belum ada.
7. **Optimasi**: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
8. `php artisan up`, lalu jalankan smoke test (INSTALLATION_GUIDE §6).

## Update dari versi Sprint 1 (0.1.0) ke 1.0.0
Migration baru yang akan dijalankan: categories/subcategories, sla_policies, ticket_sequences, tickets, ticket_attachments,
ticket_histories, ticket_replies (+FK lampiran), ticket_assignments, quick_reply_templates, notifications, kb_articles/kb_tags,
ai_requests. Semua aditif; data user, unit, audit log tetap.

## Rollback
Restore backup langkah 1 (database + file). Jangan menjalankan `migrate:rollback` pada production yang sudah berisi data baru.
