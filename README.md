# Sistem Produksi Herbatech - LinePulse

Repository sistem monitoring produksi harian untuk Herbatech.

**Repository:** https://github.com/amarNrddn/sistem-produksi

## Stack

- Laravel 13
- Vue 3.5
- Inertia.js
- TypeScript
- Tailwind CSS 4
- shadcn-vue
- Spatie Laravel Permission
- MySQL 8
- SQLite untuk development

---

## 1. Tentang Sistem

LinePulse adalah sistem monitoring produksi harian yang digunakan untuk menggantikan proses pencatatan produksi manual menggunakan Excel.

Sistem menghubungkan proses produksi dari tahap perencanaan sampai monitoring hasil produksi dalam satu sistem.

### Alur Utama

```text
PPIC
  |
  | Membuat Weekly Plan
  v
Weekly Plan
  |
  | Produk + Proses + Batch + Tanggal
  v
Leader
  |
  | Membuat Laporan Harian
  v
Proses Produksi
  |
  | Start -> Pause -> Resume -> End
  v
Output + Downtime + Reject
  |
  v
Perhitungan KPI
  |
  v
Submit Laporan
  |
  v
SPV
  |
  | Lock Laporan
  v
Dashboard dan Monitoring
```

Sistem saat ini mencakup:

- Authentication dan User Management
- Role Based Access Control
- Master Data
- Weekly Plan
- Laporan Harian Produksi
- Timer Produksi
- Downtime
- Reject Produk
- KPI Produksi
- Dashboard Monitoring
- Branding dan Application Settings
- Persiapan Integrasi Odoo
- Persiapan fitur Realtime

---

# 2. Flow Bisnis Sistem

## 2.1 PPIC Membuat Weekly Plan

PPIC bertanggung jawab menentukan rencana produksi.

Data yang dibuat:

- Produk
- Proses
- Nomor Batch
- Tanggal Produksi

Proses yang tersedia:

- Mixing
- Filling
- Packing

Weekly Plan menggunakan dua status:

```text
Draft
  |
  v
Aktif
```

Setelah Weekly Plan diaktifkan, data tersebut tidak dapat diubah.

### Aturan Weekly Plan

- Kalender produksi berjalan Senin sampai Sabtu.
- Satu produk dapat memiliki maksimal 2 proses pada tanggal yang sama.
- Proses yang sama pada produk dan tanggal yang sama tidak boleh dibuat dua kali.
- Nomor batch harus unik.
- Weekly Plan yang sudah aktif menjadi immutable.

Contoh:

```text
Tanggal: 28 September 2026

Produk A
  - Mixing
  - Filling

Produk B
  - Mixing
```

Kondisi tersebut diperbolehkan karena Produk A memiliki dua proses berbeda pada tanggal yang sama.

---

## 2.2 Leader Membuat Laporan Harian

Setelah Weekly Plan aktif, Leader dapat membuat Laporan Harian.

Flow:

```text
Pilih tanggal
    |
    v
Ambil Weekly Plan aktif
    |
    v
Pilih produk
    |
    v
Pilih proses
    |
    v
Pilih mesin dan line
    |
    v
Isi Target MP dan Total MP
    |
    v
Capacity Fisik dihitung otomatis
    |
    v
Buat Laporan Harian
```

Leader hanya dapat membuat dan melihat laporan miliknya sendiri.

Sistem akan menampilkan produk dan proses berdasarkan Weekly Plan aktif pada tanggal yang dipilih.

Jika satu produk hanya memiliki satu proses:

```text
Produk A
  |
  +-- Mixing
```

Maka proses dapat terisi otomatis.

Jika produk memiliki dua proses:

```text
Produk A
  |
  +-- Mixing
  |
  +-- Filling
```

Maka Leader dapat memilih proses yang ingin dikerjakan.

---

## 2.3 Proses Produksi

Setelah laporan dibuat, Leader menjalankan proses produksi menggunakan timer.

Status timer:

```text
Draft
  |
  v
Start
  |
  v
Pause
  |
  v
Resume
  |
  v
End
```

Pause dapat dilakukan beberapa kali.

Sistem menyimpan:

- Start Time
- End Time
- Pause Start
- Total Pause
- Gross Time

Perhitungan waktu juga mendukung proses yang melewati pergantian hari.

Contoh:

```text
Start : 23:00
End   : 01:00

Gross Time = 120 menit
```

---

# 3. Flow Mixing dan Filling

