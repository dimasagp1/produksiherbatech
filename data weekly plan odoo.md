# Product Requirement Document (PRD) & Spesifikasi Teknis Integrasi Sistem Produksi & Supply Chain

* **Judul Dokumen:** Product Requirement Document (PRD) - System Integration Supply Chain & Production Monitoring
* **Versi Dokumen:** v1.0
* **Tanggal Update:** 2026-10-02
* **Basis Data:** Spreadsheet Sasaran Mutu 2026 & Diskusi Teknis Rekaman Produksi

---

## 1. Executive Summary & Latar Belakang

### 1.1 Tujuan Pengembangan
Pengembangan modul Supply Chain yang terintegrasi secara langsung ke dalam **Sistem Produksi** bertujuan untuk menciptakan sistem tunggal (*Single Source of Truth*) yang mengkonsolidasikan perencanaan pengiriman, kontrol persediaan, eksekusi produksi harian, dan analisis waktu henti (*downtime*). Integrasi ini menghilangkan tumpang tindih data (*data conflict*) akibat penggunaan aplikasi terpisah antar departemen.

### 1.2 Ruang Lingkup Integrasi
1. **Sinkronisasi Odoo ERP:** Menarik data *Manufacturing Order* (MO) yang berstatus **Confirmed** sebagai syarat mutlak pembuatan jadwal produksi (*Weekly Production Schedule / WPS*).
2. **Batch-Centric Tracking:** Mengelompokkan seluruh pencatatan produksi (mixing, filling, packing) dan laporan harian berdasarkan **Batch Number** secara estafet.
3. **Konsolidasi 5 Menu Utama Supply Chain:**
   * Plan Delivery Schedule
   * Stock Opname & Cycle Count
   * Plan Produksi (WPS Integration)
   * Inventory Control & Material Usage
   * Downtime Tracking & Analytics

---

## 2. Matrix Hak Akses & User Roles

| Role | Akses Menu | Hak Otoritas & Tanggung Jawab |
| :--- | :--- | :--- |
| **Planner / Supply Chain** | Plan Delivery, Plan Produksi (WPS), Inventory Control | Menginput jadwal pengiriman, memetakan MPS/WPS dari MO Odoo, memantau *Days of Inventory on Hand* (DOH) dan *Safety Stock*. |
| **Admin Gudang / Inventory** | Stock Opname, Inventory Control | Menginput hasil hitung fisik (*Cycle Count/SO*), mencatat *material scrap/damage*, dan verifikasi *VIIP*. |
| **Supervisor / Leader Produksi** | Plan Produksi, Downtime Tracking, Daily Report | Membagi *shift/line*, menyetujui laporan harian, mengonfirmasi kategori *downtime*, memantau OEE. |
| **Operator Line** | Daily Report (Timer & Pause/Resume) | Menjalankan *timer* produksi per *Batch Number*, menekan Pause/Resume, mengisi alasan *downtime*, menginput *output & reject*. |
| **Management / Ops Manager** | Dashboard Analytics (All Menus Read-Only) | Memantau KPI Sasaran Mutu (OEE, Akurasi SO, Loss Usage, Downtime Cost Impact, On-Time Delivery Rate). |

---

## 3. Arsitektur System & Core Principles

### 3.1 Odoo MO Status Guard (`Confirmed`)
* **Syarat Penarikan Data:** Sistem produksi memfilter dan hanya menarik MO dari Odoo yang berstatus **Confirmed**.
* **Guard Condition:** Fitur pembuatan *Weekly Plan* pada sistem produksi terkunci jika MO terkait di Odoo masih berstatus *Draft*.
* **Sync Batal:** Apabila MO berstatus *Confirmed* dibatalkan (*Cancel*) di Odoo, maka rencana produksi terkait di sistem produksi otomatis terhapus dari kalender kerja.

### 3.2 Alur Estafet Berbasis Batch Number
Satu **Batch Number** mengikat seluruh rangkaian proses produksi secara berurutan:
1. **Mixing (Pengolahan):** Dimulai berdasarkan Batch Number dari MO Odoo.
2. **Filling (Pengisian Primer):** Berjalan *inline* / estafet langsung dari hasil *mixing* dengan memilih Batch Number yang sama.
3. **Packing (Pengemasan Sekunder):** Dapat di-hold / ditunda (misal: di-hold 1 hari jika bahan kemas sekunder belum siap) tanpa merusak keterikatan data sejarah (*history*) pada Batch Number tersebut.

