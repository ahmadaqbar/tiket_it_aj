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

## Phase 1a + 1b — Perbaikan BUG-01 & Core Ticketing (2026-11-22)

**Hasil: 120 test · 0 gagal** (`php artisan test`, MariaDB 10.11). Baseline sebelum perubahan: 76/76 lulus.

| Suite | Jumlah | Hal yang diverifikasi |
|---|---|---|
| Feature/AuditLogTest (tambahan) | 2 | Regression BUG-01: nilai setara (0 vs false) tidak tercatat sebagai perubahan; perubahan nyata tetap tercatat dengan nilai lama/baru. **Terbukti gagal di kode lama, lulus setelah perbaikan.** |
| Feature/Ticket/TicketCreationTest | 15 | Form dengan unit terisi; tiket dibuat (status NEW, prioritas default Sedang, public_id ULID, nomor, history CREATED, audit `ticket.created`); due date SLA; validasi wajib; **user tidak bisa melapor untuk unit lain**; unit/kategori nonaktif ditolak; subkategori harus sesuai kategori; IT tidak memakai form user; multi-unit; rate limit 10/menit; dashboard user memakai hitungan nyata; lampiran tersimpan privat; **file berbahaya ditolak berdasarkan isi** (PHP, HTML berekstensi .jpg, SVG); ekstensi simpan dari isi file; batas ukuran & jumlah dari settings |
| Feature/Ticket/TicketNumberingTest | 7 | Format `IT-DR-20261114-001`; sequence per unit per hari & reset harian; >999 menjadi 4 digit; **sequence ikut rollback bila pembuatan gagal**; penomoran di luar transaksi ditolak; nomor tidak dipakai ulang (soft delete); prefix dapat dikonfigurasi, default tetap `IT` |
| Feature/Ticket/TicketPrivacyTest | 8 | Pemilik melihat tiketnya; **user lain (unit sama) mendapat 404**; id numerik tidak bisa dipakai di URL; daftar/pencarian/dashboard hanya tiket sendiri; riwayat hanya CLOSED; unduh lampiran hanya pemilik/IT/IT Admin, tamu diarahkan ke login; lampiran INTERNAL tersembunyi dari pemilik; header aman (`nosniff`, content-type) |
| Feature/Admin/CategoryAndSlaTest | 11 | Seeder 8 kategori/39 subkategori; hanya IT Admin; CRUD + audit; nama unik; **kategori/subkategori terpakai tidak bisa dihapus**; yang belum terpakai bisa dihapus; nama subkategori unik per kategori; subkategori nonaktif hilang dari form; perubahan SLA hanya untuk tiket baru + audit; validasi SLA; SLA nonaktif → tanpa due date |

### Catatan Pengujian
- `UploadedFile::fake()` melaporkan MIME dari **nama** file sehingga tidak dapat menguji file palsu. Test keamanan upload memakai file temp sungguhan (deteksi isi via finfo, sama dengan production).
- Konkurensi penomoran tidak dapat diuji paralel di PHPUnit. Jaminannya berasal dari row lock `SELECT … FOR UPDATE` + UNIQUE (`unit_id`, `ticket_date`) + UNIQUE `ticket_number`. Uji beban paralel dijadwalkan di Phase 9.
- Migration Phase 1b dijalankan **secara inkremental** pada database dev yang sudah berisi data Sprint 1 (tanpa `migrate:fresh`): sukses, data lama utuh.

### Pengujian Browser (Playwright)
| Skenario | Hasil |
|---|---|
| User (iPhone 13) membuat laporan: subkategori terfilter sesuai kategori, prioritas Tinggi, lampiran PNG | ✅ nomor `IT-DR-20261122-001` |
| Detail tiket: badge status/prioritas, lampiran "Lihat" (inline 200 `image/png`) & "Unduh", riwayat | ✅ |
| Dashboard user & Tiket Saya dengan data nyata | ✅ |
| IT Admin: dashboard IT (hitungan per status), Category Management, SLA Settings | ✅ |
| Error JavaScript | Tidak ada |

## Phase 1c — IT Management (2026-11-22)

**Hasil: 155 test · 534 assertion · 0 gagal** (sebelumnya 120; 35 test baru).