Sistem mendukung rangkaian proses Mixing dan Filling untuk produk yang sama.

Contoh Weekly Plan:

```text
Tanggal 28 September

Produk A
  |
  +-- Mixing
  |
  +-- Filling
```

Pada sisi Leader, kedua proses tersebut dapat ditampilkan dalam satu halaman rangkaian.

Flow:

```text
Mixing
  |
  | Start
  v
Produksi Mixing
  |
  | End
  v
Mixing Selesai
  |
  v
Filling dapat dimulai
  |
  v
Produksi Filling
  |
  | End
  v
Filling Selesai
```

Mixing dan Filling tetap merupakan dua Laporan Harian yang berbeda di database.

Namun pada sisi tampilan, keduanya ditampilkan sebagai satu rangkaian produksi.

Relasi utama:

```text
Weekly Plan
    |
    +-- Laporan Mixing
    |
    +-- Laporan Filling
```

Filling tidak dapat dimulai sebelum Mixing selesai atau sudah berstatus submitted.

Aturan ini digunakan untuk menjaga urutan proses produksi.

---

# 4. Output Produksi

Setelah proses produksi selesai, Leader mengisi Output Fisik.

Capacity dihitung berdasarkan:

```text
Capacity Fisik = Target MP x Total MP
```

Output Fisik kemudian digunakan dalam perhitungan KPI dan reject.

Data produksi utama:

- Target MP
- Total MP
- Capacity Fisik
- Output Fisik
- CT
- Mesin
- Line
- Start Time
- End Time
- Downtime
- Reject

---

# 5. Downtime

Downtime digunakan untuk mencatat waktu ketika proses produksi tidak berjalan.

Terdapat dua jenis input downtime.

## Manual

Durasi downtime diinput berdasarkan kondisi aktual di lapangan.

## Default Hardcode

Beberapa downtime memiliki durasi default:

```text
Istirahat       = 60 menit
Briefing        = 15 menit
Line Clearance  = 15 menit
```

Downtime default tidak langsung dihitung.

Leader harus mengaktifkan downtime tersebut terlebih dahulu.

Flow:

```text
Pilih downtime
    |
    v
Aktifkan checkbox
    |
    v
Isi atau gunakan durasi
    |
    v
Downtime masuk ke perhitungan
```

Total downtime:

```text
Total Downtime
=
Total Pause
+
Downtime Produksi
```

Waktu bersih:

```text
Waktu Bersih
=
Gross Time - Total Downtime
```

Nilai waktu bersih tidak boleh kurang dari 0.

---

# 6. Perhitungan KPI

Setelah laporan disubmit, sistem menghitung KPI secara otomatis di backend.

KPI yang tersedia:

- Yield
- Availability
- Performance
- OEE
- Produktivitas

## Gross Time

```text
Gross Time = End Time - Start Time
```

Perhitungan mendukung pergantian hari.

## Target Teoritis

```text
Target Teoritis = Waktu Bersih x CT
```

## Yield

```text
Yield = Output / Capacity x 100
```

## Availability

```text
Availability = Waktu Bersih / Gross Time x 100
```

## Performance

```text
Performance = Output / Target Teoritis x 100
```

## OEE

```text
OEE = Availability x Performance x Yield
```

## Produktivitas

```text
Produktivitas =
Output / (Target MP x Total MP) x 100
```

Nilai KPI dibatasi maksimal 100 persen.

Threshold KPI:

```text
Produktivitas = 90
OEE           = 70
Yield         = 85
```

Konfigurasi tersedia di:

```text
config/linepulse.php
```

---

# 7. Reject Produk

Reject digunakan untuk mencatat produk yang tidak memenuhi hasil produksi.

Jenis reject:

- Sublayer
- GA
- Process

Data reject:

- Jenis Reject
- Jumlah
- Keterangan
- User pembuat
- Laporan produksi

Sistem menghitung:

```text
Total Reject
```

dan:

```text
Sisa Quantity
=
Available Quantity - Total Reject
```

### Aturan Akses

- Leader hanya dapat mengelola reject dari laporan miliknya.
- Manager tidak dapat melakukan input reject.
- Laporan yang sudah locked tidak dapat dimodifikasi.

Sistem juga sudah memiliki field untuk kebutuhan integrasi Odoo.

---

# 8. Submit dan Lock Laporan

Setelah proses produksi selesai, Leader melakukan Submit.

Flow:

