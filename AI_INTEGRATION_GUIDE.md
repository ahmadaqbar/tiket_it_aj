# AI INTEGRATION GUIDE

Modul AI bersifat **opsional**. Seluruh fungsi inti (tiket, antrean, balasan, status, QR, notifikasi, laporan)
berjalan penuh tanpa AI, dan tetap berjalan saat layanan AI gangguan.

> Prinsip: **AI adalah asisten, bukan otoritas.** AI tidak pernah mengubah data, mengubah status/prioritas,
> atau mengirim balasan secara otomatis. Semua hasil adalah saran yang harus diperiksa dan dikonfirmasi IT.

## 1. Fitur

| Fitur | Lokasi | Pengguna | Data yang dikirim ke AI |
|---|---|---|---|
| Klasifikasi (kategori, subkategori, prioritas, dampak, ringkasan, langkah awal) | Detail tiket IT → Asisten AI | IT, IT Admin | Judul, deskripsi, percakapan **publik**, daftar kategori aktif |
| Troubleshooting (dengan rujukan KB) | Detail tiket IT | IT, IT Admin | Seperti di atas + ≤5 artikel KB **Published** yang boleh dibaca peminta |
| Ringkasan tiket | Detail tiket IT | IT, IT Admin | Judul, deskripsi, percakapan **termasuk catatan internal** (hasil hanya tampil untuk IT) |
| Draf balasan (Formal/Profesional/Singkat/Teknis) | Detail tiket IT | IT, IT Admin | Judul, deskripsi, percakapan **publik** (tanpa catatan internal agar tidak bocor ke pelapor) |
| Tanya Knowledge Base | Knowledge Base → Tanya AI (`/kb/tanya`) | IT, IT Admin | Pertanyaan + ≤5 artikel KB Published yang relevan |
| Recurring issue insights | Analytics | IT, IT Admin | **Tidak memakai AI** — analisis berbasis data database (Phase 7) |

Hasil klasifikasi dapat **diisi ke form Klasifikasi** dan draf dapat **dimasukkan ke form balasan**; IT tetap menekan
Simpan/Kirim sendiri.

## 2. Mengaktifkan

1. Isi `.env`:
   ```
   AI_PROVIDER=anthropic            # atau openai_compatible
   AI_API_KEY="..."                 # rahasia — hanya di .env
   AI_MODEL=                        # kosong = default provider (anthropic: claude-opus-5-5)
   AI_BASE_URL=                     # opsional (proxy / endpoint kompatibel)
   AI_TIMEOUT=30
   AI_MAX_OUTPUT_TOKENS=1200
   AI_RATE_PER_USER_PER_HOUR=30
   AI_DAILY_LIMIT=300
   ```
2. `php artisan config:cache` (production).
3. IT Admin → **System Settings** → aktifkan **Asisten AI**.

AI aktif hanya bila **setting aktif DAN konfigurasi lengkap**. Bila tidak, panel AI menampilkan alasan
(mis. "API key AI belum dikonfigurasi") dan semua endpoint AI mengembalikan pesan yang jelas, tanpa error 500.

### Pilihan model & biaya
Default Anthropic adalah `claude-opus-5-5` (paling mampu). Untuk menekan biaya, isi `AI_MODEL` dengan model yang lebih
ringan, mis. `claude-sonnet-5-5` atau `claude-haiku-5-5`. Untuk `openai_compatible` (OpenAI, OpenRouter, LLM lokal,
gateway perusahaan), `AI_MODEL` wajib diisi dan `AI_BASE_URL` menunjuk ke `.../v1`.

### Shared hosting
Panggilan AI adalah request HTTPS keluar dari PHP (Laravel HTTP client, sinkron). Pastikan hosting mengizinkan
koneksi keluar ke host provider (port 443). Timeout dibatasi 5–60 detik (default 30), di bawah `max_execution_time` yang umum.

## 3. Arsitektur

