# DATABASE DESIGN — ALMEGA IT TICKETING SYSTEM

- Nama database: `almega_it_ticketing`
- Engine: **InnoDB**, charset **utf8mb4** / collation `utf8mb4_unicode_ci`
- Seluruh perubahan skema melalui **Laravel migration**; data awal melalui **seeder**.
- Kompatibel MySQL 8.0+ dan MariaDB 10.6+.

> Status implementasi per tabel dicatat pada kolom **Sprint**. Tabel ditambahkan pada sprint yang
> membutuhkannya agar setiap migration teruji bersama fiturnya.

## 1. Daftar Tabel

| # | Tabel | Fungsi | Sprint |
|---|---|---|---|
| 1 | `roles` | 3 level akses: USER, IT, IT_ADMIN | 1 |
| 2 | `units` | 15 unit bisnis + token QR | 1 |
| 3 | `users` | Akun pengguna (tanpa registrasi publik) | 1 |
| 4 | `user_units` | Relasi user ↔ unit (multi-unit, satu unit utama) | 1 |
| 5 | `categories` | Kategori tiket | 2 ✅ |
| 6 | `subcategories` | Subkategori tiket | 2 ✅ |
| 7 | `ticket_sequences` | Penomoran tiket per unit per hari | 2 ✅ |
| 8 | `tickets` | Tiket | 2 ✅ |
| 9 | `ticket_replies` | Percakapan (PUBLIC / INTERNAL) | 3 (Phase 1c) |
| 10 | `ticket_attachments` | Lampiran (private storage) | 2 ✅ (FK `ticket_reply_id` ditambahkan Phase 1c) |
| 11 | `ticket_histories` | Riwayat aktivitas tiket | 2 ✅ |
| 12 | `ticket_assignments` | Riwayat assignment PIC | 3 |
| 13 | `sla_policies` | Target respons & penyelesaian per prioritas | 2 ✅ |
| 14 | `notifications` | Laravel database notification | 4 |
| 15 | `audit_logs` | Jejak audit | 1 |
| + | `settings` | Konfigurasi aplikasi (key-value) | 1 |
| + | `quick_reply_templates` | Template balasan cepat IT | 3 |
| + | `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Infrastruktur Laravel (driver database, tanpa Redis) | 1 |

## 2. Diagram Relasi (ringkas)

```
roles 1───* users *───* units           (via user_units, is_primary)
units 1───* tickets *───1 users (pelapor: user_id)
                 *───1 users (PIC: assigned_to, nullable)
                 *───1 categories ───* subcategories
                 *───1 subcategories (nullable)
                 *───1 sla_policies (nullable)
tickets 1───* ticket_replies 1───* ticket_attachments
tickets 1───* ticket_attachments
tickets 1───* ticket_histories
tickets 1───* ticket_assignments
units 1───* ticket_sequences  (UNIQUE unit_id + ticket_date)
users 1───* audit_logs
```

## 3. Definisi Tabel

### 3.1 `roles`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| code | VARCHAR(20) UNIQUE | `USER`, `IT`, `IT_ADMIN` (dipakai di kode sebagai konstanta) |
| name | VARCHAR(50) | Label tampilan |
| description | VARCHAR(255) NULL | |
| level | TINYINT UNSIGNED | 1 = USER, 2 = IT, 3 = IT_ADMIN (hirarki) |
| timestamps | | |

### 3.2 `units`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| code | VARCHAR(10) UNIQUE | Kode untuk nomor tiket (`HO`, `DR`, …) — huruf besar & angka |
| name | VARCHAR(100) | |
| description | VARCHAR(255) NULL | |
| qr_token | CHAR(48) UNIQUE | Token acak kriptografis untuk URL `/qr/{token}` |
| qr_token_generated_at | TIMESTAMP NULL | |
| is_active | BOOLEAN default 1 | Unit nonaktif tidak bisa dipilih & QR ditolak |
| sort_order | SMALLINT UNSIGNED | |
| timestamps | | |

Unit **tidak dihapus**; cukup dinonaktifkan agar data historis tetap utuh.
Kode unit **tidak dapat diubah** setelah dipakai di tiket (nomor tiket memuat kode unit).

### 3.3 `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| role_id | FK → roles.id | RESTRICT on delete |
| name | VARCHAR(100) | |
| username | VARCHAR(50) UNIQUE | Login utama (staf unit tidak semua punya email) |
| email | VARCHAR(150) UNIQUE NULL | Login alternatif |
| phone | VARCHAR(30) NULL | |
| password | VARCHAR(255) | bcrypt |
| is_active | BOOLEAN default 1 | Akun nonaktif tidak bisa login & sesi aktif diputus |
| must_change_password | BOOLEAN default 1 | Dipaksa ganti password setelah dibuat/reset admin |
| last_login_at | TIMESTAMP NULL | |
| last_login_ip | VARCHAR(45) NULL | |
| remember_token | VARCHAR(100) NULL | |
| timestamps, deleted_at | | Soft delete (data historis tiket tetap merujuk user) |

