# SYSTEM ARCHITECTURE — ALMEGA IT TICKETING SYSTEM

## 1. Gaya Arsitektur

**Modular monolith** Laravel, satu aplikasi dan satu database, dirancang untuk **shared hosting**:
tidak ada proses permanen, semua state di MySQL, dan pekerjaan terjadwal lewat satu Cron Job.

```
Browser (HP / Desktop)
   │  HTTPS
   ▼
public/index.php ── Middleware web ── Route ── Controller / Livewire
                    (session, CSRF,           │
                     SecurityHeaders,         ├─ Form Request (validasi + authorize)
                     auth, active,            ├─ Policy / Gate (otorisasi)
                     password.changed,        └─ Service Layer (business logic, DB::transaction)
                     role:…)                          │
                                                      ├─ Eloquent Models ── MySQL (InnoDB, utf8mb4)
                                                      └─ AuditService ──── audit_logs (append-only)
Cron (tiap menit) ── artisan schedule:run ── queue:work --stop-when-empty (antrean database)
```

## 2. Lapisan

| Lapisan | Lokasi | Tanggung jawab |
|---|---|---|
| Routing | `routes/web.php` | Grup per role: `user.*`, `it.*`, `admin.*` |
| Middleware | `app/Http/Middleware` | `EnsureRole` (`role:`), `EnsureUserIsActive` (`active`), `EnsurePasswordChanged` (`password.changed`), `SecurityHeaders` |
| Validasi | `app/Http/Requests` | Form Request untuk setiap input; pesan Bahasa Indonesia (`lang/id`) |
| Otorisasi | `app/Policies`, Gate di `AppServiceProvider` | `UserPolicy`, `UnitPolicy`, `AuditLogPolicy`; Gate `access-it`, `access-admin` |
| Business logic | `app/Services` | `UserService`, `UnitService`, `AuditService`, `SettingService` (+ Ticket*/Analytics/Report di sprint berikutnya) |
| Model | `app/Models` | `Role`, `Unit`, `User`, `AuditLog` (append-only), `Setting` |
| Enum | `app/Enums/RoleCode` | USER / IT / IT_ADMIN dengan level hirarki |
| Navigasi | `app/Support/Navigation` | Menu per role; item muncul otomatis saat route modul tersedia |
| View | `resources/views` | `layouts/` (app, guest), `components/`, `auth/`, `user/`, `it/`, `admin/`, `errors/` |

## 3. Otorisasi Berlapis

1. **Route middleware** `role:IT_ADMIN` dsb. — request dari role yang salah langsung 403.
2. **Form Request `authorize()`** memanggil Policy (`can('update', $user)`).
3. **Controller `authorize()`** untuk aksi baca (index/edit).
4. **Service guard** untuk aturan bisnis (mis. admin tidak bisa menonaktifkan dirinya / admin aktif terakhir).
5. Menu yang disembunyikan di UI hanyalah pelengkap, bukan pengaman.

## 4. Konvensi Penting

- `User::$with = ['role']` — role selalu tersedia untuk otorisasi tanpa query tambahan per pemeriksaan.
- `Model::preventLazyLoading()` aktif di luar production untuk mendeteksi N+1 sejak dini.
- Layout dipakai sebagai anonymous component: `<x-layouts::app title="...">`.
- Timezone aplikasi dari `APP_TIMEZONE` (default `Asia/Jakarta`; `Asia/Makassar` untuk WITA).
- Seluruh driver (session, cache, queue) = `database`.

## 5. Keputusan Teknis (ADR ringkas)

| Keputusan | Alasan |
|---|---|
| Login memakai username (email opsional) | Staf unit tidak semua memiliki email |
| `user_units` many-to-many + `is_primary` | Staf dapat menangani lebih dari satu unit; unit utama dipakai sebagai default form |
| Controller + Form Request untuk CRUD admin; Livewire untuk bagian interaktif (sprint berikutnya) | CRUD tetap berfungsi tanpa JS dan mudah diuji; Livewire dipakai di form tiket, antrean, dan notifikasi |
| Audit log append-only di level model | Mencegah perubahan/hapus walau ada bug di controller |
| Sesi diputus dengan menghapus baris `sessions` | `Auth::logoutOtherDevices` membutuhkan middleware `AuthenticateSession`; penghapusan langsung lebih pasti pada driver database |
| Tidak memakai Laravel Breeze/Jetstream | Menghindari fitur registrasi publik / reset email yang tidak diminta |

## 6. Struktur Folder (Sprint 1)

```
app/
├── Enums/RoleCode.php
├── Http/Controllers/{Auth,Admin,User,It}/
├── Http/Middleware/
├── Http/Requests/{Auth,Admin}/
├── Models/
├── Policies/
├── Services/
└── Support/Navigation.php
config/almega.php
database/{migrations,seeders,factories}/
lang/id/
resources/views/{layouts,components,auth,user,it,admin,errors}/
tests/{Feature,Unit}/
```
