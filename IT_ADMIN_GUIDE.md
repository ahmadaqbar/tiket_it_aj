# PANDUAN IT & IT ADMIN

## 1. Level Akses
| Role | Kemampuan |
|---|---|
| **User** | Membuat & memantau tiket sendiri, Pusat Bantuan |
| **IT** | Semua tiket seluruh unit, balasan & catatan internal, status, PIC, klasifikasi, Knowledge Base (tulis), Asisten AI, Analytics, Laporan, export |
| **IT Admin** | Semua hak IT + User, Unit, Kategori, SLA, QR, System Settings, Audit Log |
Hak akses diperiksa di server (menu hanya pelengkap). Akses tanpa hak → 403/404.

## 2. Menangani Tiket
- **Semua Tiket** = antrean bersama seluruh unit; menu Tiket Baru/Dalam Proses/Pending/Resolved/Closed = filter status.
- Filter: unit, kategori, subkategori, prioritas, PIC (*Belum ada PIC*, *Tiket saya*), pelapor, tanggal, *Melewati SLA*; urutan terbaru/terlama/prioritas/aktivitas/batas SLA. **Export Excel** sesuai filter.
- **Ambil** = jadikan diri sendiri PIC. PIC opsional.
- **Balasan Publik** terlihat pelapor; **Catatan Internal** (kuning) hanya IT — gunakan untuk data sensitif.
- **Template balasan cepat** mengisi kolom pesan (dapat diedit sebelum dikirim).
- **Ubah Status**: hanya transisi valid; **Resolved wajib catatan penyelesaian**; Closed final (catatan internal tetap bisa).
- **Klasifikasi**: ubah prioritas/kategori; prioritas baru menghitung ulang SLA.
- **Artikel KB Terkait** & **Jadikan artikel** ada di detail tiket.
- **Asisten AI** (bila aktif): Klasifikasi → *Isi ke form*; Troubleshooting (rujukan KB); Ringkasan; Draf balasan → *Masukkan ke form balasan*. Selalu periksa sebelum menyimpan/mengirim.

## 3. Notifikasi
Tiket baru → semua IT aktif. Balasan pelapor / buka kembali → PIC (atau semua IT bila tanpa PIC). Ditetapkan sebagai PIC → Anda.

## 4. Knowledge Base
Lihat [KNOWLEDGE_BASE_GUIDE.md](KNOWLEDGE_BASE_GUIDE.md). **Tanya AI** di halaman KB (bila AI aktif).

## 5. Analytics & Laporan
- **Analytics**: periode (hari ini … tahun ini, bulan tertentu, kustom) & unit. Definisi metrik ada di DATABASE_DESIGN.md §6.
- **Isu berulang**: ≥ 3 tiket unit + kategori + subkategori sama dalam periode, dengan saran pencegahan & penanda artikel KB.
- **Laporan**: Operasional (daftar tiket, respons IT, durasi) atau Manajemen (ringkasan, statistik, tren, aktivitas IT, isu berulang, rekomendasi), PDF/Excel. Laporan bulanan: Periode → *Bulan Tertentu*. PDF operasional maks. 500 tiket — gunakan Excel untuk data lengkap. Catatan internal tidak pernah masuk laporan.

## 6. Administrasi (IT Admin)
| Menu | Catatan |
|---|---|
| **User Management** | Username huruf kecil; role User wajib ≥1 unit (pilih unit utama); password sementara wajib diganti; nonaktif = sesi diputus; reset password; admin aktif terakhir dilindungi |
| **Unit Management** | Kode unit dipakai di nomor tiket & terkunci setelah ada tiket; unit tidak dihapus, hanya nonaktif |
| **Category Management** | Nonaktif = hilang dari form; hapus hanya bila belum pernah dipakai |
| **SLA Settings** | Respons & penyelesaian (menit) per prioritas; berlaku untuk tiket baru |
| **QR Management** | Preview, PNG, PDF (per unit / semua), cetak, regenerate token (QR lama tidak berlaku — cetak ulang) |
| **System Settings** | Branding (nama, organisasi, kontak, warna, logo), prefix nomor tiket, auto-close, batas lampiran, Pusat Bantuan, Asisten AI (+ status & token), template balasan cepat, **Jalankan Tugas Terjadwal** (hosting tanpa Cron) |
| **Audit Log** | Hanya baca; filter aksi/user/entitas/tanggal |

## 7. Operasional Rutin
- Harian: antrean *Melewati SLA*, *Belum ada PIC*; Audit Log `auth.login_failed` berulang.
- Mingguan: Analytics → isu berulang → buat artikel KB / jadwalkan maintenance.
- Bulanan: Laporan Manajemen bulan lalu; tinjau user nonaktif; uji restore backup tiap 3 bulan.
