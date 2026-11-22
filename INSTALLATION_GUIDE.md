# INSTALLATION GUIDE — AI IT Ticketing & Helpdesk Kit

Panduan instalasi untuk organisasi baru (Edisi Produk) maupun instalasi ulang Edisi Almega.
Untuk detail cPanel (document root, cron, SSL, troubleshooting) lihat [DEPLOYMENT_SHARED_HOSTING.md](DEPLOYMENT_SHARED_HOSTING.md).

## 1. Prasyarat
Lihat [HOSTING_REQUIREMENTS.md](HOSTING_REQUIREMENTS.md). Ringkas: PHP ≥ 8.3 (ext pdo_mysql, mbstring, fileinfo, zip, gd, xml, intl),
MySQL 8 / MariaDB 10.6+, Cron Job (disarankan), HTTPS. Tidak butuh SSH, Composer, Node.js, Redis, atau Supervisor di server.

## 2. Isi Paket
`ai-it-ticketing-helpdesk-kit-<versi>.zip` (Edisi Produk) atau `almega-it-ticketing-<versi>.zip` (Edisi Almega), dibuat dengan
`bash scripts/build-package.sh [product|almega]`. Berisi kode + `vendor/` + `public/build/` dan seluruh dokumentasi.
Verifikasi integritas dengan file `.sha256`.

## 3. Instalasi (cPanel, tanpa SSH)

1. **Upload & extract** zip ke `/home/<user>/helpdesk/` (di LUAR `public_html`).
2. **Document root** subdomain/domain → `/home/<user>/helpdesk/public`.
3. **Database**: cPanel → MySQL Databases → buat database + user, beri ALL PRIVILEGES.
4. **.env**: salin `.env.example` → `.env`, isi minimal:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://helpdesk.perusahaan-anda.com
   APP_KEY=                      # lihat langkah 5
   APP_TIMEZONE=Asia/Jakarta     # atau Asia/Makassar / Asia/Jayapura
   DB_DATABASE=... DB_USERNAME=... DB_PASSWORD="..."
   HELPDESK_EDITION=product
   INITIAL_ADMIN_USERNAME=itadmin
   INITIAL_ADMIN_PASSWORD="Password-Kuat-Anda"
   SESSION_SECURE_COOKIE=true
   ```
   Bungkus nilai yang mengandung `#`, spasi, atau `$` dengan tanda kutip ganda.
5. **APP_KEY**: buat di komputer mana pun yang memiliki PHP: `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`,
   tempel ke `.env`. **Simpan salinannya** (dibutuhkan saat restore).
6. **Migrasi & seed** — pilih salah satu:
   - Cron sekali jalan (cPanel → Cron Jobs, lalu hapus setelah berhasil):
     `cd /home/<user>/helpdesk && /usr/local/bin/php artisan migrate --force --seed >> storage/logs/install.log 2>&1`
   - Atau SSH (bila ada): `php artisan migrate --force --seed`
7. **Optimasi** (cron sekali jalan / SSH): `php artisan config:cache && php artisan route:cache && php artisan view:cache`
8. **Cron scheduler** (permanen, tiap menit): `/usr/local/bin/php /home/<user>/helpdesk/artisan schedule:run >> /dev/null 2>&1`
9. **Permission**: `storage/` dan `bootstrap/cache/` writable (755); `.env` 600.
10. **Login** sebagai IT Admin → wajib ganti password → kosongkan `INITIAL_ADMIN_PASSWORD` di `.env` → `config:cache`.

## 4. Konfigurasi Awal (IT Admin)

| Langkah | Menu |
|---|---|
| Nama aplikasi, organisasi, kontak, warna, logo, prefix tiket, batas lampiran | System Settings |
| Unit/lokasi (Edisi Produk dimulai dengan 1 unit `HQ`) | Unit Management |
| Kategori & subkategori (8 kategori awal) | Category Management |
| Target SLA | SLA Settings |
| Akun staf IT & pengguna | User Management |
| QR per unit untuk ditempel | QR Management |
| Artikel bantuan | Knowledge Base |
| AI (opsional) | `.env` AI_* + System Settings → Asisten AI ([AI_INTEGRATION_GUIDE.md](AI_INTEGRATION_GUIDE.md)) |

## 5. Instalasi Lokal / Development (Windows: Laragon/XAMPP)

```bash
git clone <repo> helpdesk && cd helpdesk
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder          # opsional: user contoh (password: password123)
php artisan db:seed --class=DemoTicketSeeder    # opsional: 150 tiket contoh untuk demo analytics
npm install && npm run build
php artisan serve
```
`DemoSeeder` dan `DemoTicketSeeder` menolak berjalan saat `APP_ENV=production`.

## 6. Verifikasi Setelah Instalasi
1. `https://<domain>/up` → 200
2. `https://<domain>/.env` → 403/404
3. Login IT Admin → dipaksa ganti password
4. Buat user role User → login di HP → buat laporan → tiket muncul di antrean IT & lonceng notifikasi
5. Unduh PDF QR unit → scan → diarahkan ke login lalu form laporan
6. Analytics & Laporan terbuka; Audit Log mencatat aktivitas

## 7. Edisi
| | `HELPDESK_EDITION=almega` | `HELPDESK_EDITION=product` |
|---|---|---|
| Branding default | ALMEGA IT TICKETING SYSTEM / PT Almega Jaya Group | AI IT Ticketing & Helpdesk / Nama Organisasi |
| Unit awal (seeder) | 15 unit Almega | 1 unit `HQ` |
| Fitur | Identik | Identik |

Branding dapat diubah kapan saja di System Settings tanpa mengubah edisi.
