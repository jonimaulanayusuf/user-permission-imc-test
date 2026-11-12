# IMC User Access Management - API

REST API berbasis **Slim 4 + PHP + PostgreSQL**.

## Prasyarat

- PHP 8.1+ dengan ekstensi `pdo_pgsql` dan `pgsql` aktif
- Composer
- PostgreSQL

## Setup

1. Install dependency (jika `vendor/` belum ada):

   ```bash
   composer install
   ```

2. Generate autoload untuk namespace `App\`:

   ```bash
   composer dump-autoload
   ```

3. Isi koneksi database dan `JWT_SECRET` pada file `.env`.
   `JWT_SECRET` minimal 32 karakter.

4. Buat database, lalu jalankan skema dan seed:

   ```bash
   createdb -U postgres imc_permission
   psql -U postgres -d imc_permission -f db/schema.sql
   psql -U postgres -d imc_permission -f db/seed.sql
   ```

   Seed menggunakan `pgcrypto` untuk membuat hash password bcrypt.
   Jika ekstensi `pgcrypto` tidak tersedia, ganti `crypt('password123', gen_salt('bf', 10))`
   dengan hash bcrypt hasil `php -r "echo password_hash('password123', PASSWORD_DEFAULT);"`.

5. Jalankan server:

   ```bash
   php -S localhost:8080 -t public public/index.php
   ```

   API tersedia di `http://localhost:8080/api`.

## Akun seed

| Username | Email | Password | Level |
|---|---|---|---|
| `admin` | `admin@imc.test` | `password123` | Super Admin |
| `manager` | `manager@imc.test` | `password123` | Manager |
| `staff` | `staff@imc.test` | `password123` | Staff |
| `viewer` | `viewer@imc.test` | `password123` | Viewer |

## Format response

```json
{ "success": true, "data": {}, "message": "opsional" }
```

```json
{ "success": false, "message": "Pesan error", "errors": { "field": ["Pesan validasi"] } }
```

## Endpoint

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

Kirim token pada header:

```
Authorization: Bearer <token>
```

## Logika permission

Permission efektif dihitung setiap request:

```
(page milik level user - override deny) + override allow
```

Hanya page dengan `is_active = 1` yang dipakai untuk menu dan pengecekan akses.
Perubahan permission langsung berlaku pada request berikutnya tanpa menunggu token kedaluwarsa.