```
[ Odoo MO: Status "Confirmed" ]
               │
               ▼
      [ Batch Number Unik ]
               │
               ├──────────────────────────┐
               ▼                          ▼
      ┌─────────────────┐        ┌─────────────────┐
      │  Proses MIXING  │        │  PLAN DELIVERY  │
      └────────┬────────┘        └─────────────────┘
               │ (Estafet)
               ▼
      ┌─────────────────┐
      │  Proses FILLING │
      └────────┬────────┘
               │ (Bisa di-hold / Next Day)
               ▼
      ┌─────────────────┐
      │  Proses PACKING │
      └─────────────────┘
```

### 3.3 Real-Time Timer & Pause/Resume Event
* **Otomasi Durasi Downtime:** Sistem menghitung durasi *downtime* secara riil berdasarkan selisih timestamp saat operator menekan **Pause** hingga menekan **Resume**.
* **Mandatory Reason Selection:** Saat tombol **Resume** ditekan, sistem menampilkan *pop-up modal* wajib isi untuk memilih kategori alasan *downtime* (Mesin, Keterlambatan Supply Material, Pengadaan/Vendor, Rehat, Briefing, atau Line Clearance).
* **Net Time Calculation:** Akumulasi durasi *downtime* otomatis memotong waktu kotor (*Gross Operation Time*) menjadi waktu bersih (*Net Operation Time*).

---

## 4. Spesifikasi Detail 5 Menu Utama

### 4.1 Menu 1: Plan Delivery Schedule
* **Tujuan:** Mengelola rencana pengiriman barang jadi ke pelanggan sebagai dasar penyusunan prioritas produksi.
* **Komponen UI/UX:**
  * **Tabel Schedule:** Kolom *Delivery Order ID, Sales Order Ref, Customer Name, Product Name, Quantity Target, Planned Delivery Date, Priority Status*.
  * **Filter & Search:** Filter berdasarkan rentang tanggal pengiriman, status (*Draft, Confirmed, Shipped, Delayed*), dan pencarian nama pelanggan.
  * **Action Buttons:** `Sync Sales Order (Odoo)`, `Export Delivery Plan`, `Set Production Priority`.

### 4.2 Menu 2: Stock Opname & Cycle Count
* **Tujuan:** Mencatat hasil perhitungan fisik persediaan secara berkala dan menghitung selisih (*variance*) terhadap data stok Odoo.
* **Komponen UI/UX:**
  * **Form Input SO:** Pilihan *SO Type (Full SO / Partial Cycle Count)*, Lokasi Gudang (*Raw Material, Packaging, WIP, Finished Goods*), Tanggal Opname.
  * **Tabel Perhitungan:** *Item Code, Item Description, System Stock (Odoo), Physical Count (Input), Variance (Qty), Variance Value (Rp), Notes*.
  * **Kalkulasi Otomatis:** Sistem menghitung otomatis `% Akurasi SO = (Total Nilai Selisih / Total Nilai Persediaan) * 100%`.
  * **Action Buttons:** `Start Cycle Count`, `Draft Save`, `Submit for Stock Adjustment`.

### 4.3 Menu 3: Plan Produksi (WPS Integration)
* **Tujuan:** Memetakan MO berstatus *Confirmed* menjadi *Weekly Production Schedule* (WPS) per *Line* dan *Shift*.
* **Komponen UI/UX:**
  * **Kalender/Gantt Chart WPS:** Visualisasi alur pengerjaan per Line Produksi (Line 1, Line 2, dst.) dan per Shift (Shift 1, 2, 3).
  * **Tabel Mapped MO:** List MO Odoo yang siap dijadwalkan, dilengkapi tombol alokasi *Batch Number*.
  * **Indicator Status:** Status pengerjaan (*Unscheduled, Scheduled, In-Progress, Completed, On-Hold*).
  * **Action Buttons:** `Fetch Confirmed MO`, `Lock Weekly Plan`, `Print Batch Traveler Sheet`.

