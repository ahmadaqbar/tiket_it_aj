# TESTING REPORT — ALMEGA IT TICKETING SYSTEM

## Sprint 1 — Foundation (2026-11-14)

**Lingkungan uji:** PHP 8.3.6 · Laravel 13 · PHPUnit 12.5 · MariaDB 10.11 (database `almega_it_ticketing_test`, `RefreshDatabase`)

**Hasil: 76 test · 215 assertion · 0 gagal** (`php artisan test`, ±7 detik)

### Cakupan terhadap Persyaratan Testing (§12 master prompt)

| # | Persyaratan | Status | Test |
|---|---|---|---|
| 1 | User authentication | ✅ | `AuthenticationTest` (12), `PasswordAndProfileTest` (8) |
| 2 | Role authorization | ✅ | `RoleAccessTest` (25 termasuk data provider), `UserManagementTest::*cannot*` |
| 3–14 | Tiket, penomoran, privasi, queue, reply, internal note, status, history, attachment, QR flow | ⏳ | Sprint 2–4 |
| 15–17 | Analytics, PDF, Excel | ⏳ | Sprint 5–6 |
| 18 | Audit logging | ✅ (fondasi) | `AuditLogTest` (4) + assertion audit di test auth/user/unit |

### Rincian Suite

| Suite | Jumlah | Hal yang diverifikasi |
|---|---|---|
| Feature/Auth/AuthenticationTest | 12 | Login username & email; redirect per role; password salah → error generik + audit `auth.login_failed`; akun nonaktif ditolak; **rate limit** setelah 5 gagal; logout invalidasi sesi + audit; audit menyimpan IP & user agent; user yang dinonaktifkan ter-logout di request berikutnya; tidak ada `/register` |
| Feature/Auth/PasswordAndProfileTest | 8 | Paksa ganti password sementara; ganti password (hash, flag, audit); validasi password lama & kekuatan; password tersimpan ter-hash; user tidak bisa mengubah username/role lewat profil; **ganti password menghapus sesi lain tapi mempertahankan sesi aktif**; reset admin menghapus semua sesi user |
| Feature/Authorization/RoleAccessTest | 25 | 5 halaman admin × 3 role (USER 403, IT 403, IT_ADMIN 200); dashboard per role; POST/PUT langsung ke endpoint admin oleh USER/IT → 403 dan data tidak berubah; Gate; menu admin hanya untuk admin |
| Feature/Admin/UserManagementTest | 15 | Filter daftar; buat user multi-unit + unit utama; audit tanpa password; role USER wajib unit; IT tanpa unit; validasi username unik & password lemah; unit utama harus termasuk pilihan; ubah user & unit (audit `user.updated` + `user.units_changed`); password tidak bisa diubah lewat form edit; nonaktifkan + audit; admin tidak bisa demote diri; admin aktif terakhir dilindungi; reset password; admin tidak reset password sendiri; user tidak bisa dihapus (405) |
| Feature/Admin/UnitManagementTest | 10 | 15 unit dari seeder (urutan & kode); token QR unik 48 hex; seeder idempotent (token tidak berubah); daftar unit tidak membocorkan token; buat unit (kode kapital, token, audit); validasi kode; ubah/nonaktifkan + audit nilai lama/baru; unit tidak bisa dihapus; regenerate token + audit |
| Feature/AuditLogTest | 4 | Append-only (update & delete ditolak); nilai sensitif dibuang; filter halaman audit; tidak ada route hapus |
| Feature/DatabaseSeederTest | 3 | Seeder production hanya master data + 1 admin (idempotent, wajib ganti password); `DemoSeeder` menolak production; tepat 3 role |
| Unit/RoleCodeTest | 1 | Hirarki role & flag staf IT |

### Pengujian Manual / Browser (Playwright, Chromium)

| Skenario | Hasil |
|---|---|
| Login IT Admin awal → dipaksa ganti password → Dashboard IT | ✅ |
| Buat user multi-unit (DR + O2D, utama DR) via form, Alpine menampilkan pilihan "Utama" hanya pada unit tercentang | ✅ |
| Halaman Unit & Audit Log tampil, audit mencatat login/ganti password/buat user | ✅ |
| Login user di emulasi iPhone 13 → dashboard user, bottom navigation, drawer menu | ✅ |
| User membuka `/admin/users` → 403 | ✅ |
| Console error JavaScript | Tidak ada |

### Uji Backup & Restore (manual)

`mysqldump --single-transaction` → gzip → restore ke database baru: jumlah baris `users` (19), `units` (15), `audit_logs` identik. ✅

### Temuan & Perbaikan Selama Sprint 1

| Temuan | Perbaikan |
|---|---|
| `env()` di seeder bernilai null bila config di-cache di production | Dipindah ke `config/almega.php` |
| Karakter `#` di `.env` tanpa kutip memotong password admin awal | Peringatan di `.env.example`, README, troubleshooting deployment |
| `Auth::logoutOtherDevices()` tidak memutus sesi tanpa middleware `AuthenticateSession` | Sesi lain dihapus langsung dari tabel `sessions` (diuji) |
| `composer require --no-scripts` melewatkan package discovery (Livewire tidak terdaftar, Alpine tidak jalan) | `php artisan package:discover`; dicatat di troubleshooting |
| Tailwind v4 tidak dapat `@apply` class dari `@layer components` | Diubah menjadi `@utility` |

### Belum Diuji (dijadwalkan)

- Performance test dengan volume tiket (Sprint 7)
- Uji di hosting aktual (Sprint 8)
