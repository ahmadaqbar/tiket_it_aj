# SECURITY GUIDE — ALMEGA IT TICKETING SYSTEM

Status kontrol keamanan per item persyaratan. ✅ = terimplementasi & diuji · ⏳ = sprint berikutnya.

| # | Kontrol | Status | Implementasi |
|---|---|---|---|
| 1 | Password hashing | ✅ | bcrypt (cast `hashed` pada `User::password`, `BCRYPT_ROUNDS=12`). Password tidak pernah ditulis ke audit log. |
| 2 | CSRF protection | ✅ | Middleware `web` bawaan Laravel; semua form memakai `@csrf`. |
| 3 | XSS protection | ✅ | Output Blade `{{ }}` (escape otomatis). Tidak ada `{!! !!}` untuk input user. |
| 4 | SQL injection | ✅ | Eloquent/Query Builder dengan parameter binding; filter divalidasi Form Request. |
| 5 | Session security | ✅ | Driver database, regenerate ID saat login, invalidate saat logout, `http_only`, `same_site=lax`, `SESSION_SECURE_COOKIE=true` di production. Akun dinonaktifkan → sesi diputus pada request berikutnya (`EnsureUserIsActive`) dan baris sesi dihapus. Ganti password → sesi perangkat lain dihapus. Reset password admin → semua sesi user dihapus + remember token dirotasi. |
| 6 | Rate limiting | ✅ | Login: 5 percobaan gagal per (username+IP) per 60 detik (`LoginRequest`), + 20 request/menit per IP (`throttle:login`). |
| 7 | Authorization Policies | ✅ | Middleware `role:`, Gate `access-it`/`access-admin`, `UserPolicy`, `UnitPolicy`, `AuditLogPolicy`. Diuji dengan request langsung (bukan hanya menu). |
| 8 | Validasi file upload | ✅ | Whitelist `mimes` + `mimetypes` (dideteksi dari **isi** file), tanpa SVG/HTML/PHP; ukuran & jumlah dari setting; nama simpan ULID acak, ekstensi dari MIME hasil deteksi; nama asli disanitasi. |
| 9 | Private attachment storage | ✅ | `storage/app/private`, disk `serve => false`; unduh hanya lewat `AttachmentController` + `TicketAttachmentPolicy` (pemilik tiket / IT; lampiran INTERNAL hanya IT); header `nosniff`, `no-store`; non-gambar selalu `attachment`. |
| 10 | Secure QR token | ✅ (token) / ⏳ S4 (alur) | 48 hex (192 bit) dari `random_bytes`, unik, dapat diregenerasi (audit `unit.qr_regenerated`), disembunyikan dari serialisasi model. QR tidak memberi akses tanpa login. |
| 11 | HTTPS | ✅ | `URL::forceScheme('https')` di production, HSTS untuk request HTTPS, AutoSSL (lihat DEPLOYMENT). |
| 12 | Audit logging | ✅ | `audit_logs` append-only (update/delete dilempar `LogicException`), tanpa route hapus. Mencatat user, aksi, entitas, IP, user agent, waktu. |

## Kontrol Tambahan

- **Privasi tiket**: URL memakai ULID `public_id`; tiket milik orang lain menghasilkan **404**; semua daftar di-scope `user_id`.
- **Isolasi unit**: user hanya dapat melapor untuk unit aktif yang ditetapkan kepadanya (divalidasi di server).
- **Anti-spam**: maksimal 10 tiket/menit per user (juga berlaku untuk balasan); export maks. 6/menit.
- **Privasi catatan internal**: balasan, lampiran, dan entri riwayat INTERNAL tidak pernah dimuat untuk role USER (`visibleTo()`), USER tidak dapat membuat catatan internal (dipaksa PUBLIC di server), export tidak memuat percakapan.
- **Status**: transisi divalidasi di server (`TicketStatusService`) dengan row lock; USER hanya dapat konfirmasi/buka kembali tiket miliknya yang Resolved.

- **Tanpa registrasi publik** — akun hanya dibuat IT Admin. Tidak ada endpoint reset via email.
- **Password sementara** wajib diganti saat login pertama (`EnsurePasswordChanged`).
- **Kebijakan password**: minimal 8 karakter, mengandung huruf dan angka.
- **Anti-lockout admin**: IT Admin tidak dapat menurunkan role/menonaktifkan dirinya sendiri; IT Admin aktif terakhir tidak dapat dinonaktifkan.
- **Security headers** (`SecurityHeaders`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, HSTS.
- **Pesan login generik** ("Username/email atau password salah") — tidak membocorkan keberadaan akun. Status nonaktif hanya diberitahukan setelah password benar.
- **Data yang tidak dapat dihapus via UI**: unit, user (hanya nonaktif), audit log.
- **Rahasia** hanya di `.env` (tidak di-commit; `.gitignore`). Password admin awal dari `.env`, bukan source code.
- **Lazy-loading guard** aktif di non-production untuk mencegah N+1.

## Checklist Production

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` unik, tersimpan aman
- [ ] `SESSION_SECURE_COOKIE=true`, HTTPS aktif
- [ ] `.env` di luar web root, permission 600; `https://it.almegajaya.com/.env` → 403/404
- [ ] Password IT Admin awal sudah diganti; `INITIAL_ADMIN_PASSWORD` dikosongkan dari `.env` setelah seed
- [ ] `DemoSeeder` tidak dijalankan
- [ ] Backup terjadwal & diuji restore