```
Controller (TicketAiController / KnowledgeBaseAiController) — authorize + validasi
  └─ TicketAiService / KnowledgeBaseAiService — susun prompt & validasi hasil
       ├─ AiContextBuilder — data minimal, redaksi, pembungkus data tak tepercaya
       ├─ KnowledgeBaseService — retrieval artikel (scope readableBy)
       └─ AiGateway — status aktif, rate limit, batas harian, timeout, error, log ai_requests
            └─ AiProvider (interface)
                 ├─ AnthropicProvider        POST {base}/v1/messages
                 ├─ OpenAiCompatibleProvider POST {base}/chat/completions
                 └─ FakeAiProvider           (automated test, tanpa API berbayar)
```
Menambah provider baru: implementasikan `App\Services\Ai\AiProvider` dan daftarkan di `AiManager::makeProvider()`
serta `config/ai.php` → `providers`.

## 4. Keamanan & Privasi

| Kontrol | Implementasi |
|---|---|
| Server-side saja | Browser memanggil endpoint aplikasi; API key tidak pernah ke frontend/HTML (diuji) |
| API key | Hanya `.env`; tidak disimpan di DB, tidak di log; error provider dicatat sebagai kode (`http_401`, `connection`), bukan pesan mentah |
| Tanpa aksi | AI hanya menghasilkan teks/JSON; tidak ada tool/function calling, eksekusi perintah, atau SQL |
| Tanpa perubahan otomatis | Endpoint AI tidak menulis ke tiket (diuji: prioritas/kategori/status/balasan tidak berubah) |
| Prompt injection | Konten tiket & artikel dibungkus `<data>` sebagai data tak tepercaya; tag penutup palsu dinetralkan; system prompt melarang mengikuti instruksi di dalam data |
| Data minimal | Nama pelapor tidak dikirim; email, nomor telepon, `password/pin/token: …`, API key, dan hex panjang diredaksi |
| Catatan internal | Tidak dikirim untuk klasifikasi, troubleshooting, dan draf balasan; hanya untuk ringkasan yang hasilnya khusus IT |
| Akses KB | Hanya artikel **Published** yang boleh dibaca peminta (`readableBy`); draft & arsip tidak pernah dikirim (diuji) |
| Referensi tidak dikarang | ID artikel dari jawaban model divalidasi terhadap artikel yang benar-benar dikirim; status "berdasarkan KB" ditentukan server |
| Validasi output | JSON diparse & dibatasi panjangnya; kategori/subkategori/prioritas dicocokkan ke data master; nilai tidak valid dibuang |
| Role | Hanya IT & IT Admin (USER 403, diuji) |
| Rate limit | Per user per jam (`AI_RATE_PER_USER_PER_HOUR`), batas harian organisasi (`AI_DAILY_LIMIT`), throttle route 20/menit |
| Log penggunaan | Tabel `ai_requests`: user, fitur, provider, model, status, token, durasi, kode error. **Tanpa isi prompt/jawaban** |

## 5. Penanganan Error

| Kondisi | Pesan ke pengguna | `ai_requests.status` / `error_code` |
|---|---|---|
| AI nonaktif / konfigurasi kurang | Alasan spesifik | `disabled` |
| Timeout / jaringan | "Tidak dapat terhubung ke layanan AI…" | `error` / `connection` |
| 401/403 | "Konfigurasi API key AI ditolak…" | `error` / `http_401` |
| 429 provider / limit lokal | "…membatasi permintaan" / "Batas penggunaan AI…" | `error`/`http_429` atau `rate_limited` |
| 5xx | "Layanan AI sedang gangguan…" | `error` / `http_5xx` |
| Jawaban bukan JSON | "Jawaban AI tidak dapat diproses…" | `invalid_response` |

## 6. Pengujian

Automated test memakai `FakeAiProvider` dan `Http::fake()`, sehingga tidak ada panggilan API berbayar.
Lihat `tests/Feature/Ai/*`, `tests/Unit/AiRedactionTest.php`, dan TESTING_REPORT.md.
