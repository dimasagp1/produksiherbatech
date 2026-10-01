# 📑 Ringkasan Terpadu Perubahan & Perbaikan Sistem (25 September 2026)

Dokumen ini memuat seluruh catatan rilis (*release notes*), fitur baru, integrasi, dan perbaikan bug (*bug fixes*) yang dikerjakan pada aplikasi **LinePulse**.

---

## 🚀 I. FITUR BARU & PENINGKATAN UTAMA

### 1. ⏱️ Floating Production Timer & Sistem Anti Reset
* **Floating Active Production Widget (`FloatingActiveTimer.vue`)**:
  - Menampilkan timer produksi yang tetap mengambang di pojok kanan bawah saat user berpindah ke menu lain (Dashboard, Master Data, PPIC, Reject).
  - Dilengkapi informasi nomor batch, nama produk, tahapan proses, durasi berjalan (*live pulse*), serta tombol navigasi 1-klik untuk kembali ke halaman produksi aktif.
* **Server-Side Persistence**:
  - Perhitungan waktu berjalan menggunakan selisih waktu nyata di server (`start_time` & `total_pause_menit`), sehingga **timer tetap berjalan akurat dan tidak akan reset meskipun tab browser ditutup atau perangkat dimatikan**.

### 2. 🔄 Integrasi Odoo ERP (JSON-RPC v2.0)
* **Sinkronisasi Master Produk (`OdooProductSyncModal.vue`)**:
  - Menarik data master produk langsung dari Odoo ERP dengan filter tipe produk (*Internal, Consumable, Storable, Finished Goods*) dan seleksi interaktif.
  - Mendukung reset/hapus produk Odoo yang belum memiliki riwayat transaksi lokal.
* **Sinkronisasi Scrap Order Otomatis (`Reject/Index.vue`)**:
  - Transaksi reject yang diinput pada LinePulse otomatis diteruskan sebagai *Scrap Order (`stock.scrap`)* di Odoo.
  - Tab live **"Odoo Live Scrap"** untuk memantau status dokumen scrap langsung dari server Odoo secara real-time.
* **Manajemen Kredensial & Pengujian Koneksi (`Admin/Settings/Odoo.vue`)**:
  - Konfigurasi URL server Odoo, Nama Database, Username/Email, API Key, dan batas waktu *timeout* yang tersimpan di database.
  - Fitur pengujian koneksi langsung (*⚡ Test Connection JSON-RPC*).

### 3. 📱 Desain Responsif & Kompatibilitas Mobile Penuh (Semua Halaman)
* **Mobile Topbar & Off-Canvas Slide Drawer (`AuthenticatedLayout.vue`)**:
  - Bilah navigasi atas khusus perangkat mobile (`lg:hidden`) dengan tombol hamburger menu, logo LinePulse, dan pintasan toggle tema (*Dark/Light Mode*).
  - Menu sidebar desktop bertransformasi menjadi *Off-Canvas Slide Drawer* dengan efek *backdrop blur*.
* **Dual-View System (Tabel Desktop + Kartu Mobile)**:
  - Seluruh halaman tabel data (*Reject Produk, Laporan Harian, Dashboard, Master Produk, Mesin, Line, Alasan Downtime, dan Users*) otomatis beralih menjadi format kartu sentuh (*Card View*) di layar mobile.
* **Detail Reject Odoo 2 Kotak Berjejer**:
  - Tampilan detail Odoo Scrap Order di mobile disusun dalam format **2 kolom berjejer (`grid-cols-2`)** yang ringkas, rapi, dan mudah dibaca.

### 4. 📅 Dukungan Multi-Proses Weekly Plan
* Mendukung hingga **3 tahapan proses bertahap sekaligus (Mixing → Filling → Packing)** untuk nomor batch dan produk yang sama pada satu tanggal produksi.

---

## 🛠️ II. CATATAN PERBAIKAN BUG & OPTIMASI (BUG FIXES)

| No | Kategori | Masalah Sebelum Perbaikan | Solusi / Perbaikan yang Diterapkan |
| :---: | :--- | :--- | :--- |
| **1** | **Scrollbar** | Scrollbar bawaan browser tebal, kaku, dan tidak cocok dengan tema gelap. | Dibuat custom scrollbar tipis (6px) membulat (*pill*) yang adaptif dengan tema Light & Obsidian Dark Mode di `app.css`. |
| **2** | **Input Angka** | Muncul tombol panah (*spinner*) pada input durasi downtime dan kuantitas. | Menambahkan CSS `.no-spinner` untuk menghapus tombol panah di semua browser dan menyetel default durasi `0` mnt. |
| **3** | **Heat Grid Mobile** | Kolom Filling & Packing pada monitoring proses terpotong di tepi layar HP. | Kolom disesuaikan menjadi `52px repeat(3, minmax(0, 1fr))` dan dibungkus *horizontal scroll* pelindung anti-overflow. |
| **4** | **Grafik Kosong** | Grafik merender canvas kosong setinggi 150px jika belum ada data transaksi. | Diberikan *empty-state placeholder* informatif yang ringkas saat data belum tersedia. |
| **5** | **Build & Compiler** | Galat kompilasi `Invalid end tag` saat menjalankan `npm run build`. | Memperbaiki dan membersihkan tag penutup `</div>` ganda pada file `Create.vue`, `Edit.vue`, dan `Show.vue`. |
| **6** | **Detail Scrap Mobile** | Detail Odoo Scrap di HP hanya tampil 4 baris 1 kolom dan terpotong. | Menyelaraskan seluruh 8 atribut data lengkap desktop ke dalam format **2 Kotak Berjejer (`grid-cols-2`)**. |
| **7** | **Padding Form HP** | Jarak vertikal `py-12` terlalu lebar di HP sehingga form turun ke bawah. | Menstandardisasi padding kontainer menjadi `px-3 sm:px-6 py-4 sm:py-6` agar form padat dan hemat scroll. |
| **8** | **Kredensial Odoo** | Pengaturan `.env` statis dan sulit diuji koneksinya oleh admin. | Mengalihkan konfigurasi ke database dengan form UI admin dan tombol uji koneksi langsung (*JSON-RPC v2.0*). |

---

## 📦 III. STATUS DEPLOYMENT & REPOSITORY

- **Branch**: `main`
- **Commit ID**: `d094648` (*feat: complete mobile responsive layout, floating timer widget, and Odoo ERP integration*)
- **Status Git**: **Berhasil di-push ke GitHub (`origin/main`)**
- **Status Build**: **`npm run build` sukses 100% tanpa error (Code 0)**