```text
Draft
  |
  v
Produksi Berjalan
  |
  v
Produksi Selesai
  |
  v
Submit
  |
  v
Submitted
  |
  v
SPV melakukan Lock
  |
  v
Locked
```

Setelah laporan berstatus Locked:

- Data tidak dapat dimodifikasi.
- Downtime tidak dapat diubah.
- Reject tidak dapat ditambahkan.
- Data produksi menjadi data final.

Lock hanya dapat dilakukan oleh:

- SPV
- Superadmin

---

# 9. Role dan Hak Akses

Sistem memiliki 6 role:

| Role | Fungsi Utama |
|---|---|
| Superadmin | Mengelola seluruh sistem |
| Admin | Mengelola master data dan user |
| PPIC | Mengelola Weekly Plan |
| Leader | Menjalankan dan membuat laporan produksi |
| SPV | Memantau dan melakukan lock laporan |
| Manager | Melihat data dengan akses read-only |

## Superadmin

Memiliki akses ke seluruh modul:

- User
- Role
- Master Data
- Weekly Plan
- Laporan Harian
- Timer
- Reject
- Dashboard
- Lock Laporan
- Branding
- Settings

## Admin

Fokus pada pengelolaan:

- Produk
- Mesin
- Line
- Alasan Downtime
- User
- Laporan

## PPIC

Fokus pada:

- Weekly Plan
- Master Produk

## Leader

Fokus pada proses produksi:

- Laporan Harian
- Timer
- Output
- Downtime
- Reject milik sendiri

Leader hanya dapat melihat laporan miliknya sendiri.

## SPV

Fokus pada monitoring dan verifikasi:

- Melihat seluruh laporan
- Memeriksa laporan
- Melakukan Lock
- Mengelola Reject sesuai akses

## Manager

Manager menggunakan sistem sebagai read-only.

Manager dapat melihat data tetapi tidak dapat melakukan:

```text
POST
PUT
DELETE
```

---

# 10. Master Data

## Produk

Data Produk:

- Kode Produk
- Nama Produk
- Proses Default
- Status Aktif

Proses default:

- Mixing
- Filling
- Packing

Kode produk harus unik.

Akses:

- Superadmin
- Admin
- PPIC
- Manager

## Mesin

Data Mesin:

- Nama Mesin
- CT
- Status Aktif

Akses:

- Superadmin
- Admin
- Manager

## Line

Data Line:

- Kode Line
- Nama Line
- Status Aktif

## Alasan Downtime

Data:

- Nama Alasan
- Tipe Input
- Durasi Default
- Status Aktif

Tipe input:

- Manual
- Default Hardcode

## User

Manajemen User menyediakan:

- CRUD User
- Assign Role
- Sync Role
- Aktifkan User
- Nonaktifkan User
- Search
- Pagination

---

# 11. Dashboard

Dashboard digunakan untuk melihat kondisi produksi secara keseluruhan.

Dashboard menyediakan:

- Filter tanggal
- Mode Live
- Monitoring laporan produksi
- Rata-rata KPI
- Downtime Pareto
- Heatmap KPI
- Monitoring berdasarkan proses
- Monitoring berdasarkan produk
- Breakdown berdasarkan batch
- Sisa quantity
- Monthly Output
- Statistik Reject
- Statistik Master Data
- Statistik Weekly Plan

## Downtime Pareto

Downtime dikelompokkan berdasarkan alasan downtime untuk membantu melihat jenis downtime yang paling banyak terjadi.

## Monitoring Produksi

Data dapat dikelompokkan berdasarkan:

```text
Line
Proses
Produk
Batch
```

## Reject Dashboard

Reject dapat dilihat berdasarkan:

```text
Jenis Reject
Produk
Bulan
```

---

# 12. Branding dan Settings

Sistem memiliki tabel settings untuk menyimpan konfigurasi aplikasi.

Data utama:

- Key
- Value
- Group

Branding yang dapat digunakan:

- Nama aplikasi
- Tagline
- Logo

Jika konfigurasi branding belum tersedia, sistem menggunakan fallback:

```text
LinePulse
```

---

# 13. Integrasi Odoo

Integrasi Odoo merupakan bagian dari Fase 2.

Field yang sudah disiapkan:

## Produk

```text
odoo_id
uom
synced_at
```

## Laporan Harian

```text
odoo_mo_id
odoo_mo_name
synced_at
```

## Reject

```text
odoo_scrap_id
synced_at
```

