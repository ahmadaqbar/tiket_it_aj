# DEPLOYMENT KE SHARED HOSTING — https://it.almegajaya.com

> ⚠️ **Deployment production hanya dilakukan dengan persetujuan eksplisit** (Sprint 8).
> Dokumen ini disiapkan sejak Sprint 1 dan akan diverifikasi ulang terhadap spesifikasi hosting aktual
> (lihat checklist di [HOSTING_REQUIREMENTS.md](HOSTING_REQUIREMENTS.md)).

## 1. Struktur Direktori Target (cPanel)

```
/home/<cpanel_user>/
├── almega-it/                 ← seluruh aplikasi Laravel (TIDAK dapat diakses publik)
│   ├── app/ bootstrap/ config/ database/ lang/ resources/ routes/ storage/ vendor/
│   ├── .env                   ← rahasia, permission 600
│   └── public/                ← SATU-SATUNYA folder publik
└── public_html/               ← situs utama almegajaya.com (tidak disentuh)
```

## 2. Metode A (Disarankan): Document Root Subdomain → `almega-it/public`

1. cPanel → **Domains / Subdomains** → buat `it.almegajaya.com`, isi Document Root `almega-it/public`.
2. Jika subdomain sudah ada, ubah document root-nya ke `almega-it/public` (atau minta ke penyedia hosting).
3. `.env`, `vendor/`, dan `storage/` otomatis berada di luar web root.

## 3. Metode B (Alternatif): Document Root Tidak Dapat Diubah

Jika subdomain terkunci ke `/home/<user>/public_html/it` (contoh):
1. Upload aplikasi ke `/home/<user>/almega-it/` (di luar `public_html`).
2. Salin **isi** `almega-it/public/` ke `public_html/it/`.
3. Edit `public_html/it/index.php`:
   ```php
   require __DIR__.'/../../almega-it/vendor/autoload.php';
   $app = require_once __DIR__.'/../../almega-it/bootstrap/app.php';
   ```
   (sesuaikan path pada baris `maintenance.php` juga)
4. Setiap kali `npm run build`, salin ulang `public/build/` ke `public_html/it/build/`.
5. **Jangan pernah** meletakkan seluruh folder Laravel di dalam `public_html`.

## 4. Persiapan Paket (di komputer lokal / Windows)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Paket yang di-upload: seluruh project **termasuk** `vendor/` dan `public/build/`, **tanpa** `node_modules/`, `.git/`, `tests/`, `.env`, `storage/logs/*`.
Upload via File Manager (zip → extract) atau FTP/SFTP.

## 5. Database

1. cPanel → **MySQL Databases**: buat database (mis. `cpuser_almega_it`), user, dan beri **ALL PRIVILEGES**.
2. Isi `.env` production:
   ```
   APP_NAME="ALMEGA IT TICKETING SYSTEM"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://it.almegajaya.com
   APP_TIMEZONE=Asia/Jakarta
   DB_DATABASE=cpuser_almega_it
   DB_USERNAME=cpuser_almega
   DB_PASSWORD="..."
   SESSION_SECURE_COOKIE=true
   LOG_LEVEL=warning
   INITIAL_ADMIN_USERNAME=itadmin
   INITIAL_ADMIN_PASSWORD="<password-kuat>"
   ```
3. Generate `APP_KEY` **sekali** (lokal: `php artisan key:generate --show`) lalu tempel ke `.env`. Simpan salinannya di tempat aman — kehilangan APP_KEY = sesi & data terenkripsi tidak dapat dibaca.
4. Migrasi:
   - **Dengan SSH:** `php artisan migrate --force && php artisan db:seed --force`
   - **Tanpa SSH:** jalankan migration + seeder di database lokal kosong, export dengan
     `mysqldump --no-tablespaces --routines almega_it_ticketing > init.sql`, lalu import via phpMyAdmin.
     (Akun IT Admin awal ikut ter-export; segera ganti passwordnya setelah login.)
5. **Jangan** menjalankan `DemoSeeder` di production (seeder menolak bila `APP_ENV=production`).

## 6. Permission

```
almega-it/storage            755 (folder) / 644 (file) — harus writable oleh PHP
almega-it/bootstrap/cache    755
almega-it/.env               600
```
Attachment disimpan di `storage/app/private` (bukan `public`) sehingga **tidak perlu** `storage:link`.

## 7. Optimasi (setelah `.env` final)

Dengan SSH:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
Tanpa SSH: tambahkan Cron Job sekali jalan (lihat §8) berisi perintah di atas, lalu hapus cron tersebut.
**Jangan** menjalankan `config:cache` di komputer lokal lalu meng-upload `bootstrap/cache/config.php` — path absolut berbeda.

Setiap kali `.env` diubah: jalankan ulang `php artisan config:cache` (atau `config:clear`).

## 8. Cron Job (Scheduler)

