<p align="center">
  <img src="public/brand/aurevant-icon.png" alt="Logo Aurevant" width="120" />
</p>

<h1 align="center">Aurevant Server</h1>

<p align="center">
  <strong>API dan backend untuk operasional multi-cabang.</strong><br />
  Kasir, stok, order jasa, absensi, penggajian, dan keuangan dalam satu alur data.
</p>

<p align="center">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" />
  <img alt="PHP 8.3+" src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" />
  <img alt="MySQL 8" src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" />
  <img alt="Sanctum" src="https://img.shields.io/badge/Auth-Sanctum-005D42" />
  <img alt="Primary color" src="https://img.shields.io/badge/primary-%23005D42-005D42" />
</p>

Aurevant Server adalah API dan backend untuk **Aurevant**, aplikasi operasional multi-cabang untuk bisnis barang dan jasa. Repo ini menangani autentikasi, multi-perusahaan, cabang, role dan permission, transaksi, stok, order jasa, SDM, penggajian, keuangan, laporan, notifikasi, dan audit log.

Repo ini adalah bagian dari ekosistem Aurevant:

- `Aurevant-Server` — API dan backend Laravel
- `Aurevant-Client` — aplikasi web Vue 3
- `Aurevant-PKK` — monorepo, aplikasi Android, dan dokumentasi produk

## Daftar isi

