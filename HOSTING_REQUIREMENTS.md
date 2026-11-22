# HOSTING REQUIREMENTS — ALMEGA IT TICKETING SYSTEM

Target production: **https://it.almegajaya.com** (shared hosting)

> **Status informasi hosting: BELUM DIVERIFIKASI.**
> Spesifikasi aktual shared hosting PT Almega Jaya Group belum tersedia saat dokumen ini dibuat.
> Seluruh nilai di bawah adalah **asumsi konservatif** yang wajib dicek ulang di panel hosting
> (cPanel/DirectAdmin/Plesk) sebelum deployment. Kolom "Hasil Cek" diisi saat verifikasi.

## 1. Ringkasan Kebutuhan

| # | Kebutuhan | Minimum | Rekomendasi | Asumsi bila belum diketahui | Hasil Cek |
|---|---|---|---|---|---|
| 1 | Versi PHP | **8.3** | 8.3 / 8.4 | Tersedia via "Select PHP Version" (CloudLinux) | ☐ |
| 2 | Versi MySQL | MySQL **8.0** atau MariaDB **10.6** | MySQL 8.0+ / MariaDB 10.11 | MariaDB 10.6+ | ☐ |
| 3 | PHP extensions | lihat §2 | — | Ekstensi standar cPanel aktif | ☐ |
| 4 | SSH | Opsional | Disarankan | **Tidak tersedia** (rencana deployment tanpa SSH disiapkan) | ☐ |
| 5 | Composer | Opsional | Disarankan | **Tidak tersedia** → `vendor/` di-build lokal lalu di-upload | ☐ |
| 6 | Cron Job | Wajib untuk fitur terjadwal | 1 cron per menit | Tersedia via cPanel "Cron Jobs" (umum di shared hosting) | ☐ |
| 7 | Document root subdomain ke folder `public` | Opsional | Disarankan | Dapat diatur untuk subdomain di cPanel | ☐ |
| 8 | Batas upload (`upload_max_filesize` / `post_max_size`) | 10M / 12M | 20M / 25M | Default host sering 2M–8M → **harus dinaikkan** via MultiPHP INI Editor | ☐ |
| 9 | Execution time (`max_execution_time`) | 60 detik | 120 detik | 30 detik → laporan dibatasi per periode & menggunakan streaming | ☐ |
| 10 | Kapasitas storage | 2 GB | 5 GB+ | 2 GB (aplikasi ±150 MB, sisanya attachment & backup) | ☐ |
| 11 | `memory_limit` | 128M | 256M | 128M | ☐ |
| 12 | SSL / HTTPS | Wajib | AutoSSL / Let's Encrypt | Tersedia (AutoSSL cPanel) | ☐ |
| 13 | Koneksi HTTPS keluar (hanya bila AI diaktifkan) | Port 443 ke host provider AI | — | Diizinkan (umum di shared hosting) | ☐ |
| 14 | Fungsi PHP `set_time_limit`, `proc_open` tidak wajib | — | — | Laporan tetap dibatasi ukuran agar < 30 dtk | ☐ |

## 2. PHP Extensions yang Dibutuhkan

| Extension | Digunakan oleh | Wajib |
|---|---|---|
| `ctype`, `filter`, `hash`, `mbstring`, `openssl`, `tokenizer`, `session`, `json` | Laravel core | Ya |
| `pdo`, `pdo_mysql` | Database MySQL | Ya |
| `fileinfo` | Validasi MIME upload, QR code | Ya |
| `dom`, `xml`, `xmlreader`, `xmlwriter`, `libxml` | Laravel, dompdf, openspout | Ya |
| `zip` | Export Excel (XLSX = arsip ZIP) | Ya |
| `gd` | QR Code PNG, gambar di PDF | Ya |
| `curl` | HTTP client Laravel | Ya |
| `intl` | Format angka/tanggal Bahasa Indonesia | Disarankan |
| `bcmath` | Tidak dibutuhkan | — |
| `redis`, `imagick`, `pcntl`, `sockets` | **Tidak dibutuhkan** | — |

MySQL/MariaDB harus mendukung index **FULLTEXT pada InnoDB** (MySQL ≥ 5.6, MariaDB ≥ 10.0) — dipakai pencarian Knowledge Base.

Cara cek cepat di hosting: buat file sementara `phpinfo.php` → buka → **hapus segera** setelah dicek.

## 3. Hal yang TIDAK Dibutuhkan (sesuai batasan shared hosting)

