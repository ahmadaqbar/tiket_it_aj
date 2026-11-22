# PANDUAN IT & IT ADMIN — ALMEGA IT TICKETING SYSTEM

> Mengikuti implementasi aktual. Bagian ⏳ menyusul pada sprint berikutnya.

## 1. Level Akses

| Role | Kemampuan (saat ini) |
|---|---|
| **User** | Dashboard user, profil, ganti password |
| **IT** | Dashboard IT (⏳ antrean tiket, balasan, analytics, laporan) |
| **IT Admin** | Semua kemampuan IT + User Management, Unit Management, Audit Log (⏳ kategori, SLA, QR, pengaturan) |

Hak akses diperiksa di server. Membuka URL admin dengan akun non-admin menghasilkan **403 Akses Ditolak**.

## 2. Akun IT Admin Awal
Dibuat oleh seeder dari `.env` (`INITIAL_ADMIN_USERNAME`, `INITIAL_ADMIN_PASSWORD`).
Saat login pertama wajib ganti password. Setelah itu, kosongkan `INITIAL_ADMIN_PASSWORD` di `.env` production.

## 3. User Management (`Administrasi → User Management`)
- **Cari/filter**: nama/username/email, role, unit, status.
- **Tambah User**: nama, username (huruf kecil, angka, `.`, `-`, `_`), email & HP opsional, role, password sementara.
  - Role **User** wajib memiliki minimal satu unit; tandai satu sebagai **Utama** (otomatis terisi saat membuat laporan).
  - Role IT/IT Admin boleh tanpa unit (melihat semua unit).
  - User baru **wajib mengganti password** saat login pertama.
- **Ubah User**: data, role, unit, status aktif. Password **tidak** diubah dari form ini.
- **Nonaktifkan**: hapus centang *Akun aktif*. User langsung keluar pada request berikutnya dan tidak bisa login.
  User **tidak dihapus** agar riwayat tiket tetap utuh.
- **Reset Password** (panel kanan halaman ubah): sesi aktif user diputus, user wajib ganti password lagi.
- Pengaman: Anda tidak dapat menonaktifkan/menurunkan role akun sendiri, dan IT Admin aktif terakhir tidak dapat dinonaktifkan.

## 4. Unit Management (`Administrasi → Unit Management`)
- 15 unit awal dibuat oleh seeder (HO, MK, GK, KMC, K57, DR, TS, SCN, O2D, MMJ, PMC, POV, OMA, SH, STZ).
- **Tambah/Ubah**: kode (huruf kapital & angka, maks. 10), nama, keterangan, urutan, status aktif.
- **Kode unit** dipakai di nomor tiket (`IT-{KODE}-{YYYYMMDD}-{NNN}`) dan **terkunci** setelah unit memiliki tiket.
- Unit **tidak dapat dihapus**. Gunakan status nonaktif: unit tidak bisa dipilih untuk tiket baru dan QR-nya ditolak.
- Setiap unit memiliki token QR acak (⏳ halaman QR Management: generate, unduh PNG/PDF, cetak, regenerate — Phase 1d).

## 5. Category Management (`Administrasi → Category Management`)
- 8 kategori & 39 subkategori awal dari seeder.
- Tambah kategori di form atas; klik nama kategori untuk melihat/mengubah subkategori.
- **Nonaktif**: tidak muncul di form Buat Laporan; tiket lama tetap utuh.
- **Hapus** hanya tersedia bila kategori/subkategori belum pernah dipakai tiket.

## 6. SLA Settings (`Administrasi → SLA Settings`)
- Target **respons** dan **penyelesaian** (menit) per prioritas. Default: Mendesak 30/240, Tinggi 60/480, Sedang 240/1440, Rendah 480/4320.
- Perubahan hanya berlaku untuk **tiket baru**; tiket lama menyimpan batas waktunya sendiri.
- SLA nonaktif → tiket prioritas tersebut tidak memiliki batas waktu.

## 7. Audit Log (`Administrasi → Audit Log`)
Hanya baca. Filter: aksi, user, entitas, rentang tanggal. Klik **detail** untuk melihat nilai sebelum/sesudah.

Aksi yang dicatat saat ini:
`auth.login`, `auth.logout`, `auth.login_failed`, `auth.login_blocked`, `auth.password_changed`,
`user.created`, `user.updated`, `user.units_changed`, `user.activated`, `user.deactivated`, `user.password_reset`,
`user.profile_updated`, `unit.created`, `unit.updated`, `unit.qr_regenerated`, `ticket.created`,
`category.created|updated|deleted`, `subcategory.created|updated|deleted`, `sla.updated`.

Password, token QR, dan remember token **tidak pernah** disimpan di audit log.

## 8. Pengaturan Sistem (tabel `settings`)
Nilai awal (UI ⏳ Phase 6): `ticket.number_prefix = IT`, `ticket.auto_close_days = 3`, `upload.max_kb = 5120`, `upload.max_files = 5`.

## 9. Operasional Rutin
- Harian: cek Audit Log untuk `auth.login_failed` berulang dari IP yang sama.
- Bulanan: tinjau user nonaktif / user yang belum pernah login.
- Backup: lihat [BACKUP_RESTORE_GUIDE.md](BACKUP_RESTORE_GUIDE.md).