| Suite | Jumlah | Hal yang diverifikasi |
|---|---|---|
| Feature/Ticket/InternalNotePrivacyTest | 10 | IT membuat balasan publik & catatan internal (history + audit); **pelapor tidak melihat isi, entri riwayat, maupun label catatan internal**; IT/IT Admin melihatnya; **pelapor tidak dapat membuat catatan internal walau memalsukan request**; lampiran catatan internal bertanda INTERNAL dan 404 bagi pelapor; balasan pelapor + lampiran; user lain 403; `first_response_at` hanya dari balasan publik IT; tiket Closed hanya menerima catatan internal |
| Feature/Ticket/TicketStatusTest | 11 | Ubah status + history + audit + first response; RESOLVED wajib catatan (menjadi balasan publik); CLOSED dengan pesan; transisi tidak valid ditolak (CLOSED final); user tidak dapat mengubah status; konfirmasi pelapor; reopen dengan alasan (resolved_at dikosongkan, reopened_count); user lain tidak dapat konfirmasi/reopen; IT reopen; auto-close hanya tiket Resolved lewat batas; auto-close dapat dinonaktifkan |
| Feature/Ticket/ItQueueTest | 12 | Hanya IT/IT Admin; semua unit dalam satu antrean; 11 kombinasi filter; filter tanggal & melewati SLA + validasi rentang; urutan prioritas; paginasi 20; ambil/assign/lepas PIC + riwayat assignment; PIC harus staf IT; klasifikasi ulang menghitung ulang SLA + history; subkategori tidak cocok ditolak; urutan riwayat lengkap; dashboard IT |
| Feature/Ticket/TicketExportTest | 2 | XLSX dibaca ulang dengan openspout: header, filter unit diterapkan, **tidak memuat catatan internal**, audit `ticket.exported`; user 403 |

### Temuan selama pengujian
- Import `TicketStatus` di `It/DashboardController` terhapus oleh Pint di fase sebelumnya (belum dipakai saat itu) → error 500 di dashboard IT; tertangkap oleh `RoleAccessTest`, diperbaiki.
- Label "Catatan Internal" ikut ter-render (dalam ekspresi Alpine) pada form balasan pelapor — bukan kebocoran isi, tetapi diubah agar string internal hanya dirender untuk IT.
- Form balasan IT pada tiket Closed semula default "publik" (akan 403) → kini khusus catatan internal.
- Urutan di `TicketStatusService::change`: pesan penutupan harus dikirim **sebelum** status CLOSED diterapkan.

### Pengujian Browser (Playwright, end-to-end)
IT (desktop 1440px) ambil tiket dari antrean → catatan internal → quick reply (template masuk ke textarea, dapat diedit) → Dalam Proses → Resolved dengan catatan → export XLSX terunduh.
User (iPhone 13) membuka tiket: balasan & catatan penyelesaian terlihat, **teks catatan internal tidak ada di HTML**, konfirmasi "Ya, sudah selesai" → Closed. Tidak ada error JavaScript.

## Phase 1d — QR & Notifikasi (2026-11-22)
**Hasil: 176 test · 622 assertion · 0 gagal** (21 test baru).
- `QrCodeTest` (11): alur guest→login→form terisi; sumber QR; token invalid/unit nonaktif 404; QR tidak memberi akses data tiket;
  user unit lain diperingatkan; IT diarahkan ke antrean unit; rate limit; QR Management khusus IT Admin; PNG valid (GD), PDF valid,
  halaman cetak; regenerate membatalkan token lama + audit; manifest PWA mengikuti branding.
- `NotificationTest` (10): penerima per pemicu, catatan internal tidak memicu & tidak bocor ke notifikasi, PIC vs semua IT,
  tidak ada notifikasi ganda saat Resolved dengan catatan, ambil sendiri tidak memberi notifikasi, buka notifikasi menandai dibaca
  & redirect sesuai role, notifikasi milik orang lain 404, tandai semua dibaca, komponen Livewire lonceng.
- Browser (Playwright): scan QR di iPhone 13 → login → form unit DR → tiket; lonceng IT menampilkan "1 belum dibaca", update Livewire HTTP 200; tanpa error JS.
