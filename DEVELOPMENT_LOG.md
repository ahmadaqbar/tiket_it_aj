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
