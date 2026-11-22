# SYSTEM ARCHITECTURE

## 1. Gaya
**Modular monolith** Laravel 13, satu database MySQL, dirancang untuk **shared hosting**: tanpa proses permanen,
semua state di database, pekerjaan terjadwal lewat satu Cron (`schedule:run`), notifikasi via polling ringan.

```
Browser (HP/Desktop, PWA)
  │ HTTPS
public/index.php → Middleware (session, CSRF, SecurityHeaders, auth, active, password.changed, role:…, throttle)
  → Controller / Livewire (NotificationBell)
      ├─ Form Request (validasi + authorize → Policy)
      └─ Service Layer (DB::transaction, AuditService, NotificationService)
            ├─ Eloquent → MySQL (InnoDB, utf8mb4, FULLTEXT KB)
            ├─ storage/app/private (lampiran, logo) — diunduh via controller + Policy
            └─ AiGateway → AiProvider (HTTPS keluar, opsional)
Cron tiap menit → schedule:run → queue:work --stop-when-empty · tickets:auto-close (tiap jam) · queue:prune-failed
```

## 2. Modul
| Modul | Komponen utama |
|---|---|
| Auth & akses | LoginController, PasswordController, ProfileController; middleware `role`, `active`, `password.changed`; Gate `access-it`/`access-admin` |
| Master data | UserService, UnitService, CategoryService, SlaPolicyController, QuickReplyTemplateController |
| Tiket | TicketService (buat, klasifikasi ulang), TicketNumberService (increment atomik), AttachmentService, TicketReplyService, TicketStatusService, TicketAssignmentService, TicketExportService |
| Otorisasi tiket | TicketPolicy, TicketAttachmentPolicy, scope `TicketReply::visibleTo`, `TicketHistory::visibleTo` |
| QR | QrCodeService, QrController (publik), Admin\QrCodeController |
| Notifikasi | NotificationService, TicketActivity (database), Livewire NotificationBell |
| Knowledge Base | KnowledgeBaseService (FULLTEXT + LIKE, related), KbArticlePolicy, scope `KbArticle::readableBy` |
| AI | AiManager, AiGateway, AiProvider (Anthropic / OpenAI-compatible / Fake), AiContextBuilder, AiJson, TicketAiService, KnowledgeBaseAiService |
| Analytics & laporan | AnalyticsService, ReportPeriod, ReportService (dompdf, openspout + SafeRow) |
| Konfigurasi produk | SettingService (tabel `settings`), Branding, config/helpdesk.php (edisi), SettingsController |
| Audit | AuditService, AuditLog (append-only) |

## 3. Otorisasi Berlapis
1. Route middleware (`role:IT_ADMIN` dsb.) → 403.
2. Form Request `authorize()` → Policy.
3. Controller `authorize()` / `abort_unless(can(...), 404)` untuk data milik orang lain (tidak membocorkan keberadaan).
4. Scope query (`ownedBy`, `visibleTo`, `readableBy`) — data internal tidak pernah dimuat untuk role USER.
5. Aturan bisnis di service (transisi status, visibilitas balasan, anti-lockout admin).
6. `AccessMatrixTest` menguji seluruh rute GET secara otomatis.

## 4. Konfigurasi
1. Tabel `settings` (System Settings, UI) → 2. `config/helpdesk.php` & `config/ai.php` (.env) → 3. default kode = nilai Almega.
Rahasia (DB, APP_KEY, AI_API_KEY) hanya di `.env`. Timezone di `.env` (tidak diubah via UI karena memengaruhi data lama).

## 5. Keputusan Teknis (ADR ringkas)
| Keputusan | Alasan |
|---|---|
| Login username (email opsional) | Staf unit tidak semua punya email |
| `user_units` many-to-many + unit utama | Staf dapat menangani beberapa unit |
| CRUD admin: controller + Form Request; Livewire hanya untuk lonceng polling | Berfungsi tanpa JS, mudah diuji, minim state server |
| Form laporan & balasan: Blade + Alpine (multipart biasa) | Upload tanpa endpoint temporary; cocok batas upload hosting |
| Tiket di URL = ULID; akses tidak sah → 404 | Tidak dapat ditebak & tidak membocorkan keberadaan |
| Nomor tiket: `INSERT … ON DUPLICATE KEY UPDATE` + retry | Bebas deadlock pada laporan bersamaan (uji 16 proses paralel) |
| Notifikasi database sinkron + polling | Tanpa worker/WebSocket |
| Kategori KB = kategori tiket | Tanpa tabel duplikat; relevansi tiket↔artikel |
| AI via endpoint JSON + Alpine, provider abstraction | Mudah diuji (Fake), tidak terkunci satu vendor, AI opsional |
| Spreadsheet: semua teks sebagai StringCell (`SafeRow`) | Mencegah formula injection |
| Logo di storage privat + route | Tanpa `storage:link` (symlink sering diblokir hosting) |
| Warna tema = override CSS variable Tailwind v4 | Rebranding tanpa build ulang |
