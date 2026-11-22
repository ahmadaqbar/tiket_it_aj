# PRODUCTIZATION PLAN — AI IT TICKETING & HELPDESK KIT

Produk komersial self-hosted yang dikembangkan **dari** ALMEGA IT TICKETING SYSTEM, bukan aplikasi baru.
Satu codebase, dua edisi yang dibedakan oleh **data konfigurasi**, bukan oleh fork kode.

## 1. Edisi

| | Edisi A — Almega Internal | Edisi B — AI IT Ticketing & Helpdesk Kit |
|---|---|---|
| Organisasi | PT Almega Jaya Group | Dikonfigurasi pembeli |
| Domain | https://it.almegajaya.com | Domain pembeli |
| Unit | 15 unit Almega (seeder `UnitSeeder`) | Dibuat di wizard/halaman admin (seeder contoh opsional) |
| Nomor tiket | `IT-{UNIT}-{YYYYMMDD}-{SEQ}` | Prefix dapat diatur (default `IT`) |
| Role | USER / IT / IT_ADMIN | Sama (tidak ada role baru) |
| AI | Opsional, default nonaktif | Opsional, default nonaktif |
| Pemilihan edisi | `HELPDESK_EDITION=almega` (default untuk deployment ini) | `HELPDESK_EDITION=product` |

Edisi hanya memengaruhi **seeder default & nilai awal setting**. Logika aplikasi identik.

## 2. Model Konfigurasi (single-organization)

Tiga lapisan, urutan prioritas dari atas:

1. **Tabel `settings`** (dapat diubah IT Admin melalui UI): nama aplikasi, organisasi, kontak, warna tema,
   logo (path file), prefix tiket, auto-close, batas upload, konfigurasi AI non-rahasia, konfigurasi KB.
2. **`config/helpdesk.php`** (dibaca dari `.env`): edisi, admin awal, rahasia (API key AI), default instalasi.
3. **Default di kode** yang identik dengan konfigurasi Almega saat ini → upgrade tanpa langkah manual
   tidak mengubah tampilan atau nomor tiket Almega.

Rahasia (API key AI) **tidak** disimpan di tabel `settings` sebagai teks biasa.
Pilihan: `.env` (disarankan untuk shared hosting) atau kolom terenkripsi `encrypted` cast Laravel (APP_KEY).

## 3. Kesiapan Multi-tenant (dokumentasi saja)

Tidak ada multi-tenancy pada fase ini. Agar ekspansi kelak tidak mahal:
- Semua data operasional sudah terikat `unit_id`. Kolom `organization_id` dapat ditambahkan kemudian secara aditif.
- Akses setting hanya lewat `SettingService` (satu titik untuk scoping per organisasi di masa depan).
- Tidak ada query global yang tersebar ke tabel setting.

## 4. Lisensi (dokumentasi saja)

Tidak ada DRM/lisensi online pada fase ini. Kemungkinan di masa depan: file lisensi bertanda tangan (offline verify)
dicek oleh middleware ringan. Tidak memblokir fitur inti.

## 5. Roadmap Fase (direvisi setelah audit)

Audit menemukan core ticketing **belum ada**, sehingga Phase 1 diperluas.

| Phase | Lingkup | Bergantung pada | Status |
|---|---|---|---|
| 0 | Audit: `PROJECT_AUDIT.md`, `PROJECT_STATUS.md`, rencana ini | — | ✅ |
| 1a | Perbaikan BUG-01 audit log | 0 | ✅ |
| 1b | Core ticketing: kategori/subkategori, SLA, penomoran, form laporan, lampiran privat, Tiket Saya, detail, dashboard user | 1a | ✅ |
| 1c | Shared queue IT, balasan publik, internal note, quick reply, status + reopen/konfirmasi, assignment, history | 1b | ✅ |
| 1d | QR flow (`/qr/{token}`, preview/unduh PNG/PDF/cetak), notifikasi in-app | 1c | ⏳ |
| 2 | Knowledge Base (artikel, kategori, tag, visibilitas, publish/archive, pencarian FULLTEXT/LIKE, artikel terkait) | 1b | ⏳ |
| 3 | AI foundation: `AiProvider` interface, provider Anthropic & OpenAI-compatible, setting, timeout, error, rate limit, `ai_requests` log | 1c | ⏳ |
| 4 | AI ticket assistance: klasifikasi, troubleshooting, ringkasan, generator respons (semua editable, konfirmasi manual) | 2, 3 | ⏳ |
| 5 | AI KB assistant (retrieval DB, referensi artikel tervalidasi) | 2, 3 | ⏳ |
| 6 | Product configuration: branding, logo, warna, kontak, prefix tiket, edisi | 1b | ⏳ |
| 7 | Analytics + recurring issue (berbasis data) + laporan PDF/Excel | 1c | ⏳ |
| 8 | Paket produk: INSTALLATION/UPDATE guide, seeder produk, paket zip | 6 | ⏳ |
| 9 | QA & security menyeluruh | semua | ⏳ |
| 10 | Persiapan shared hosting (deploy hanya dengan persetujuan) | 9 | ⏳ |

## 6. Rancangan Ringkas Modul Baru

### 6.1 Knowledge Base (Phase 2)
Tabel: `kb_categories`, `kb_articles` (title, slug unik, problem, symptoms, root_cause, steps, resolution,
visibility `INTERNAL|PUBLIC`, status `DRAFT|PUBLISHED|ARCHIVED`, author_id, updated_by, view_count, published_at),
`kb_tags`, `kb_article_tag`. Pencarian: MySQL FULLTEXT (InnoDB) dengan fallback `LIKE`.
Akses: USER hanya `PUBLISHED + PUBLIC`; IT/IT_ADMIN semua yang published + draft milik sendiri; IT_ADMIN semua.

### 6.2 AI (Phase 3–5)
```
AiAssistantService (fitur: classify, troubleshoot, summarize, draftReply, askKnowledgeBase)
   ├─ AiContextBuilder   — hanya data yang boleh dilihat pemanggil; tanpa internal note untuk USER;
   │                       redaksi email/telepon; batas panjang; konten dibungkus sebagai data tak tepercaya
   ├─ AiProvider (interface) ── AnthropicProvider | OpenAiCompatibleProvider | FakeProvider (test)
   ├─ AiResponseValidator — parse JSON terstruktur, validasi kategori/prioritas terhadap master data,
   │                        validasi referensi artikel KB terhadap artikel yang benar-benar dikirim
   └─ ai_requests (log)   — user, fitur, provider, model, token, durasi, status, error code; tanpa isi prompt penuh
```
Prinsip: server-side saja, tidak ada eksekusi aksi, semua output adalah saran yang harus dikonfirmasi manusia.

## 7. Paket Distribusi (Phase 8)

Zip berisi kode + `vendor/` (`--no-dev`) + `public/build/`, tanpa `.env`, `.git`, `node_modules`, `tests/`.
Dokumen: INSTALLATION, CONFIGURATION, AI_INTEGRATION_GUIDE, KNOWLEDGE_BASE_GUIDE, UPDATE guide,
HOSTING_REQUIREMENTS, BACKUP_RESTORE, SECURITY, USER/IT_ADMIN guide, CHANGELOG.
