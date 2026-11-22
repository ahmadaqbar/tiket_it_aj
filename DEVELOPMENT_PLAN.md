# DEVELOPMENT PLAN — ALMEGA IT TICKETING SYSTEM

> Sejak 2026-11-22 proyek dilanjutkan sebagai **AI IT Ticketing & Helpdesk Kit**. Urutan fase terbaru ada di
> [PRODUCTIZATION_PLAN.md](PRODUCTIZATION_PLAN.md) §5; sprint 2–8 di bawah dipetakan ke Phase 1b–10 di sana.

Organisasi: PT Almega Jaya Group · Production: https://it.almegajaya.com (shared hosting)
Metode: incremental, satu sprint per commit, setiap sprint diuji sebelum dinyatakan selesai.

## 0. Audit Awal (2026-11-14)

| Item | Temuan | Tindak lanjut |
|---|---|---|
| Project directory | Repository git kosong (belum ada commit) | Inisialisasi Laravel baru |
| PHP | 8.3.6, seluruh extension wajib tersedia | — |
| Composer | 2.8.12 | — |
| Node.js | 22.x (build asset saja) | `public/build` di-build lokal |
| MySQL | Tidak terpasang → MariaDB 10.11 diinstal untuk dev/test | Production: MySQL 8 / MariaDB 10.6+ |
| Spesifikasi hosting | **Belum tersedia** | Asumsi konservatif di `HOSTING_REQUIREMENTS.md`, wajib diverifikasi sebelum Sprint 8 |

## 1. Stack

| Layer | Pilihan |
|---|---|
| Backend | Laravel 13 (PHP ≥ 8.3), modular monolith, Service Layer |
| Frontend | Blade + Livewire 4 + Alpine.js (bundled Livewire) + Tailwind CSS 4 |
| Charts | Chart.js 4 (bundle Vite) |
| Database | MySQL 8 / MariaDB 10.6+ (InnoDB, utf8mb4) |
| Session / Cache / Queue | `database` driver (tanpa Redis) |
| Scheduler | Cron Job shared hosting → `php artisan schedule:run` tiap menit |
| PDF / Excel / QR | dompdf · openspout · chillerlan/php-qrcode |
| Test | PHPUnit (Laravel feature & unit test) dengan database MySQL/MariaDB |

## 2. Sprint

| Sprint | Lingkup | Status |
|---|---|---|
| **1 — Foundation** | Setup Laravel, konfigurasi DB, autentikasi (login username/email, logout, rate limit, ganti password, profil), role (USER/IT/IT_ADMIN) + middleware + Gate + Policy, master unit (15 unit via seeder), manajemen user (multi-unit), audit log (login/logout/user/unit), layout responsif per role, settings | ✅ Selesai — lihat `TESTING_REPORT.md` |
| 2 — Core Ticketing | Kategori/subkategori (seeder + CRUD admin), SLA policy, penomoran tiket (transaction + row lock), form buat laporan (1 halaman), attachment private, Tiket Saya, detail tiket, dashboard user | ✅ Selesai 2026-11-22 (Phase 1b) |
| 3 — IT Management | Shared queue + filter/sort/search/pagination, balasan publik, internal note, quick reply, status management + reopen/konfirmasi, assignment PIC, history | ⏳ |
| 4 — QR & Notification | QR per unit (PNG/PDF/print/regenerate), `/qr/{token}` → login → unit terisi, database notification + polling, manifest PWA | ⏳ |
| 5 — Analytics | Dashboard IT (summary + 9 chart), filter periode, SLA metrics, recurring issues | ⏳ |
| 6 — Reporting | Operational & Management report (PDF + Excel), filter tanggal/unit, laporan bulanan | ⏳ |
| 7 — QA & Security | Test menyeluruh, security review, performance, uji backup/restore | ⏳ |
| 8 — Deployment | Persiapan hosting, domain, SSL, DB production, cron, smoke test (**hanya dengan persetujuan eksplisit**) | ⏳ |

## 3. Prinsip Implementasi

1. Otorisasi selalu di backend (middleware `role:`, Gate, Policy). Menu hanya disembunyikan sebagai pelengkap.
2. Validasi melalui Form Request (controller) atau rules Livewire yang setara.
3. Business logic di `app/Services`. Controller/komponen hanya orkestrasi.
4. Semua perubahan data penting dalam `DB::transaction` dan tercatat di audit log.
5. Tidak ada dummy data di production: seeder production hanya data master (role, unit, kategori, SLA, settings, template) + 1 akun IT Admin awal dari variabel `.env`.
6. Data demo (user contoh) hanya lewat `DemoSeeder` yang menolak berjalan di `APP_ENV=production`.
7. Query analytics memakai agregasi SQL + index; tidak ada N+1 (eager loading).
8. Commit per sprint.

## 4. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Hosting hanya PHP 8.2 | Fallback Laravel 12 (dicatat di HOSTING_REQUIREMENTS) |
| Tidak ada SSH/Composer | Paket deploy berisi `vendor/` + `public/build` dibuat lokal |
| Tidak ada Cron | Auto-close & antrean notifikasi dijalankan manual lewat halaman admin "Jalankan Tugas Terjadwal" (terdokumentasi), bukan lewat request user |
| Batas memori/execution time | Excel streaming (openspout), PDF per periode, pagination |
| Upload limit kecil | Batas upload dapat dikonfigurasi, validasi sisi server + pesan jelas |

## 5. Ringkasan Sprint 1 (2026-11-14)

**Selesai & teruji (76 test):** setup Laravel 13, konfigurasi MySQL, migration & seeder fondasi, autentikasi
(login, logout, rate limit, paksa/ganti password, profil), 3 role + middleware/Gate/Policy, User Management,
Unit Management, audit log, layout responsif per role.

**Tertunda (sesuai rencana sprint berikutnya):**
- Halaman QR Management (token per unit sudah ada & dapat diregenerasi lewat service) → Sprint 4
- Halaman System Settings (tabel & seeder sudah ada) → Sprint 4
- Category/SLA management → Sprint 2
- Verifikasi spesifikasi hosting aktual → sebelum Sprint 8

**Perubahan terhadap rencana:** tidak ada perubahan business logic. Keputusan teknis tambahan dicatat di
`SYSTEM_ARCHITECTURE.md` §5 (login username, multi-unit, pemutusan sesi via tabel `sessions`).
