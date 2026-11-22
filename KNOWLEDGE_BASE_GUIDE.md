# KNOWLEDGE BASE GUIDE

Knowledge Base (KB) menyimpan dokumentasi troubleshooting yang dapat dipakai teknisi IT, pengguna (pusat bantuan),
dan — bila AI diaktifkan — sebagai konteks jawaban AI.

## 1. Akses

| Role | Membaca | Menulis / Publish / Archive |
|---|---|---|
| USER | Hanya artikel **Published + Publik**, dan hanya bila setting `kb.public_enabled` aktif (menu **Bantuan**) | ✗ |
| IT, IT Admin | Semua artikel (Draft, Published, Archived; Internal & Publik) | ✓ |

Artikel yang tidak boleh dibaca menghasilkan **404** (keberadaannya tidak diungkap).

## 2. Struktur Artikel

| Field | Wajib | Keterangan |
|---|---|---|
| Judul | ✓ | Slug URL dibuat otomatis & stabil (tidak berubah saat judul diedit) |
| Kategori / Subkategori | | Memakai kategori tiket yang sama (satu taksonomi) |
| Deskripsi Masalah | ✓ | |
| Gejala, Akar Masalah | | |
| Langkah Troubleshooting | ✓ | Tulis bernomor, satu langkah per baris |
| Resolusi | | |
| Tag | | Dipisah koma, mis. `printer, epson, kasir` |
| Visibilitas | ✓ | **Internal IT** atau **Publik (User)** |
| Status | | Draft → Published → Archived (dapat dikembalikan ke Draft) |

Jangan menulis password atau data rahasia di artikel. Gunakan catatan internal tiket untuk itu.

## 3. Alur Kerja

1. **Knowledge Base → Artikel Baru** (atau dari detail tiket: **Jadikan artikel**. Judul, kategori, deskripsi, dan balasan publik terakhir IT terisi otomatis, tanpa catatan internal).
2. Simpan sebagai **Draft**, tinjau, lalu **Publikasikan**.
3. Artikel usang → **Arsipkan** (tidak dihapus; hilang dari pencarian user dan konteks AI).

Semua aksi tercatat di Audit Log (`kb.created`, `kb.updated`, `kb.published`, `kb.unpublished`, `kb.archived`).

## 4. Pencarian

- MySQL **FULLTEXT** (InnoDB, natural language) pada judul, masalah, gejala, langkah, resolusi, diurutkan berdasarkan relevansi.
- Bila tidak ada hasil (kata < 3 huruf, dsb.), fallback ke pencarian `LIKE` per kata + tag.
- Filter: kategori, tag; IT juga status & visibilitas.
- Tidak memakai vector database. Semantic search dapat ditambahkan kemudian tanpa mengubah struktur artikel.

## 5. Artikel Terkait Tiket

Di detail tiket IT, panel **Artikel KB Terkait** menampilkan artikel Published yang dapat dibaca:
subkategori sama → kategori sama → kecocokan teks judul tiket.

## 6. KB sebagai Konteks AI

Lihat [AI_INTEGRATION_GUIDE.md](AI_INTEGRATION_GUIDE.md). AI hanya menerima artikel dari scope `readableBy()`:
**Published** dan boleh dibaca oleh pengguna yang meminta. Draft dan arsip tidak pernah dikirim ke AI.

## 7. Statistik

Jumlah dilihat dihitung sekali per sesi per artikel; tampilan oleh penulis tidak dihitung.
