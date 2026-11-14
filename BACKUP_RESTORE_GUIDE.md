# BACKUP & RESTORE GUIDE — ALMEGA IT TICKETING SYSTEM

## 1. Apa yang Harus Di-backup

| Komponen | Lokasi | Frekuensi |
|---|---|---|
| Database MySQL | `almega_it_ticketing` (nama DB cPanel) | Harian + sebelum setiap update |
| Lampiran tiket (mulai Sprint 2) | `almega-it/storage/app/private/` | Harian (inkremental) |
| File `.env` | `almega-it/.env` | Setiap kali diubah — simpan **terpisah & terenkripsi** (berisi APP_KEY & password DB) |

Kode aplikasi tidak perlu di-backup harian karena tersimpan di Git.

Retensi yang disarankan: 7 harian, 4 mingguan, 12 bulanan. Simpan minimal satu salinan **di luar server hosting**
(Google Drive perusahaan / NAS kantor).

## 2. Backup Database

### 2.1 Via cPanel (tanpa SSH)
- cPanel → **Backup** → *Download a MySQL Database Backup* → pilih database → file `.sql.gz`, **atau**
- phpMyAdmin → pilih DB → **Export** → Custom → format SQL, compression gzip.

### 2.2 Via SSH / Cron (otomatis)
Perintah (sudah diuji pada MariaDB 10.11 — dump → restore menghasilkan jumlah baris identik):
```bash
mysqldump -u DB_USER -p'DB_PASS' --no-tablespaces --single-transaction --routines \
  --default-character-set=utf8mb4 DB_NAME | gzip > ~/backups/almega-db-$(date +\%F).sql.gz
```
Contoh Cron harian 01:30 (cPanel → Cron Jobs; `%` harus di-escape `\%`):
```
30 1 * * * mysqldump -u DB_USER -p'DB_PASS' --no-tablespaces --single-transaction DB_NAME | gzip > /home/<user>/backups/almega-db-$(date +\%F).sql.gz && find /home/<user>/backups -name 'almega-db-*' -mtime +7 -delete
```
> Folder `~/backups` harus berada **di luar** `public_html`. Agar password tidak tampil di daftar proses,
> gunakan file `~/.my.cnf` (permission 600) berisi `[mysqldump] user=... password=...`.

## 3. Backup Lampiran

```bash
tar -czf ~/backups/almega-files-$(date +%F).tar.gz -C ~/almega-it/storage/app private
```
Tanpa SSH: cPanel File Manager → klik kanan `storage/app/private` → **Compress** → download.

## 4. Restore

1. Aktifkan maintenance: `php artisan down` (atau cron sekali jalan).
2. **Backup kondisi saat ini terlebih dahulu** (jangan menimpa tanpa salinan).
3. Restore database:
   - phpMyAdmin → pilih DB → **Import** → file `.sql.gz`, **atau**
   - `gunzip -c almega-db-YYYY-MM-DD.sql.gz | mysql -u DB_USER -p DB_NAME`
4. Restore lampiran: extract arsip ke `storage/app/` (menghasilkan `storage/app/private/...`).
5. Pastikan `.env` memakai **APP_KEY yang sama** dengan saat backup.
6. `php artisan config:cache && php artisan view:clear`
7. `php artisan up`, lalu jalankan smoke test (DEPLOYMENT_SHARED_HOSTING.md §12).

## 5. Uji Restore Berkala

Setiap 3 bulan: restore backup terbaru ke database terpisah (mis. `cpuser_almega_restore`), bandingkan jumlah
baris `users`, `units`, `tickets`, `audit_logs` dengan production, lalu hapus database uji.

Hasil uji terakhir dicatat di [TESTING_REPORT.md](TESTING_REPORT.md).

## 6. Larangan

- Jangan menyimpan file backup di dalam `public_html` atau `almega-it/public`.
- Jangan menjalankan `migrate:fresh`, `migrate:reset`, atau `db:wipe` di production.
- Jangan me-restore ke production tanpa persetujuan dan tanpa backup kondisi terkini.
