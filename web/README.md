# IMC User Access Management - Web

Frontend **Angular 20 + Angular Material** untuk aplikasi manajemen user, level, page,
dan hak akses (permission).

## Prasyarat

- Node.js 20+ dan npm
- API berjalan di `http://localhost:8080` (lihat `../api/README.md`)

## Menjalankan

```bash
npm install
npm start
```

Aplikasi terbuka di `http://localhost:4200`.

Dev server memakai `proxy.conf.json`, sehingga semua request ke `/api`
diteruskan ke `http://localhost:8080`. Tidak perlu mengubah URL di kode.

Build produksi:

```bash
npm run build
```

## Akun demo

| Username | Password | Level | Akses |
|---|---|---|---|
| `admin` | `password123` | Super Admin | Semua halaman |
| `manager` | `password123` | Manager | Dashboard (akses `/users` di-deny lewat override) |
| `staff` | `password123` | Staff | Dashboard + `/users` (allow override) |
| `viewer` | `password123` | Viewer | Dashboard |

## Halaman & route

| Route | Permission page | Keterangan |
|---|---|---|
| `/login` | publik | Login username/email + password |
| `/dashboard` | login saja | Ringkasan dan menu yang dapat diakses |
| `/users` | `/users` | CRUD user (dialog form, pencarian, status) |
| `/levels` | `/levels` | CRUD level jabatan |
| `/pages` | `/pages` | CRUD page/menu (nama, path, urutan, status) |
| `/permissions` | `/permissions` | Matrix permission per level + override per user |

Menu sidebar dan statistik dashboard hanya menampilkan page yang menjadi
permission efektif user yang sedang login.

## Permission

- **Level permission**: daftar page yang boleh diakses sebuah level.
- **User override**: `allow` menambah akses, `deny` mencabut akses level.
- Permission efektif dihitung di backend:

  ```
  (page milik level - override deny) + override allow
  ```

- Hanya page `is_active` yang dipakai untuk menu dan pengecekan akses.
- Backend tetap memvalidasi permission pada setiap request (bukan hanya UI),
  sehingga user yang mencoba membuka URL langsung tetap ditolak.

## Struktur singkat

```
src/app/
  core/          auth, guard, interceptor, service API, model, util form
  layout/        shell sidebar + toolbar
  pages/
    login/
    dashboard/
    users/       list + form dialog
    levels/      list + form dialog
    pages/       list + form dialog
    permissions/ matrix level & override user
  shared/        confirm dialog
```

## Catatan teknis

- Token JWT disimpan di `localStorage` (`imc_access_token`) dan dikirim lewat
  header `Authorization: Bearer <token>` oleh functional interceptor.
- Route terproteksi memakai functional guard: `authGuard` untuk login dan
  `permissionGuard` untuk permission halaman.
- Data sesi (user, permissions, menus) disimpan pada Angular signals di
  `AuthService`, dan dimuat ulang lewat `/api/auth/me`.
