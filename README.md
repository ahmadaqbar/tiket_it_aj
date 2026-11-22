# AI IT Ticketing & Helpdesk Kit
### (dikembangkan dari ALMEGA IT TICKETING SYSTEM — PT Almega Jaya Group)

Sistem IT Helpdesk & Ticketing terpusat untuk bisnis multi-unit, dapat berjalan di **shared hosting PHP**.
Satu codebase, dua edisi: **Edisi Almega** (production: https://it.almegajaya.com, 15 unit) dan **Edisi Produk** untuk organisasi lain.

> **Versi:** 1.0.0-rc.1 · **Status:** seluruh fase pengembangan selesai & teruji (255 test).
> Deployment production belum dilakukan dan menunggu persetujuan + data spesifikasi hosting. Lihat [PROJECT_STATUS.md](PROJECT_STATUS.md).

## Fitur Utama
Laporan dari HP + QR per unit · antrean IT bersama · balasan & catatan internal · status/SLA/PIC · notifikasi in-app ·
Knowledge Base · Asisten AI opsional · Analytics & isu berulang · Laporan PDF/Excel · System Settings & branding · audit log.
Rincian: [PRODUCT_FEATURES.md](PRODUCT_FEATURES.md).

## Stack
Laravel 13 · PHP ≥ 8.3 · MySQL 8 / MariaDB 10.6+ · Blade + Livewire 4 + Alpine.js · Tailwind CSS 4 · Chart.js ·
dompdf · openspout · chillerlan/php-qrcode. Tanpa Redis, Supervisor, Docker, WebSocket, atau Node.js di server.

## Mulai Cepat (development)
```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder && php artisan db:seed --class=DemoTicketSeeder   # opsional, data contoh
npm install && npm run build && php artisan serve
```
Test: `php artisan test` (database MySQL/MariaDB `almega_it_ticketing_test`, lihat `phpunit.xml`).
Paket distribusi: `bash scripts/build-package.sh [almega|product]`.

## Dokumentasi
| Topik | Dokumen |
|---|---|
| Status, audit, rencana | [PROJECT_STATUS.md](PROJECT_STATUS.md) · [PROJECT_AUDIT.md](PROJECT_AUDIT.md) · [PRODUCTIZATION_PLAN.md](PRODUCTIZATION_PLAN.md) · [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md) · [DEVELOPMENT_LOG.md](DEVELOPMENT_LOG.md) |
| Instalasi & operasi | [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md) · [HOSTING_REQUIREMENTS.md](HOSTING_REQUIREMENTS.md) · [DEPLOYMENT_SHARED_HOSTING.md](DEPLOYMENT_SHARED_HOSTING.md) · [UPDATE_GUIDE.md](UPDATE_GUIDE.md) · [BACKUP_RESTORE_GUIDE.md](BACKUP_RESTORE_GUIDE.md) |
| Pengguna | [USER_GUIDE.md](USER_GUIDE.md) · [IT_ADMIN_GUIDE.md](IT_ADMIN_GUIDE.md) · [KNOWLEDGE_BASE_GUIDE.md](KNOWLEDGE_BASE_GUIDE.md) · [AI_INTEGRATION_GUIDE.md](AI_INTEGRATION_GUIDE.md) |
| Teknis | [SYSTEM_ARCHITECTURE.md](SYSTEM_ARCHITECTURE.md) · [DATABASE_DESIGN.md](DATABASE_DESIGN.md) · [SECURITY_GUIDE.md](SECURITY_GUIDE.md) · [TESTING_REPORT.md](TESTING_REPORT.md) · [CHANGELOG.md](CHANGELOG.md) |