Index: `role_id`, `is_active`.

### 3.4 `user_units`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| user_id | FK → users.id CASCADE | |
| unit_id | FK → units.id RESTRICT | |
| is_primary | BOOLEAN | Tepat satu unit utama per user (dijaga di service) |
| timestamps | | |

UNIQUE (`user_id`, `unit_id`). Role IT / IT_ADMIN boleh tanpa unit (melihat semua unit).

### 3.5 `categories`
| id | name VARCHAR(100) UNIQUE | description NULL | sort_order | is_active | timestamps |

### 3.6 `subcategories`
| id | category_id FK RESTRICT | name VARCHAR(100) | sort_order | is_active | timestamps |
UNIQUE (`category_id`, `name`). Kategori/subkategori yang sudah dipakai tiket **tidak boleh dihapus** (FK RESTRICT + validasi), hanya dinonaktifkan.

### 3.7 `ticket_sequences`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| unit_id | FK → units.id | |
| ticket_date | DATE | Tanggal lokal (timezone aplikasi) |
| last_number | INT UNSIGNED | Nomor terakhir yang terpakai |
| timestamps | | |

UNIQUE (`unit_id`, `ticket_date`).

### 3.8 `sla_policies`
| id | priority ENUM-like VARCHAR(10) UNIQUE | name | response_minutes INT | resolution_minutes INT | is_active | timestamps |

Default seeder: LOW 480/4320, MEDIUM 240/1440, HIGH 60/480, URGENT 30/240 (menit; dapat diubah IT Admin).

### 3.9 `tickets`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED PK | Internal, tidak tampil di URL |
| public_id | CHAR(26) UNIQUE | ULID, dipakai di URL |
| ticket_number | VARCHAR(40) UNIQUE | `IT-{UNIT}-{YYYYMMDD}-{SEQ}` |
| user_id | FK → users.id | Pelapor |
| unit_id | FK → units.id | |
| category_id | FK → categories.id | |
| subcategory_id | FK NULL → subcategories.id | |
| title | VARCHAR(150) | |
| description | TEXT | |
| priority | VARCHAR(10) | LOW / MEDIUM / HIGH / URGENT (default MEDIUM) |
| status | VARCHAR(15) | NEW / IN_PROGRESS / PENDING / RESOLVED / CLOSED |
| assigned_to | FK NULL → users.id | PIC IT (opsional) |
| source | VARCHAR(10) | WEB / QR |
| sla_policy_id | FK NULL | Snapshot kebijakan saat tiket dibuat/prioritas diubah |
| response_due_at | TIMESTAMP NULL | Batas respons SLA |
| resolution_due_at | TIMESTAMP NULL | Batas penyelesaian SLA |
| first_response_at | TIMESTAMP NULL | Respons publik / perubahan status pertama oleh IT |
| resolved_at | TIMESTAMP NULL | Waktu RESOLVED terakhir |
| closed_at | TIMESTAMP NULL | |
| reopened_count | SMALLINT UNSIGNED default 0 | |
| timestamps, deleted_at | | |

Index: `status`, `priority`, (`unit_id`, `created_at`), (`category_id`, `created_at`), `assigned_to`, `created_at`, `user_id`.

### 3.10 `ticket_replies`
| id | ticket_id FK CASCADE | user_id FK | message TEXT | visibility VARCHAR(10) `PUBLIC`/`INTERNAL` | timestamps |
Index (`ticket_id`, `visibility`, `created_at`). **Query untuk role USER selalu difilter `visibility = PUBLIC`.**

### 3.11 `ticket_attachments`
| id | ticket_id FK | ticket_reply_id FK NULL | user_id FK | original_name | stored_path | mime_type | size_bytes | visibility | timestamps |
File disimpan di `storage/app/private/attachments/{Y}/{m}/{random}.{ext}` — **tidak** di folder publik. Diunduh melalui route yang memeriksa Policy.