### 4.4 Menu 4: Inventory Control & Material Usage
* **Tujuan:** Mengawasi tingkat persediaan (*Safety Stock/DOH*), mencatat material *scrap/damage*, dan mengontrol variansi pemakaian bahan aktual vs BOM.
* **Komponen UI/UX:**
  * **Dashboard Status Stok:** Indikator visual warna (Merah: Below Safety Stock, Hijau: Safe, Kuning: Overstock > 90 DOH).
  * **Form Material Damage/Scrap:** Form pencatatan material rusak di luar proses produksi harian (pilihan material ditarik dari struktur BOM Odoo).
  * **Grafik Usage Variance:** Visualisasi tren persentase deviasi pemakaian material aktual terhadap standar BOM.
  * **Action Buttons:** `Log Material Scrap`, `Calculate Material Usage Ratio`, `Export DOH Report`.

### 4.5 Menu 5: Downtime Tracking & Analytics
* **Tujuan:** Mencatat, mengkategorikan, dan menganalisis seluruh waktu henti produksi serta dampaknya terhadap OEE dan biaya.
* **Komponen UI/UX:**
  * **Control Panel Timer (Laporan Harian):** Tombol utama `START`, `PAUSE`, `RESUME`, `FINISH`.
  * **Modal Pop-up Downtime:** Muncul saat `RESUME`, berisi dropdown kategori utama:
    1. *Kendala Mesin / Breakdown*
    2. *Keterlambatan Supply Material (Supply Chain Stop)*
    3. *Keterlambatan Pengadaan / Vendor (Procurement Delay)*
    4. *Non-Technical Pause (Briefing, Rehat, Line Clearance)*
  * **Analytics Dashboard:** Grafik Pareto *Downtime Cause*, Total Jam Stop per Departemen, dan Kalkulasi *Dampak Cost Downtime*.
  * **Action Buttons:** `Filter Downtime Category`, `Export Analytics PDF/Excel`.

---

## 5. Business Rules & Mathematical Formulas

Seluruh perhitungan dalam sistem wajib menggunakan formula baku sesuai kesepakatan Sasaran Mutu 2026:

### 5.1 Overall Equipment Effectiveness (OEE)
$$\text{OEE} = \text{Availability} \times \text{Performance} \times \text{Quality}$$

1. **Availability Ratio:**
   $$\text{Availability (\%)} = \left( \frac{\text{Net Operation Time}}{\text{Planned Operation Time}} \right) \times 100\%$$
   *Di mana:*
   $$\text{Net Operation Time} = \text{Gross Operation Time} - \text{Total Accumulative Downtime}$$

2. **Performance Ratio:**
   $$\text{Performance (\%)} = \left( \frac{\text{Total Output Aktual} \times \text{Standard Cycle Time per Pcs}}{\text{Net Operation Time}} \right) \times 100\%$$

3. **Quality Ratio:**
   $$\text{Quality (\%)} = \left( \frac{\text{Good Output}}{\text{Total Output Diproses}} \right) \times 100\%$$
   *Di mana:*
   $$\text{Good Output} = \text{Total Output Diproses} - \text{Total Scrap / Reject}$$

---

### 5.2 Stock Opname Accuracy Formula
$$\text{Akurasi SO (\% Selisih)} = \left( \frac{\text{Nilai Selisih Physical Count (Rp)}}{\text{Total Nilai Persediaan (Rp)}} \right) \times 100\%$$

---

### 5.3 Material Usage Ratio Formula
$$\text{Rasio Usage (\%)} = \left( \frac{\text{Pemakaian Material Aktual} - \text{Pemakaian Standar (BOM)}}{\text{Pemakaian Standar (BOM)}} \right) \times 100\%$$

---

### 5.4 Loss Inventory Ratio Formula
$$\text{Loss Inventory (\%)} = \left( \frac{\text{Total Nilai Material Rusak / Expired (Rp)}}{\text{Total Target Revenue (Rp)}} \right) \times 100\%$$

---

### 5.5 Downtime Cost Impact Formulas