Environment variable yang sudah disiapkan:

```env
ODOO_HOST=
ODOO_DB=
ODOO_USERNAME=
ODOO_API_KEY=
```

Integrasi sinkronisasi Odoo belum aktif sepenuhnya pada sistem saat ini.

---

# 14. Realtime

Fitur realtime masih dalam tahap perencanaan.

Komponen yang sudah direncanakan:

- Broadcast Log
- Queue Database
- Cache Database
- Laravel Reverb
- Laravel Horizon

Realtime belum aktif pada code saat ini.

---

# 15. Model dan Relasi

Relasi utama sistem:

```text
User
 |
 +-- WeeklyPlan
 |
 +-- LaporanHarian
```

```text
Produk
 |
 +-- WeeklyPlan
 |
 +-- LaporanHarian
```

```text
WeeklyPlan
 |
 +-- Produk
 +-- User
 +-- LaporanHarian
```

```text
LaporanHarian
 |
 +-- User
 +-- WeeklyPlan
 +-- Produk
 +-- Mesin
 +-- Line
 +-- User sebagai Locker
 +-- DowntimeDetail
 +-- RejectDetail
```

```text
DowntimeDetail
 |
 +-- LaporanHarian
 +-- AlasanDowntime
```

```text
RejectDetail
 |
 +-- LaporanHarian
```

Model utama:

- User
- Produk
- Mesin
- Line
- AlasanDowntime
- WeeklyPlan
- LaporanHarian
- DowntimeDetail
- RejectDetail
- Setting

LaporanHarian menggunakan Soft Delete.

---

# 16. Teknologi

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.3+, Laravel 13.32 |
| Authentication | Laravel Sanctum |
| Frontend | Vue 3.5 |
| Language | TypeScript 5 |
| SPA Bridge | Inertia.js |
| CSS | Tailwind CSS 4 |
| UI | shadcn-vue |
| Build Tool | Vite |
| RBAC | Spatie Laravel Permission |
| Database | MySQL 8 |
| Development Database | SQLite |
| Realtime | Reverb, Horizon, Redis |
| Realtime Status | Belum aktif |

---

# 17. Instalasi

## 17.1 Prasyarat

Pastikan sudah tersedia:

```bash
php -v
composer -V
node -v
npm -v
mysql --version
```

Minimum:

```text
PHP 8.3
Node.js 20+
MySQL 8
Composer
NPM
```

Untuk environment MAMP, MySQL menggunakan port:

```text
8889
```

---

## 17.2 Clone Repository

```bash
git clone https://github.com/amarNrddn/sistem-produksi.git production-sistem-herbatech

cd production-sistem-herbatech
```

---

## 17.3 Install Dependency

Backend:

```bash
composer install
```

Frontend:

```bash
npm install
```

---

## 17.4 Environment

Copy file environment:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

---

## 17.5 Konfigurasi Database

