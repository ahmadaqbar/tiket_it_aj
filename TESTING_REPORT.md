# TESTING REPORT — AI IT Ticketing & Helpdesk Kit

## Ringkasan Akhir (1.0.0-rc.1, 2026-11-22)

| Item | Hasil |
|---|---|
| Automated test | **255 test · 1.054 assertion · 0 gagal** (PHPUnit 12, MariaDB 10.11) |
| Panggilan API AI berbayar dalam test | Tidak ada (FakeAiProvider, `Http::fake`, stub lokal) |
| Audit dependensi | composer audit & npm audit: 0 kerentanan |
| Uji konkurensi penomoran | 16 proses × 25 tiket paralel: 400/400 sukses, 0 duplikat |
| Uji performa | Antrean/dashboard ≤ 63 ms, analytics ≤ 114 ms (±5.800 tiket) |
| Backup/restore | Semua tabel identik setelah restore |
| Instalasi bersih dari paket | Berhasil (edisi produk, APP_ENV=production) |
| Bug ditemukan & diperbaiki selama pengembangan lanjutan | BUG-01 audit palsu, redaksi AI (2 pola), SEC-01 formula injection Excel, CON-01 deadlock penomoran, SEC-02 Referer, forceScheme tanpa SSL, paket membengkak |

Riwayat rinci per fase di bawah.

---


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

## Phase 2 — Knowledge Base (2026-11-22)
**Hasil: 188 test · 0 gagal** (12 test baru, `KnowledgeBaseTest`): CRUD + publish/archive + audit; slug unik & stabil; **user hanya
melihat Published+Publik (internal/draft/arsip → 404)**; IT melihat semua; user tidak dapat menulis (403); pencarian gejala/tag/kategori;
hitung tampilan; artikel terkait menghormati visibilitas; "jadikan artikel" tanpa catatan internal; pusat bantuan dapat dinonaktifkan;
konten di-escape (XSS); subkategori harus sesuai kategori.
Manual: jalur FULLTEXT pada DB dev — query memakai `MATCH`, artikel paling relevan di urutan pertama.

## Phase 3–5 — AI (2026-11-22)
**Hasil: 224 test · 802 assertion · 0 gagal** (36 test baru). Tidak ada panggilan API berbayar (`FakeAiProvider`, `Http::fake`).
- `AiFoundationTest` (12): default nonaktif & ticketing tetap jalan; konfigurasi tidak lengkap; format request/response Anthropic & OpenAI-compatible;
  HTTP 401 ditangani tanpa membocorkan key; timeout; rate limit per user & harian; log hanya metadata; JSON tidak valid; key tidak di HTML; USER 403.
- `TicketAiTest` (9): klasifikasi dipetakan ke master data & **tidak mengubah tiket**; nilai tidak valid dibuang; **klasifikasi & draf tanpa catatan internal,
  nama, email, telepon, password**; ringkasan menyertakan catatan internal; **prompt injection dinetralkan**; troubleshooting hanya artikel Published &
  referensi tervalidasi (ID karangan dibuang); tanpa KB → "saran umum"; draf tidak terkirim; gaya tidak valid 422.
- `KnowledgeBaseAiTest` (5): referensi tervalidasi; tanpa artikel → bukan "berdasarkan KB" walau model mengklaim; draft/arsip tidak dikirim; validasi; halaman nonaktif.
- `Unit/AiRedactionTest` (10): pola redaksi, data teknis (IP, jam, nomor tiket) tetap utuh, pembungkus data, ekstraksi JSON.
- Browser (stub lokal berformat Anthropic): klasifikasi → isi form (belum tersimpan), draf → textarea (belum terkirim), tanya KB; `ai_requests` tercatat; tanpa error JS.

## Phase 6 — Product Configuration (2026-11-22)
**Hasil: 236 test · 0 gagal** (12 test baru, `SystemSettingsTest`): default Almega utuh tanpa setting; hanya IT Admin; rebranding edisi produk
(login, dashboard, manifest, CSS variable) + audit; prefix & batas lampiran berlaku; validasi; logo upload/serve/hapus tanpa symlink;
SVG ditolak; toggle AI + alasan bila key kosong; API key tidak tampil; CRUD template; tugas terjadwal manual; seeder edisi produk.

## Phase 7 — Analytics & Reporting (2026-11-22)
**Hasil: 249 test · 925 assertion · 0 gagal** (13 test baru).
- `AnalyticsTest` (8): **akurasi angka dengan data terkontrol** (total, status, selesai dalam periode, rata-rata respons 110 mnt & penyelesaian 780 mnt,
  SLA 66,7% & 50%); tiket dibuka kembali tidak dihitung selesai; per unit/kategori/prioritas + filter unit; bucket tren & granularitas; isu berulang (≥3, unit sama);
  rekomendasi berbasis data; preset periode; akses halaman.
- `ReportTest` (5): Excel operasional memuat tiket & respons IT publik **tanpa catatan internal**; Excel manajemen 7 sheet dengan angka benar; PDF valid; filter unit & periode kosong; akses & validasi.
- Browser: 5 grafik Chart.js ter-render pada 150 tiket demo; PDF operasional/manajemen & Excel terunduh; PDF manajemen dirender dan diperiksa visual.

## Phase 9 — QA & Security (2026-11-22)

**Hasil: 255 test · 1.054 assertion · 0 gagal.**