### 3.12 `ticket_histories`
| id | ticket_id FK CASCADE | user_id FK NULL | action VARCHAR(40) | old_value VARCHAR(255) NULL | new_value VARCHAR(255) NULL | note TEXT NULL | created_at |
Action: `CREATED`, `STATUS_CHANGED`, `PRIORITY_CHANGED`, `CATEGORY_CHANGED`, `ASSIGNED`, `UNASSIGNED`, `REPLIED`, `INTERNAL_NOTE`, `ATTACHMENT_ADDED`, `REOPENED`, `CONFIRMED`, `AUTO_CLOSED`.
Append-only (tanpa `updated_at`).

### 3.13 `ticket_assignments`
| id | ticket_id FK | assigned_to FK users | assigned_by FK users | note NULL | unassigned_at NULL | created_at |

### 3.14 `notifications`
Skema standar Laravel (`uuid id`, `type`, `notifiable_type/id`, `data JSON`, `read_at`, timestamps).

### 3.15 `audit_logs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| user_id | FK NULL → users.id (SET NULL) | Aktor |
| action | VARCHAR(50) | `auth.login`, `auth.logout`, `auth.login_failed`, `user.created`, `unit.updated`, `ticket.created`, … |
| entity_type | VARCHAR(100) NULL | Nama model, mis. `User`, `Unit`, `Ticket` |
| entity_id | BIGINT UNSIGNED NULL | |
| description | VARCHAR(255) NULL | |
| old_values | JSON NULL | Tanpa password/token |
| new_values | JSON NULL | |
| ip_address | VARCHAR(45) NULL | |
| user_agent | VARCHAR(255) NULL | |
| created_at | TIMESTAMP | |

Index: `action`, (`entity_type`, `entity_id`), `created_at`, `user_id`.
**Append-only**: model menolak update/delete; tidak ada UI hapus.

### 3.16 `settings`
| id | key VARCHAR(100) UNIQUE | value TEXT NULL | type VARCHAR(20) (`string`/`int`/`bool`) | label VARCHAR(150) NULL | timestamps |
Contoh: `app.timezone`, `ticket.auto_close_days`, `upload.max_kb`.

### 3.17 `quick_reply_templates`
| id | title | message TEXT | sort_order | is_active | timestamps |

## 4. Penomoran Tiket

Format: `IT-{UNIT_CODE}-{YYYYMMDD}-{SEQ}` — contoh `IT-DR-20261114-001`.

Prefix `IT` berasal dari setting `ticket.number_prefix` (default `IT`, huruf kapital & angka, maks. 10) agar Edisi Produk dapat memakai prefix lain. Edisi Almega tetap `IT` — format tidak berubah.
Implementasi: `App\Services\TicketNumberService` (menolak dipanggil di luar transaksi).

Algoritma (`TicketNumberService`, di dalam `DB::transaction`):
1. `INSERT IGNORE`-style upsert baris (`unit_id`, `ticket_date`) bila belum ada.
2. `SELECT ... FOR UPDATE` baris tersebut (row lock InnoDB).
3. `last_number = last_number + 1`, simpan.
4. Bentuk nomor (SEQ minimal 3 digit, otomatis 4 digit bila > 999).
5. Insert tiket dalam transaksi yang sama → bila insert gagal, sequence ikut rollback.

`tickets.ticket_number` UNIQUE sebagai pengaman terakhir. Nomor tidak pernah dipakai ulang karena
sequence hanya naik dan tiket tidak di-hard-delete.

## 5. Aturan Status

```
NEW ──► IN_PROGRESS ◄──► PENDING
 │          │               │
 └──────────┴──► RESOLVED ◄─┘
                   │   ▲
          konfirmasi│   │reopen (user/IT, dengan alasan)
                   ▼   │
                 CLOSED ┘ (CLOSED final)
```
- Perubahan status hanya oleh IT/IT_ADMIN, kecuali: USER pemilik tiket dapat **konfirmasi** (RESOLVED→CLOSED) dan **reopen** (RESOLVED→IN_PROGRESS).
- Tiket RESOLVED otomatis CLOSED setelah `ticket.auto_close_days` hari tanpa respons user (scheduler).
- Setiap perubahan tercatat di `ticket_histories`.

## 6. Definisi Metrik Analytics

| Metrik | Definisi |
|---|---|
| Tiket masuk periode | `created_at` dalam rentang |
| Tiket selesai periode | `resolved_at` dalam rentang (status RESOLVED/CLOSED) |
| Response time | `first_response_at − created_at` (tiket yang sudah direspons) |
| Resolution time | `resolved_at − created_at` (tiket yang sudah resolved) |
| Recurring issue | Kombinasi unit + kategori + subkategori dengan ≥ 3 tiket dalam rentang |
