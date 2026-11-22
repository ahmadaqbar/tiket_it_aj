# SECURITY GUIDE

✅ = terimplementasi & diuji. Detail pengujian: TESTING_REPORT.md.

## 1. Kontrol Wajib
| # | Kontrol | Implementasi |
|---|---|---|
| 1 | Password hashing | bcrypt (`hashed` cast, 12 rounds); tidak pernah di audit log ✅ |
| 2 | CSRF | Middleware `web`; semua form `@csrf`; request AJAX (AI) mengirim `X-CSRF-TOKEN` ✅ |
| 3 | XSS | Blade `{{ }}`; Alpine `x-text`; `{!! !!}` hanya untuk SVG QR hasil library & 2 string statis; konten KB/tiket di-escape (diuji) ✅ |
| 4 | SQL injection | Eloquent/binding; raw SQL hanya konstanta atau binding (pencarian KB) ✅ |
| 5 | Session | Driver database, regenerate saat login, invalidate saat logout, `http_only`, `same_site=lax`, `secure` bila HTTPS; akun nonaktif → sesi diputus; ganti/reset password → sesi lain dihapus ✅ |
| 6 | Rate limiting | Login 5/menit per akun+IP & 20/menit per IP; tiket & balasan 10/menit/user; QR 30/menit/IP; AI 20/menit + kuota per user/jam & harian; export 6/menit; laporan 10/menit ✅ |
| 7 | Authorization | Middleware role, Gate, 6 Policy, scope visibilitas; **matriks akses otomatis seluruh rute GET** ✅ |
| 8 | Validasi upload | `mimes` + `mimetypes` dari **isi** file (finfo); tanpa SVG/HTML/PHP; ukuran & jumlah dari setting; nama simpan ULID; ekstensi dari MIME ✅ |
| 9 | Penyimpanan privat | `storage/app/private`, disk `serve=false`; unduh via Policy; lampiran INTERNAL hanya IT; `nosniff`, `no-store` ✅ |
| 10 | Token QR | 192-bit `random_bytes`, unik, regenerasi (audit), tidak diserialisasi; QR tidak memberi akses data tanpa login ✅ |
| 11 | HTTPS | `forceScheme('https')` bila production & `APP_URL` https; HSTS untuk request HTTPS; AutoSSL ✅ |
| 12 | Audit logging | Append-only (model menolak update/delete), tanpa route hapus; user, aksi, entitas, IP, user agent, waktu ✅ |

## 2. Privasi Tiket & Catatan Internal
- URL tiket = ULID; tiket orang lain → **404**; daftar/dashboard/pencarian di-scope ke pemilik.
- Catatan internal, lampiran internal, dan riwayat internal **tidak pernah dimuat** untuk role USER; USER tidak dapat membuat catatan internal (dipaksa publik di server).
- Catatan internal tidak memicu notifikasi ke pelapor, tidak masuk export/laporan, tidak dikirim ke AI untuk klasifikasi/troubleshooting/draf balasan.

## 3. Keamanan AI
Lihat AI_INTEGRATION_GUIDE.md §4: server-side saja, API key hanya `.env` (tidak di HTML/log), tanpa aksi otomatis, data diredaksi
(nama pelapor, email, telepon, password/token), konten dibungkus sebagai data tak tepercaya (anti prompt injection), referensi KB
divalidasi, hanya artikel Published yang boleh dibaca, log tanpa isi prompt, default nonaktif.

## 4. Integritas Data
- Penomoran tiket: increment atomik + UNIQUE constraint + retry; diuji 16 proses paralel (0 duplikat, 0 error).
- **Formula injection spreadsheet** dicegah (`SafeRow`: teks selalu sel string).
- Unit, user, kategori terpakai, audit log tidak dapat dihapus lewat UI.

## 5. Kontrol Tambahan
Tanpa registrasi publik · password sementara wajib diganti · kebijakan password min. 8 huruf+angka · anti-lockout IT Admin ·
security headers (`nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, HSTS) · pesan login generik ·
logo hanya PNG/JPG/WEBP · tautan kembali tidak memakai Referer eksternal · `preventLazyLoading` di non-production.

## 6. Checklist Production
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
- [ ] `APP_KEY` unik & tersimpan aman (dibutuhkan saat restore)
- [ ] `SESSION_SECURE_COOKIE=true`, AutoSSL aktif
- [ ] `.env` di luar web root, permission 600; `/.env` → 403/404
- [ ] Password IT Admin awal diganti; `INITIAL_ADMIN_PASSWORD` dikosongkan
- [ ] `DemoSeeder`/`DemoTicketSeeder` tidak dijalankan
- [ ] AI: hanya diaktifkan setelah menyetujui kebijakan data provider; `AI_DAILY_LIMIT` sesuai anggaran
- [ ] Backup terjadwal & uji restore