### Audit & Review
| Pemeriksaan | Hasil |
|---|---|
| `composer audit` | Tidak ada advisory |
| `npm audit` | 0 kerentanan |
| Rute tanpa autentikasi | Hanya `login`, `qr/{token}` (throttle), `manifest.webmanifest`, `branding/logo`, `up` — sesuai desain |
| Raw SQL | Hanya string konstan atau parameter binding (pencarian KB) |
| `{!! !!}` | SVG QR (dihasilkan library dari URL internal) & 2 string statis |
| Mass assignment | Tidak ada `$request->all()`; controller memakai `validated()`/`safe()` |
| N+1 | `preventLazyLoading` aktif di non-production; seluruh test (termasuk halaman daftar) lulus tanpa pelanggaran |

### Temuan & Perbaikan
| # | Temuan | Dampak | Perbaikan | Bukti |
|---|---|---|---|---|
| SEC-01 | **Formula injection Excel**: `Cell::fromValue()` openspout mengubah string berawalan `=` (mis. judul tiket `=HYPERLINK(...)`) menjadi sel formula | File export/laporan dapat memuat formula aktif buatan user | `App\Support\SafeRow` — semua string ditulis sebagai `StringCell` | `SpreadsheetInjectionTest`: **gagal di kode lama, lulus setelah perbaikan** (tidak ada `<f>` di XML sheet) |
| CON-01 | **Deadlock penomoran tiket** saat laporan masuk bersamaan (`INSERT IGNORE` + `SELECT FOR UPDATE` → shared lock lalu exclusive lock) | 142 dari 200 pembuatan tiket gagal (error 1213) pada 8 proses paralel; **tidak ada nomor ganda** | Increment atomik `INSERT … ON DUPLICATE KEY UPDATE last_number = last_number + 1` + retry transaksi (3×) | Lihat uji konkurensi di bawah |
| SEC-02 | Tautan "kembali" detail tiket IT memakai Referer | Potensi open redirect bila Referer eksternal mengandung `/it/tiket` | Hanya dipakai bila diawali URL antrean aplikasi | Review kode |
| PERF-01 | PDF operasional ±7,5 dtk / bulan data | Risiko `max_execution_time` 30 dtk di shared hosting | Batas PDF 500 baris + `set_time_limit(120)` bila diizinkan; Excel untuk data lengkap | Uji performa |

### Uji Konkurensi Penomoran (DB nyata, InnoDB, proses PHP paralel)
| Skenario | Sebelum perbaikan | Setelah perbaikan |
|---|---|---|
| 8 proses × 25 tiket, unit sama, waktu bersamaan | 58/200 berhasil, 142 deadlock, 0 duplikat | **200/200 berhasil, 0 error, 200 nomor unik, berurutan tanpa celah (059→258)** |
| 16 proses × 25 tiket | — | **400/400 berhasil, 0 error, 400 nomor unik** |

### Uji Performa (DB dev ±5.800 tiket, `php artisan serve`, median 3 request)
| Halaman | Waktu |
|---|---|
| Dashboard IT | 52 ms |
| Antrean tiket (default / cari / filter+urut prioritas / lewat SLA hal. 5) | 50–63 ms |
| Analytics bulan ini / tahun ini | 98 / 114 ms |
| Knowledge Base | 32 ms |
| Export antrean XLSX (5.800 baris, 331 KB) | 1,4 dtk |
| Laporan manajemen XLSX (tahun ini) | 0,13 dtk |
| Laporan operasional PDF (bulan lalu) | 7,5 dtk → batas diturunkan ke 500 baris |

Catatan: shared hosting umumnya lebih lambat dari lingkungan uji; angka di atas menjadi baseline.

### Matriks Akses Otomatis (`AccessMatrixTest`)
Menelusuri **seluruh rute GET** (rute baru otomatis ikut teruji): tamu selalu diarahkan ke login kecuali rute publik;
USER mendapat 403/404 di semua rute `it/*`, `admin/*`, dan penulisan KB (25 rute); IT mendapat 403 di semua rute `admin/*`; security header ada.

### Backup & Restore (skema lengkap)
`mysqldump --single-transaction` → gzip (445 KB) → restore ke DB baru: jumlah baris identik untuk users, units, tickets (5.807),
ticket_replies, ticket_histories, kb_articles, audit_logs, settings, notifications (2.421), ai_requests. Index FULLTEXT ikut ter-restore.

### Belum Diuji / Batasan
- Uji di shared hosting aktual (spesifikasi belum tersedia) — Phase 10 menyiapkan paket & checklist.
- Uji beban HTTP konkuren (banyak user membuka halaman bersamaan) belum dilakukan; konkurensi tulis yang kritis (penomoran) sudah diuji.
- Panggilan ke API AI sungguhan (berbayar) tidak dilakukan; diganti Fake provider, `Http::fake`, dan stub lokal berformat Anthropic.

## Phase 10 — Persiapan Shared Hosting (2026-11-22)
- `scripts/build-package.sh` untuk `product` dan `almega`: zip ±48 MB, tanpa `.env`, tanpa `tests/`, tanpa dependensi dev, `public/build/` ada, `HELPDESK_EDITION` sesuai.
- **Instalasi bersih dari paket** (direktori & DB baru, `APP_ENV=production`): `key:generate`, `migrate --seed` (unit `HQ`, 8 kategori, admin wajib ganti
  password), `config:cache`/`route:cache`/`view:cache` sukses, scheduler terdaftar, `/.env` 404, `/up` 200.
- Browser (Playwright) pada paket terpasang: admin ganti password → rebranding → buat user → user (iPhone 13) ganti password → buat tiket
  `IT-HQ-20261122-001` → tampil di antrean IT. Tanpa error JavaScript.
- Belum dilakukan: deployment ke hosting aktual (menunggu persetujuan & spesifikasi hosting).
