# IMC User Access Management

Aplikasi internal untuk mengatur akses halaman berdasarkan user dan level jabatan.

Stack: Angular 20, Angular Material, Slim 4, PHP, PostgreSQL.

## Fitur

- Login dengan username atau email dan password. Sesi memakai token JWT.
- CRUD user. Password disimpan sebagai hash, username dan email unik, status akun bisa diaktifkan atau dinonaktifkan.
- CRUD level. Level yang masih dipakai user tidak bisa dihapus.
- CRUD page. Path unik, ada urutan tampil dan status aktif.
- Pengaturan permission per level lewat matrix.
- Pengecualian per user: allow untuk menambah akses, deny untuk mencabut akses.
- Menu dan route mengikuti permission user. API juga menolak request tanpa izin.

## Struktur project

```
(workspace)/
├─ api/                     REST API Slim 4
│  ├─ public/index.php      entry point
│  ├─ src/                  Controller, Service, Middleware, Support
│  ├─ db/                   schema.sql, seed.sql
│  ├─ .env                  konfigurasi DB dan JWT
│  └─ composer.json
└─ web/                     Frontend Angular 20
   ├─ src/app/              core, layout, pages, shared
   ├─ proxy.conf.json       proxy /api ke API backend
   └─ package.json
```

## Prasyarat

- PHP 8.1 atau lebih baru dengan ekstensi `pdo_pgsql`
- Composer
- PostgreSQL
- Node.js 20 atau lebih baru dan npm

## Setup awal

### 1. Aktifkan ekstensi PHP

Di `php.ini` (untuk XAMPP ada di `C:\xampp\php\php.ini`), aktifkan:

```ini
extension=pdo_pgsql
extension=pgsql
```

Restart Apache atau PHP, lalu cek:

```bash
php -m | findstr pgsql
```

### 2. Buat database

```bash
createdb -U postgres imc_permission
```

### 3. Konfigurasi API

Isi `api/.env`:

```env
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=imc_permission
DB_USER=postgres
DB_PASSWORD=ganti_dengan_password_anda
JWT_SECRET=ganti_dengan_string_acak_minimal_32_karakter
JWT_TTL=28800
CORS_ORIGIN=http://localhost:4200
```

Buat nilai `JWT_SECRET` minimal 32 karakter, misalnya:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

### 4. Autoload Composer

```bash
cd api
composer install
composer dump-autoload
```

### 5. Jalankan skema dan seed database

```bash
cd api
psql -U postgres -d imc_permission -f db/schema.sql
psql -U postgres -d imc_permission -f db/seed.sql
```

Seed memakai `pgcrypto` untuk hash password bcrypt.

### 6. Dependency frontend

```bash
cd web
npm install
```

## Menjalankan aplikasi

Terminal pertama, jalankan API:

```bash
cd api
php -S 127.0.0.1:8080 -t public public/index.php
```

Terminal kedua, jalankan web:

```bash
cd web
npm start
```

Web berjalan di `http://localhost:4200`. Request ke `/api` diteruskan ke API oleh `proxy.conf.json`.

## Akun demo

| Username | Password | Level | Akses |
|---|---|---|---|
| `admin` | `password123` | Super Admin | Semua halaman |
| `manager` | `password123` | Manager | Dashboard (deny `/users`) |
| `staff` | `password123` | Staff | Dashboard dan `/users` (allow) |
| `viewer` | `password123` | Viewer | Dashboard |

## Logika permission

Permission efektif dihitung dari page milik level, dikurangi override deny, lalu ditambah override allow.

```
page milik level - deny + allow
```

Hanya page aktif yang dipakai untuk menu dan pengecekan akses. Perhitungan dilakukan di setiap request, jadi perubahan permission langsung berlaku.

## Endpoint API

| Method | Endpoint | Permission page |
|---|---|---|
| POST | `/api/auth/login` | publik |
| GET | `/api/auth/me` | login |
| GET/POST | `/api/users` | `/users` |
| GET/PUT/DELETE | `/api/users/{id}` | `/users` |
| GET/POST | `/api/levels` | `/levels` |
| GET/PUT/DELETE | `/api/levels/{id}` | `/levels` |
| GET/POST | `/api/pages` | `/pages` |
| GET/PUT/DELETE | `/api/pages/{id}` | `/pages` |
| GET | `/api/permissions` | `/permissions` |
| PUT | `/api/permissions/levels/{levelId}` | `/permissions` |
| GET/PUT | `/api/permissions/users/{userId}` | `/permissions` |

## Troubleshooting

- `could not find driver`: `pdo_pgsql` belum aktif, atau `php.ini` yang dipakai berbeda. Cek dengan `php --ini`.
- 503 Koneksi database gagal: periksa `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD` di `api/.env`. Pastikan database sudah dibuat.
- 500 `JWT_SECRET`: isi `JWT_SECRET` minimal 32 karakter.
- `ECONNREFUSED` atau `AggregateError` di proxy: API belum berjalan. Jalankan server PHP di terminal terpisah dan pastikan target proxy memakai `127.0.0.1`.
- Level gagal dihapus (409): level masih dipakai user. Pindahkan atau hapus user tersebut dulu.
- Menu kosong: level user belum diberi page di tab Permission per Level.