Untuk MAMP:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=sistem_production_herbatech
DB_USERNAME=root
DB_PASSWORD=root
```

Database dapat dibuat melalui phpMyAdmin atau MySQL.

phpMyAdmin MAMP:

```text
http://localhost:8888/phpMyAdmin
```

---

## 17.6 Storage

Jalankan:

```bash
php artisan storage:link
```

Kemudian:

```bash
php artisan config:clear
```

---

## 17.7 Migration dan Seeder

Untuk migration:

```bash
php artisan migrate --force
```

Untuk development dengan database baru:

```bash
php artisan migrate:fresh --seed --force
```

Perhatian:

```text
migrate:fresh akan menghapus seluruh tabel dan data pada database.
```

Seeder menyediakan:

- Roles
- Master Produk
- Master Mesin
- Master Line
- Master Alasan Downtime
- Weekly Plan
- Laporan Harian
- User Default

---

# 18. Menjalankan Aplikasi

Jalankan Laravel:

```bash
php artisan serve
```

Jalankan Vite:

```bash
npm run dev
```

Opsional:

```bash
php artisan queue:listen
```

Untuk monitoring log Laravel:

```bash
php artisan pail
```

Aplikasi dapat dibuka melalui:

```text
http://127.0.0.1:8000/login
```

Jika menggunakan MAMP Apache:

```text
http://localhost:8888
```

---

# 19. Default User

Seeder menyediakan beberapa akun untuk testing.

| Email | Password | Role |
|---|---|---|
| admin@herbatech.com | password | superadmin |
| ppic@herbatech.com | password | ppic |
| leader@herbatech.com | password | leader |
| leader2@herbatech.com | password | leader |
| spv@herbatech.com | password | spv |
| manager@herbatech.com | password | manager |

Akun default hanya digunakan untuk development dan testing.

---

# 20. Konfigurasi Penting

## KPI

File:

```text
config/linepulse.php
```

Digunakan untuk mengatur:

- Threshold Produktivitas
- Threshold OEE
- Threshold Yield
- Heatmap KPI

## Odoo

Environment:

```env
ODOO_HOST=
ODOO_DB=
ODOO_USERNAME=
ODOO_API_KEY=
```

## Frontend

File utama:

```text
vite.config.js
tailwind.config.js
resources/css/app.css
```

File CSS juga berisi konfigurasi untuk input tanpa spinner pada field numerik.

---

# 21. Struktur Direktori

Struktur utama project:

```text
app/
├── Http/
│   └── Controllers/
│       ├── WeeklyPlanController.php
│       ├── LaporanHarianController.php
│       ├── DashboardController.php
│       ├── RejectController.php
│       ├── UserController.php
│       ├── MesinController.php
│       ├── LineController.php
│       └── AlasanDowntimeController.php
│
├── Models/
│   ├── User.php
│   ├── Produk.php
│   ├── Mesin.php
│   ├── Line.php
│   ├── AlasanDowntime.php
│   ├── WeeklyPlan.php
│   ├── LaporanHarian.php
│   ├── DowntimeDetail.php
│   ├── RejectDetail.php
│   └── Setting.php
│
database/
├── migrations/
└── seeders/
    ├── RoleSeeder.php
    ├── MasterDataSeeder.php
    ├── ProdukSeeder.php
    ├── WeeklyPlanSeeder.php
    └── LaporanHarianSeeder.php
│
resources/
└── js/
    └── Pages/
        ├── PPIC/
        │   └── WeeklyPlan/
        ├── Leader/
        │   └── LaporanHarian/
        ├── Dashboard.vue
        ├── Reject/
        └── Admin/
