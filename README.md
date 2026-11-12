# IMC User Access Management

Aplikasi internal untuk mengelola user, level jabatan, page/menu, dan hak akses
(permission) berbasis level serta override per user.

**Stack:** Angular 20 + Angular Material · Slim 4 + PHP + PDO · PostgreSQL

## Fitur

- Login username/email + password dengan token JWT
- CRUD User — password di-hash, username & email unik, status aktif/nonaktif
- CRUD Level — tidak bisa dihapus selama masih dipakai user
- CRUD Page — path unik, urutan tampil, status aktif
- Permission matrix level × page
- Override per user: `allow` (tambah akses) / `deny` (cabut akses)
- Menu dan route guard mengikuti permission efektif; backend tetap menolak API tanpa izin

## Struktur project

```
IMC_Test_CRUD/
├─ api/                     REST API Slim 4
│  ├─ public/index.php      entry point
│  ├─ src/                  Controller, Service, Middleware, Support
│  ├─ db/                   schema.sql, seed.sql
│  ├─ .env                  konfigurasi DB & JWT
│  └─ composer.json
└─ web/                     Frontend Angular 20
   ├─ src/app/              core, layout, pages, shared
   ├─ proxy.conf.json       proxy /api → API backend
   └─ package.json
```

## Prasyarat

- PHP 8.1+ dengan ekstensi `pdo_pgsql`
- Composer
- PostgreSQL
- Node.js 20+ dan npm

## Initial setup

### 1. Aktifkan ekstensi PHP

Di `C:\xampp\php\php.ini`:

```ini
extension=pdo_pgsql
extension=pgsql
```

Restart Apache/PHP, lalu cek:

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

Generate `JWT_SECRET`:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

### 4. Generate autoload Composer

```bash
cd api
composer install
composer dump-autoload
```

### 5. Jalankan skema & seed database

```bash
cd api
psql -U postgres -d imc_permission -f db/schema.sql
psql -U postgres -d imc_permission -f db/seed.sql
```

Seed memakai `pgcrypto` untuk hash password bcrypt.

### 6. Install dependency frontend

```bash
cd web
npm install
```

## Menjalankan setelah setup

**Terminal 1 — API:**

```bash
cd api
php -S 127.0.0.1:8080 -t public public/index.php
```

**Terminal 2 — Web:**

```bash
cd web
npm start
```

Buka `http://localhost:4200`. Frontend memakai `proxy.conf.json` yang meneruskan
`/api` ke `http://127.0.0.1:8080`.

## Akun demo

| Username | Password | Level | Akses |
|---|---|---|---|
| `admin` | `password123` | Super Admin | Semua halaman |
| `manager` | `password123` | Manager | Dashboard (deny `/users`) |
| `staff` | `password123` | Staff | Dashboard + `/users` (allow) |
| `viewer` | `password123` | Viewer | Dashboard |

## Logika permission

```
permission efektif = (page milik level − override deny) + override allow
```

Hanya page `is_active` yang dipakai untuk menu dan pengecekan akses. Perubahan
permission langsung berlaku pada request berikutnya.

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

- **`could not find driver`** → `pdo_pgsql` belum aktif atau `php.ini` yang dipakai
  berbeda (`php --ini`).
- **503 Koneksi database gagal** → periksa `DB_*` di `api/.env` dan pastikan database
  sudah dibuat.
- **500 `JWT_SECRET`** → isi minimal 32 karakter.
- **Proxy `ECONNREFUSED` / `AggregateError`** → API belum berjalan; jalankan PHP server
  di terminal terpisah dan pastikan target proxy memakai `127.0.0.1`.
- **Level gagal dihapus (409)** → masih digunakan user; pindahkan atau hapus user
  tersebut dulu.
- **Menu kosong** → level user belum diberi page pada tab Permission per Level.
