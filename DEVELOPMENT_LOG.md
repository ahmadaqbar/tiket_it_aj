# DEVELOPMENT LOG

Catatan kronologis keputusan dan pekerjaan. Entri terbaru di bawah.

## 2026-11-14 — Sprint 1 (Foundation)
- Laravel 13 + Livewire 4 + Tailwind 4, MariaDB 10.11 lokal. Auth, 3 role, user/unit management, audit log.
- 76 test lulus. Commit `cd915f4`.

## 2026-11-22 — Phase 0: Audit lanjutan (productization)
- Spesifikasi lanjutan menganggap modul tiket/QR/analytics/laporan/notifikasi sudah ada → **tidak benar**;
  hanya Sprint 1 yang ada. Detail: `PROJECT_AUDIT.md`.
- Baseline: 76/76 test lulus, 5 migration ter-apply, remote = lokal, working tree bersih.
- Dibuat: `PROJECT_AUDIT.md`, `PROJECT_STATUS.md`, `PRODUCTIZATION_PLAN.md`, log ini.
- Keputusan: core ticketing dibangun lebih dulu (Phase 1b–1d) karena fitur AI tiket bergantung padanya.

## 2026-11-22 — Phase 1a: BUG-01
- Gejala: edit user/unit nonaktif tanpa perubahan status → audit `user.updated` (`is_active 0 → false`)
  + `user.deactivated` palsu + pemutusan sesi tak perlu.
- Penyebab: perbandingan `(string)` di `AuditService::logChanges()`.
- Perbaikan: pakai `Model::getChanges()`. Regression test terbukti gagal di kode lama, lulus setelah perbaikan.
- Catatan test: payload update user harus menyertakan `email`; bila tidak, email memang dikosongkan (perilaku form yang benar).

## 2026-11-22 — Phase 1b: Core Ticketing
- Migration aditif (categories, subcategories, sla_policies, ticket_sequences, tickets, ticket_attachments,
  ticket_histories) dijalankan inkremental di DB dev berisi data → sukses.
- Keputusan:
  - Nomor tiket: format tetap; prefix dibaca dari setting `ticket.number_prefix` (default `IT`) untuk Edisi Produk.
  - URL tiket memakai `public_id` (ULID); akses tiket orang lain → **404** (tidak membocorkan keberadaan).
  - Pembuatan laporan khusus role USER dengan unit aktif; IT membuat tiket atas nama user belum termasuk (tidak diminta).
  - "Tiket Saya" = semua kecuali CLOSED (RESOLVED tetap tampil karena menunggu konfirmasi); "Riwayat" = CLOSED.
  - Form memakai Blade + Alpine (bukan Livewire upload) agar upload berjalan tanpa endpoint temporary Livewire.
  - `ticket_replies` ditunda ke Phase 1c; `ticket_attachments.ticket_reply_id` disiapkan tanpa FK.
  - Disk `local` `serve => false`.
- Temuan saat test: `UploadedFile::fake()` tidak mendeteksi MIME dari isi → test keamanan memakai file nyata.
- Temuan UI: ukuran lampiran < 1 KB tampil "0 KB" → dibulatkan ke atas.
- Hasil: 120/120 test lulus; smoke test Playwright (mobile user & desktop admin) tanpa error JS.

## 2026-11-22 — Phase 1c: IT Management
- Migration aditif (ticket_replies, ticket_assignments, quick_reply_templates, FK reply pada lampiran) dijalankan inkremental di DB dev → sukses.
- Keputusan:
  - Satu pintu visibilitas: `TicketReply::visibleTo()` & `TicketHistory::visibleTo()`; controller pelapor tidak pernah memuat data internal.
  - Role USER yang mengirim `visibility=INTERNAL` diperlakukan sebagai PUBLIC (tidak ada data internal tercipta).
  - RESOLVED wajib catatan penyelesaian (bahan ringkasan AI & laporan kelak). Pesan status dikirim sebagai balasan publik.
  - CLOSED final; IT tetap dapat menambah catatan internal pada tiket Closed untuk dokumentasi.
  - Reopen mengosongkan `resolved_at` agar analytics tidak menghitung tiket yang belum selesai.
  - Perubahan prioritas menghitung ulang SLA dari `created_at`.
  - Export antrean memakai openspout ke file temp lalu dihapus setelah dikirim (hemat memori, cocok shared hosting).
  - Kelola template quick reply via UI ditunda ke Phase 6 (System Settings) agar tidak menambah menu baru.
- Hasil: 155/155 test; smoke test end-to-end IT ↔ user tanpa error JS.

## 2026-11-22 — Phase 1d: QR & Notifikasi
- QR: token tetap di `units.qr_token` (Sprint 1). Guest → `redirect()->guest(login)` sehingga setelah login kembali ke URL QR.
- Notifikasi dikirim **sinkron** via channel database (tanpa queue) agar tidak bergantung Cron/worker.
- Satu-satunya `{!! !!}` baru: SVG QR hasil library dari URL internal (bukan input user).
- Temuan test: lonceng di layout memuat nomor tiket → test filter antrean diubah memeriksa data paginator.
- Browser: alur scan QR → login → laporan (IT-DR-20261122-002), lonceng IT menampilkan notifikasi, request Livewire update HTTP 200.
- Hasil: 176/176 test.