cPanel → **Cron Jobs** → Common Settings: *Once Per Minute* (`* * * * *`):
```
/usr/local/bin/php /home/<cpanel_user>/almega-it/artisan schedule:run >> /dev/null 2>&1
```
Cek path PHP di cPanel (mis. `/opt/cpanel/ea-php83/root/usr/bin/php` atau `/usr/local/bin/ea-php83`).

Scheduler menjalankan (lihat `routes/console.php`):
| Jadwal | Perintah | Fungsi |
|---|---|---|
| tiap menit | `queue:work --stop-when-empty --max-time=50` | Memproses antrean database lalu berhenti (tanpa worker permanen) |
| harian | `queue:prune-failed --hours=720` | Membersihkan job gagal > 30 hari |
| (Sprint 3–4) | auto-close tiket Resolved, notifikasi | ditambahkan saat modul tersedia |

**Jika Cron tidak tersedia:** fitur inti (login, tiket, balasan, notifikasi in-app) tetap berjalan karena tidak bergantung pada antrean.
Tugas terjadwal (auto-close tiket) akan disediakan sebagai tombol **"Jalankan Tugas Terjadwal"** di halaman IT Admin (Sprint 4) — dijalankan manual oleh admin, tidak dipicu oleh request user biasa.

## 9. SSL / HTTPS

1. cPanel → **SSL/TLS Status** → jalankan **AutoSSL** untuk `it.almegajaya.com`.
2. Aplikasi memaksa skema `https` di production (`URL::forceScheme`) dan mengirim header HSTS untuk request HTTPS.
3. Opsional, redirect HTTP→HTTPS di `public/.htaccess` (setelah SSL aktif):
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

## 10. PHP Settings (MultiPHP INI Editor)

```
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 120
memory_limit = 256M
```

## 11. Deployment Checklist

- [ ] Spesifikasi hosting diverifikasi (HOSTING_REQUIREMENTS.md §7)
- [ ] Backup database & file production (jika update)
- [ ] `composer install --no-dev -o` dan `npm run build` lokal sukses
- [ ] `php artisan test` lulus di lokal
- [ ] Upload paket; `.env` production terisi, `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Document root → `almega-it/public`
- [ ] Migrasi database sukses
- [ ] Permission `storage/` & `bootstrap/cache/`
- [ ] `config:cache`, `route:cache`, `view:cache`
- [ ] Cron `schedule:run` aktif
- [ ] AutoSSL aktif, HTTPS berfungsi
- [ ] Smoke test (§12)
- [ ] Ganti password IT Admin awal

## 12. Smoke Test Setelah Deploy

1. `https://it.almegajaya.com/up` → 200
2. `https://it.almegajaya.com/.env` → **403/404** (tidak boleh menampilkan isi)
3. Login IT Admin → dipaksa ganti password → dashboard IT
4. Buat user role User → login di HP → dashboard user tampil
5. User mengakses `/admin/users` → 403
6. Audit Log mencatat login, pembuatan user
7. 6× password salah → pesan "Terlalu banyak percobaan"

## 13. Update Versi (Rilis Berikutnya)

1. Backup database + `storage/app` (BACKUP_RESTORE_GUIDE.md)
2. `php artisan down` (atau cron sekali jalan)
3. Upload file baru (kecuali `.env` dan `storage/`)
4. `php artisan migrate --force` → `config:cache` → `route:cache` → `view:cache`
5. `php artisan up`

## 14. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| HTTP 500 tanpa pesan | Permission `storage/`, APP_KEY kosong, ekstensi PHP kurang | Cek `storage/logs/laravel-*.log`, error_log cPanel; set permission 755 |
| Tampilan tanpa CSS | `public/build/` tidak ter-upload / Metode B belum disalin | Jalankan `npm run build` lokal, upload `public/build` |
| `419 Page Expired` | Cookie secure di HTTP, APP_URL salah, sesi kedaluwarsa | Pastikan HTTPS aktif & `SESSION_SECURE_COOKIE=true` hanya bila HTTPS |
| Login selalu gagal setelah seed | Karakter `#` di `.env` tanpa kutip | Bungkus nilai dengan `"..."`, `config:clear`, seed ulang/reset |
| Perubahan `.env` tidak berlaku | Config ter-cache | `php artisan config:clear` lalu `config:cache` |
| `SQLSTATE[HY000] [1045]` | Kredensial DB salah / user belum di-assign ke DB | Periksa cPanel MySQL Databases |
| `could not find driver` | `pdo_mysql` tidak aktif | Aktifkan via Select PHP Version → Extensions |
| Upload gagal untuk file besar | `upload_max_filesize` kecil | Naikkan via MultiPHP INI Editor |
| Antrean/tugas terjadwal tidak jalan | Cron belum dibuat / path PHP salah | Cek Cron Jobs & email output cron |
| `Class "…" not found` setelah upload | `vendor/` tidak lengkap / autoload lama | Upload ulang `vendor/` hasil `composer install --no-dev -o` |