│
routes/
└── web.php
│
config/
└── linepulse.php
│
doc/
├── ANALISIS_ROLE_DAN_ALUR_SISTEM.md
├── ANALISIS_IMPACT_PARALLEL_FLOW.md
├── ARUS_SISTEM_PRODUKSI_DETAILED.md
└── PRD_Sistem_Monitoring_Produksi_v2.md
```

---

# 22. Dokumentasi Internal

Dokumentasi tambahan tersedia di folder `doc`.

## ANALISIS_ROLE_DAN_ALUR_SISTEM.md

Berisi:

- Matriks role
- Hak akses
- Flow sistem
- Analisis masing-masing role

## ANALISIS_IMPACT_PARALLEL_FLOW.md

Berisi analisis dan rancangan flow Mixing dan Filling dalam satu halaman.

## ARUS_SISTEM_PRODUKSI_DETAILED.md

Berisi penjelasan detail mengenai arus sistem produksi.

## PRD_Sistem_Monitoring_Produksi_v2.md

Berisi Product Requirement Document untuk sistem.

---

# 23. Status Fitur

| Modul | Status |
|---|---|
| Authentication | Selesai |
| User Management | Selesai |
| Role dan RBAC | Selesai |
| Master Produk | Selesai |
| Master Mesin | Selesai |
| Master Line | Selesai |
| Master Alasan Downtime | Selesai |
| Weekly Plan | Selesai |
| Laporan Harian | Selesai |
| Timer Produksi | Selesai |
| Mixing dan Filling Flow | Selesai |
| Downtime | Selesai |
| Reject Produk | Selesai |
| KPI | Selesai |
| Dashboard | Selesai |
| Branding dan Settings | Selesai |
| Persiapan Integrasi Odoo | Dalam pengembangan |
| Integrasi Odoo Penuh | Belum aktif |
| Realtime | Belum aktif |

---

# 24. Troubleshooting

## MySQL Connection Refused

Error:

```text
Connection refused 127.0.0.1:8889
```

Pastikan MySQL MAMP sudah berjalan.

Cek proses MySQL:

```bash
ps aux | grep mysqld
```

Pastikan MySQL berjalan pada port:

```text
8889
```

---

## Table Settings Tidak Ditemukan

Error:

```text
1146 Table settings doesn't exist
```

Jalankan:

```bash
php artisan migrate --force
```

Pastikan migration untuk tabel `settings` sudah dijalankan.

---

## Duplicate Column is_active

Jika muncul error:

```text
1060 Duplicate column is_active
```

Periksa struktur tabel:

```sql
DESCRIBE users;
```

Jika kolom `is_active` sudah tersedia tetapi migration masih berstatus pending, periksa tabel:

```text
migrations
```

Pastikan migration:

```text
2026_09_21_045500_add_is_active_to_users_table
```

memiliki status yang sesuai.

---

## Vite atau vue-tsc Error styleText

Jika muncul error terkait `styleText`, periksa versi Node.js:

```bash
node -v
```

Gunakan Node.js 20 atau versi yang lebih baru.

---

# 25. Aturan Penting Sistem

Beberapa aturan bisnis penting yang perlu diperhatikan ketika melakukan perubahan code:

1. Weekly Plan aktif tidak boleh diubah.
2. Satu produk maksimal memiliki 2 proses pada tanggal yang sama.
3. Proses yang sama tidak boleh diduplikasi pada produk dan tanggal yang sama.
4. Leader hanya dapat mengakses laporan miliknya sendiri.
5. Filling tidak boleh dimulai sebelum Mixing selesai atau submitted.
6. Laporan yang sudah locked tidak boleh dimodifikasi.
7. Manager bersifat read-only.
8. KPI dihitung di backend.
9. Downtime yang tidak diaktifkan tidak masuk ke perhitungan.
10. Reject tidak boleh melebihi quantity yang tersedia.
11. Capacity dihitung berdasarkan Target MP dan Total MP.
12. Proses Mixing dan Filling dapat ditampilkan sebagai satu rangkaian pada halaman laporan.

---

# 26. Referensi Controller dan File Utama

Beberapa file penting untuk memahami flow sistem:

```text
app/Http/Controllers/WeeklyPlanController.php
```

Digunakan untuk proses Weekly Plan.

```text
app/Http/Controllers/LaporanHarianController.php
```

Digunakan untuk:

- Create laporan
- Timer
- Pause
- Resume
- End
- Submit
- Lock
- KPI

```text
app/Http/Controllers/RejectController.php
```

Digunakan untuk proses Reject Produk.

```text
app/Http/Controllers/DashboardController.php
```

Digunakan untuk data Dashboard dan monitoring.

```text
app/Models/WeeklyPlan.php
```

Berisi logic urutan proses dan proses berikutnya.

```text
resources/js/Pages/Leader/LaporanHarian/Show.vue
```

Merupakan halaman utama untuk monitoring rangkaian proses produksi.

---

# 27. Alur Singkat untuk Developer Baru

Jika developer baru masuk ke project, pahami sistem dengan urutan berikut:

```text
1. User dan Role
       |
       v
2. Master Produk, Mesin, Line
       |
       v
3. Weekly Plan
       |
       v
4. Laporan Harian
       |
       v
5. Timer Produksi
       |
       v
6. Downtime dan Output
       |
       v
7. KPI
       |
       v
8. Reject
       |
       v
9. Submit
       |
       v
10. Lock oleh SPV
       |
       v
11. Dashboard
```

Untuk memahami flow produksi secara lebih detail, baca:

```text
doc/ARUS_SISTEM_PRODUKSI_DETAILED.md
```

Untuk memahami role:

```text
doc/ANALISIS_ROLE_DAN_ALUR_SISTEM.md
```

Untuk memahami flow Mixing dan Filling:

```text
doc/ANALISIS_IMPACT_PARALLEL_FLOW.md
```

Untuk memahami requirement sistem:

```text
doc/PRD_Sistem_Monitoring_Produksi_v2.md
```

---

# 28. License

Project menggunakan license MIT.

Kontribusi dapat dilakukan melalui Pull Request pada repository:

https://github.com/amarNrddn/sistem-produksi

---

# 29. Catatan Development

Saat melakukan perubahan pada sistem produksi, pastikan perubahan tidak merusak flow yang sudah berjalan, terutama:

- Weekly Plan
- Relasi Weekly Plan dengan Laporan Harian
- Urutan Mixing dan Filling
- Timer
- Perhitungan Downtime
- Perhitungan KPI
- Reject
- Lock Laporan
- Hak akses berdasarkan Role

Perubahan pada flow produksi sebaiknya terlebih dahulu diperiksa terhadap relasi database, controller, model, dan halaman frontend yang terkait.