1. **Dampak Cost Downtime Supply Material:**
   $$\text{Dampak Cost Supply (\%)} = \left( \frac{\text{Jam Stop Supply} \times \text{Output Standard/Jam} \times \text{Value Produk/Pcs}}{\text{Total Target Revenue}} \right) \times 100\%$$

2. **Dampak Cost Downtime Pengadaan (Procurement Delay):**
   $$\text{Dampak Cost Pengadaan (\%)} = \left( \frac{\text{Loss Time Vendor} \times \text{Output Standard/Jam} \times \text{Value Produk/Pcs}}{\text{Total Target Revenue}} \right) \times 100\%$$

3. **Rasio Downtime Mesin:**
   $$\text{Rasio Downtime Mesin (\%)} = \left( \frac{\text{Total Jam Downtime Mesin}}{\text{Total Jam Produksi Terjadwal}} \right) \times 100\%$$

---

### 5.6 Yield & Productivity Formulas

1. **Yield Produksi:**
   $$\text{Yield (\%)} = \left( \frac{\text{Output Finished Goods Aktual}}{\text{Output Teoritis Batch (BOM)}} \right) \times 100\%$$

2. **Productivity Karyawan:**
   $$\text{Productivity (\%)} = \left( \frac{\text{Std Manpower} \times \text{Std Output} \times \text{Std Time}}{\text{Manpower Aktual} \times \text{Output Aktual} \times \text{Time Aktual}} \right) \times 100\%$$

---

## 6. End-to-End Process & Data Mapping Matrix

### 6.1 Matriks Pemetaan dengan Spreadsheet Sasaran Mutu 2026

| Menu Sistem Produksi | Parameter Spreadsheet Sasaran Mutu | Indikator KPI Utama | Pengelompokan Modul Spreadsheet |
| :--- | :--- | :--- | :--- |
| **Plan Delivery Schedule** | Operations Manager No. 1 & Supply Chain No. 1 | On-Time Delivery Rate & % Plan Delivery Compliance | Finance & Production Monitoring |
| **Stock Opname & Cycle Count** | Supply Chain No. 4 | Akurasi Stock Opname secara berkala (% Variance) | Planning & Production Monitoring |
| **Plan Produksi (WPS)** | Supply Chain No. 1 & Production No. 1 | Akurasi Perencanaan Produksi (WPS vs Actual) | Planning & Production Monitoring |
| **Inventory Control & Usage** | Supply Chain No. 2 & No. 3 | Rasio Usage Material (%) & Loss Inventory (%) | Planning & Production Monitoring |
| **Downtime Tracking & Analytics** | Supply Chain No. 5, Procurement No. 1, Production No. 3 & 4 | Jam Stop Supply Cost, Loss Time Vendor Cost, OEE | Planning & Production Monitoring |

---

## 7. Non-Functional Requirements & Acceptance Criteria

### 7.1 Non-Functional Requirements
1. **User Interface Resilience:** Antarmuka operator dirancang sederhana (*touch-friendly*) untuk meminimalkan kesalahan input di area pabrik.
2. **Offline-First Handling:** Jika koneksi lokal terputus saat *timer running*, data *timer* tersimpan secara lokal (*browser cache*) dan otomatis menyelaraskan kembali saat koneksi pulih.
3. **Data Integrity:** Mencegah modifikasi data *downtime* secara manual tanpa persetujuan (*approval*) dari Supervisor Produksi.

### 7.2 Acceptance Criteria
* [x] Sistem memblokir pembuatan *Weekly Plan* jika MO Odoo belum berstatus `Confirmed`.
* [x] *Timer* harian dapat menghitung waktu *Net Operation Time* secara riil dengan pemotongan otomatis durasi *Pause-Resume*.
* [x] Operator wajib memilih alasan *downtime* saat menekan tombol *Resume*.
* [x] Laporan OEE dan *downtime cost impact* mengkalkulasi data secara akurat sesuai formula baku di Bab 5.
* [x] Seluruh laporan pengerjaan dari *mixing*, *filling*, hingga *packing* terekap lengkap di bawah satu *Batch Number*.

---
*Dokumen ini disusun sebagai acuan resmi pengerjaan integrasi sistem oleh Tim Software Development.*