- [Fitur API](#fitur-api)
- [Stack](#stack)
- [Arsitektur](#arsitektur)
- [Struktur repo](#struktur-repo)
- [Prasyarat](#prasyarat)
- [Menjalankan project](#menjalankan-project)
- [Environment penting](#environment-penting)
- [Akun demo](#akun-demo)
- [Kontrak API](#kontrak-api)
- [Testing](#testing)
- [Repo terkait](#repo-terkait)
- [Dokumentasi](#dokumentasi)
- [Catatan keamanan](#catatan-keamanan)
- [Batasan saat ini](#batasan-saat-ini)

## Fitur API

### Autentikasi dan platform

- Login dengan email atau nomor HP, register, lupa password, reset password, dan ganti password.
- Sanctum bearer token untuk web dan Android.
- Multi-perusahaan, pemilihan company, dan pembatasan data berdasarkan `company_id`.
- Role, permission, penempatan karyawan, dan audit log.
- Paket langganan, masa trial, status langganan, dan pembatasan kuota.

### Operasional

- Kasir dengan shift, nota barang dan jasa, diskon, DP, split payment, void, retur, dan refund.
- Katalog item, harga per cabang, stok per cabang, transfer antar cabang, dan stok opname.
- Purchase order, penerimaan barang bertahap, retur supplier, dan tagihan supplier.
- Order jasa, agenda teknisi, estimasi biaya, paket jasa, dan Customer 360.

### SDM

- Jadwal shift, pola mingguan, roster, dan override per tanggal.
- Absensi QR dinamis, geofence, selfie, dan satu akun satu perangkat.
- Cuti, saldo cuti, lembur, tukar shift, dan persetujuan.
- Penggajian dengan komponen fleksibel, slip PDF, dan kartu karyawan.

### Keuangan dan laporan

- Jurnal otomatis, laba rugi, neraca, arus kas, dan laporan per cabang maupun konsolidasi.
- Hutang, piutang, biaya operasional, anggaran, aset tetap, dan rekonsiliasi bank.
- Ekspor laporan Excel dan PDF.
- Notifikasi in-app, push FCM untuk Android, dan audit log append-only.

## Stack

| Bagian | Teknologi |
|---|---|
| Framework | Laravel 13 |
| Bahasa | PHP 8.3+ |
| Autentikasi | Laravel Sanctum |
| Database | MySQL 8 / MariaDB |
| Permission | Spatie Permission |
| Queue dan cache | Database driver, siap Redis |
| Testing | PHPUnit |
| Code style | Laravel Pint |

## Arsitektur

Backend memakai pemisahan lapisan agar aturan bisnis tidak bergantung langsung pada Eloquent atau HTTP:

```text
app/
├── Domain/          # Entitas, kontrak repository, aturan bisnis
├── Application/     # Use case dan DTO
├── Infrastructure/  # Eloquent, repository, integrasi eksternal
└── Http/            # Controller, request, middleware
```

Prinsip utama:

- Semua query bisnis dibatasi oleh `company_id`.
- Permission memakai nama yang sama di backend, web, dan Android.
- Nomor nota dan idempotency key dibuat di server.
- Aksi penting dicatat ke audit log.
- Operasi yang memengaruhi stok dan keuangan dijalankan dalam transaksi database.

## Struktur repo

```text
.
├── app/
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
│   └── brand/
├── resources/
├── routes/
│   └── api.php
├── storage/
├── tests/
├── composer.json
├── composer.lock
├── phpunit.xml
└── .env.example
```

## Prasyarat

- PHP 8.3 atau lebih baru
- Composer 2
- MySQL 8 atau MariaDB yang kompatibel
- Node.js `^22.18.0` atau `>=24.12.0` jika ingin membangun asset Vite
- npm
- Queue worker dan scheduler untuk fitur latar belakang

## Menjalankan project

### 1. Clone dan install dependency

```bash
git clone https://github.com/Kalaigram/Aurevant-Server.git
cd Aurevant-Server
composer install
```

### 2. Siapkan environment

```bash
cp .env.example .env
php artisan key:generate
```

Di PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Sesuaikan koneksi database di `.env`:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aurevant_pkk
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Buat database

```sql
CREATE DATABASE aurevant_pkk
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 4. Migrasi dan seed

```bash
php artisan migrate --seed
php artisan storage:link
```

`migrate --seed` membuat permission, paket langganan, dan data demo. Untuk database tanpa data demo, jalankan `php artisan migrate` saja.

### 5. Jalankan API

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

API tersedia di `http://127.0.0.1:8000/api/v1`.

### 6. Jalankan queue worker dan scheduler

Di terminal terpisah:

```bash
php artisan queue:work
php artisan schedule:work
```

Queue worker diperlukan untuk audit log, notifikasi, dan proses latar belakang. Scheduler menjalankan tugas terjadwal seperti pengingat langganan.

## Environment penting

| Variable | Fungsi |
|---|---|
| `APP_NAME` | Nama aplikasi |
| `APP_URL` | URL backend |
| `FRONTEND_URL` | Origin web untuk CORS dan tautan frontend |
| `DB_CONNECTION` | Driver database, default `mysql` |
| `DB_DATABASE` | Nama database |
| `DB_USERNAME` | User database |
| `DB_PASSWORD` | Password database |
| `QUEUE_CONNECTION` | Driver queue, default `database` |
| `FIREBASE_CREDENTIALS` | Path service account Firebase, opsional |
| `FIREBASE_PROJECT_ID` | ID proyek Firebase, opsional |

Jika Firebase kosong, push notification nonaktif tetapi aplikasi tetap berjalan.

## Akun demo

Tersedia setelah `php artisan migrate --seed`:

| Peran | Email |
|---|---|
| Super Admin | `superadmin@demo.id` |
| Pemilik | `pemilik@demo.id` |
| Manajer | `manajer@demo.id` |
| Kasir | `kasir@demo.id` |
| Gudang | `gudang@demo.id` |
| Teknisi | `teknisi@demo.id` |
| HR | `hr@demo.id` |
| Keuangan | `keuangan@demo.id` |

Sandi semua akun demo: `password123`.

Akun ini hanya untuk pengembangan lokal. Jangan memakai data atau sandi demo di environment produksi.

## Kontrak API

- Base path: `/api/v1`
- Autentikasi: Bearer token Sanctum
- Format respons:

```json
{
  "status": true,
  "message": "Pesan untuk pengguna",
  "data": {},
  "errors": null
}
```

- Semua query bisnis dibatasi oleh `company_id`.
- Permission memakai nama yang sama di backend, web, dan Android.
- Nomor nota dan idempotency key dibuat di server.
- Daftar endpoint ada di `routes/api.php`.

## Testing

```bash
php artisan test
./vendor/bin/pint --test
```

Di PowerShell:

```powershell
php artisan test
.\vendor\bin\pint --test
```

Sebelum commit, jalankan pemeriksaan yang relevan dan pastikan `git status` tidak memuat file rahasia.

## Repo terkait

| Repo | Isi |
|---|---|
| [`Aurevant-Client`](https://github.com/Kalaigram/Aurevant-Client) | Aplikasi web Vue 3 |
| [`Aurevant-Server`](https://github.com/Kalaigram/Aurevant-Server) | API dan backend Laravel |
| [`Aurevant-PKK`](https://github.com/Kalaigram/Aurevant-PKK) | Monorepo, Android, dan dokumentasi produk |

## Dokumentasi

Dokumentasi produk dan aturan bisnis ada di monorepo:

- [`doc/spek.md`](https://github.com/Kalaigram/Aurevant-PKK/blob/main/doc/spek.md) — spesifikasi produk dan aturan bisnis
- [`doc/halaman.md`](https://github.com/Kalaigram/Aurevant-PKK/blob/main/doc/halaman.md) — peta halaman dan endpoint
- [`doc/monitoring-progress.md`](https://github.com/Kalaigram/Aurevant-PKK/blob/main/doc/monitoring-progress.md) — progres implementasi
- [`doc/DESIGN.md`](https://github.com/Kalaigram/Aurevant-PKK/blob/main/doc/DESIGN.md) — design system calm green

## Catatan keamanan

- Jangan commit `.env`, service account Firebase, keystore, atau kredensial asli.
- Gunakan salinan dari `.env.example` untuk konfigurasi lokal.
- Ganti seluruh kredensial demo dan set `APP_DEBUG=false` sebelum produksi.
- Batasi akses database dan storage sesuai environment.
- Gunakan HTTPS di produksi.

## Batasan saat ini

- Payment gateway, loyalty, dan notifikasi WhatsApp belum termasuk.
- Mode offline kasir belum tersedia.
- Deployment produksi memerlukan HTTPS, SMTP, queue worker, scheduler, storage, dan konfigurasi Firebase bila push notification dipakai.