- Docker / Kubernetes
- Redis / Memcached (cache, session, dan queue memakai **database / file**)
- Supervisor / long-running queue worker
- Node.js di server (asset di-build **lokal** dengan `npm run build`, hasilnya `public/build/` di-upload)
- WebSocket server (notifikasi in-app memakai polling ringan Livewire)
- Root access / konfigurasi Nginx

## 4. Keputusan Versi

| Komponen | Versi terpasang (dikunci di composer.lock / package-lock.json) | Alasan |
|---|---|---|
| Laravel | **13.x** | Rilis stabil terbaru, dukungan keamanan lebih panjang. Laravel 12 (PHP 8.2) dukungan keamanannya berakhir Feb 2027 |
| PHP | **≥ 8.3** | Kebutuhan Laravel 13 & openspout 4 |
| Livewire | **4.x** | Kompatibel Laravel 13, Alpine.js sudah termasuk |
| Tailwind CSS | 4.x (build lokal) | Tidak butuh Node di server |
| Chart.js | 4.x (di-bundle via Vite) | Tidak bergantung CDN |
| PDF | `barryvdh/laravel-dompdf` 3.x | Pure PHP, tanpa binary eksternal (tidak memakai wkhtmltopdf/Chrome) |
| Excel | `openspout/openspout` 4.x | Pure PHP, streaming → hemat memori shared hosting |
| QR Code | `chillerlan/php-qrcode` 5.x | Pure PHP, output PNG via GD & SVG |

**Fallback bila hosting hanya menyediakan PHP 8.2:** turunkan ke Laravel 12 + openspout 4.2x (PHP 8.2),
lalu jalankan ulang seluruh test. Hal ini harus diputuskan sebelum Sprint 8.

## 5. Konfigurasi Runtime Shared-Hosting-Friendly (`.env`)

```
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database   # diproses oleh scheduler via cron (queue:work --stop-when-empty)
FILESYSTEM_DISK=local       # attachment di storage/app/private (tidak bisa diakses publik)
```

## 6. Environment Development (hasil audit 2026-11-14)

| Item | Hasil |
|---|---|
| OS dev (audit) | Linux container (cloud). Development tim di **Windows** (Laragon/XAMPP) |
| PHP | 8.3.6 CLI — semua extension wajib tersedia |
| Composer | 2.8.12 |
| Node.js / npm | 22.x / 10.9 (hanya untuk build asset) |
| Database | MariaDB 10.11 (diinstal untuk pengujian; MySQL 8 setara) |

Rekomendasi Windows: **Laragon** (PHP 8.3 + MySQL 8 + Composer + Node) atau XAMPP + Composer + Node terpisah.

## 7. Checklist Verifikasi ke Penyedia Hosting

1. ☐ PHP 8.3 dapat dipilih untuk subdomain `it.almegajaya.com`
2. ☐ Extension `pdo_mysql, fileinfo, zip, gd, intl, xml, mbstring` aktif
3. ☐ Subdomain dapat diarahkan ke `/home/<user>/almega-it/public`
4. ☐ Cron Job tersedia dengan interval tiap menit (atau minimal tiap 5 menit)
5. ☐ SSH tersedia? (Jika ya → Composer & artisan dapat dijalankan langsung)
6. ☐ `upload_max_filesize` dapat dinaikkan ke ≥ 10M
7. ☐ Kuota storage & inode
8. ☐ Jadwal backup otomatis dari pihak hosting
9. ☐ Database MySQL: versi, batas ukuran, akses phpMyAdmin
10. ☐ AutoSSL aktif untuk subdomain

## 8. Ukuran & Kebutuhan Disk (hasil Phase 10)
- Paket instalasi (`scripts/build-package.sh`): ±48 MB zip; ±150 MB setelah diekstrak (mayoritas `vendor/`).
- Database: ±445 KB (gzip) untuk ±5.800 tiket + 2.400 notifikasi pada uji; tumbuh sesuai volume.
- Lampiran: sesuai pemakaian (batas default 5 MB × 5 file per kiriman).

## 9. Hasil Uji Performa Acuan (lingkungan dev)
Antrean/dashboard 50–63 ms, analytics ≤ 114 ms pada ±5.800 tiket; export XLSX 5.800 baris 1,4 dtk; PDF operasional ±7 dtk / 500 baris.
Shared hosting umumnya lebih lambat — gunakan angka ini sebagai baseline saat smoke test.
