# JarMon

Panel manajemen hotspot MikroTik dengan UI modern. Kelola pengguna, voucher, profil, laporan penjualan, jaringan, PPP, dan monitoring router dari satu tempat.

![version](https://img.shields.io/badge/version-1.0.0-6366f1)

## Fitur

- **Dashboard** — sesi aktif, jumlah pengguna, pendapatan hari ini/bulan, kesehatan CPU/RAM/disk, grafik traffic langsung, log hotspot.
- **Pengguna hotspot** — tambah/edit/hapus, kunci perangkat, batas waktu & kuota, aksi massal (aktifkan, nonaktifkan, hapus, cetak), ekspor CSV, pencarian & pagination.
- **Profil** — shared users, rate limit, address pool, parent queue, kunci perangkat, dan mode kedaluwarsa (Remove / Notice / Remove & Record / Notice & Record) lengkap dengan scheduler monitor otomatis.
- **Voucher Studio** — generate massal (username=password atau user+password), prefix, panjang & jenis karakter, template Standar / Kompakt / Thermal, QR login, dan cetak ulang per batch/komentar.
- **Paket cepat** — preset paket yang bisa disimpan dan dicetak dengan satu klik.
- **Laporan penjualan** — rekap otomatis dari on-login profile, filter tanggal/bulan/prefix, ringkasan per profil, ekspor CSV, halaman cetak, dan hapus data periode.
- **Jaringan** — hosts, IP binding (regular/bypassed/blocked), cookies, DHCP leases, interfaces.
- **Traffic monitor** — grafik real-time per antarmuka.
- **PPP** — secrets, profiles, dan sesi aktif.
- **Sistem** — scheduler CRUD, identity, reboot/shutdown, audit log aktivitas admin.
- **Multi-router** — simpan banyak router dan berpindah konteks kapan saja.
- **Keamanan** — password admin bcrypt, kredensial router terenkripsi AES-256-GCM, CSRF token, auto logout, audit log, backup/restore konfigurasi.
- **UI** — tema gelap/terang, aksen warna, sidebar scroll independen, layout responsif desktop/tablet/mobile, command palette, pencarian tabel, sort, pagination, toast, dan dialog.
- **Branding** — unggah logo voucher per router melalui Pengaturan → Logo.

## Kebutuhan

- PHP 8.1–8.5 dengan ekstensi `openssl` (umumnya sudah aktif).
- Web server Apache/Nginx (Laragon, XAMPP, atau hosting PHP).
- MikroTik RouterOS v6.43+ hingga v7 terbaru dengan layanan API aktif (port 8728 atau 8729 untuk SSL).

## Kompatibilitas RouterOS

- Monitor kedaluwarsa profil berjalan otomatis setiap **1 menit** di router. Jika baru memindahkan router atau memperbarui JarMon, buka **Profil → Perbaiki monitor** untuk menyinkronkan ulang scheduler.

- Login API mendukung metode pra-6.43 (challenge/MD5) dan pasca-6.43.
- Format tanggal RouterOS lama (`nov/19/2026`) dan ISO (`2026-11-19`, v7.10+) dideteksi otomatis pada script kedaluwarsa, monitor profil, dan laporan.
- Script penjualan tidak memakai properti `owner` yang bersifat read-only di RouterOS 7, sehingga pencatatan transaksi tetap berjalan.
- Penggunaan socket PHP sudah bebas fungsi deprecated (`socket_set_timeout`/`socket_get_status`) dan aman di PHP 8.5.

## Instalasi

1. Letakkan folder ini di web root, misalnya `C:\laragon\www\JarMon`.
2. Buka `http://localhost/JarMon/` di peramban.
3. Buat akun administrator pada halaman setup.
4. Tambahkan router:
   - **Host/IP**: alamat router, misal `192.168.88.1`
   - **Port API**: `8728` (atau `8729` bila SSL)
   - **User/Password**: user dengan hak API
   - **Nama hotspot**, **DNS login** (dipakai voucher), dan **interface** (untuk traffic)
5. Aktifkan API di router:

```routeros
/ip service enable api
/ip service set api port=8728
```

Untuk keamanan, buat user khusus:

```routeros
/user group add name=api policy=api,read,write,test,winbox
/user add name=jarmon group=api password=password-kuat
```

> Untuk akses jarak jauh sebaiknya aktifkan API SSL (`/ip service enable api-ssl`) dan gunakan port 8729 + centang "Gunakan API SSL" di JarMon.

## Keamanan data

- Konfigurasi tersimpan di `data/config.json` (dilindungi `.htaccess` untuk Apache). Setiap penyimpanan membuat cadangan `config.bak.json`.
- Riwayat batch voucher (termasuk kredensial) tersimpan di `data/batches.json` agar dapat dicetak ulang tanpa membuat voucher baru. Batas 60 batch terbaru.
- Password router dienkripsi dengan kunci `data/secret.key` (AES-256-GCM).
- Backup dari **Pengaturan → Backup** memuat konfigurasi + kunci; simpan berkas backup di tempat aman.
- Jika memakai Nginx, tambahkan aturan agar folder `app/`, `data/`, `views/`, `lang/`, dan `lib/` tidak dapat diakses langsung.

## Pintasan

| Aksi | Pintasan |
| --- | --- |
| Command palette | `Ctrl` + `K` |
| Fokus pencarian tabel | `/` |
| Tutup dialog/menu | `Esc` |
| Cetak voucher / laporan | `Ctrl` + `P` |

## Struktur

```
index.php            Front controller
app/                 Bootstrap, helper, API layer, UI helper
views/               Layout & halaman
assets/              CSS, JS, QR library, ikon
lang/                String antarmuka
lib/                 RouterOS PHP API
data/                Konfigurasi & audit (tidak untuk diakses publik)
```

## Lisensi & kredit

Dirilis di bawah GNU General Public License v2 (lihat `LICENSE`).

- **RouterOS PHP API class** oleh Denis Basta dan kontributor.
- **QRious** untuk pembuatan QR code.
