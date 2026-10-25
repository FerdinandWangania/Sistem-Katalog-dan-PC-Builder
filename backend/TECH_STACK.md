# Tech Stack — Backend Sistem Katalog & PC Builder

Dokumen ini merangkum seluruh teknologi yang dipakai pada bagian backend,
disusun per lapisan agar mudah dipakai untuk dokumentasi/tugas.

---

## 1. Ringkasan (Ringkas)

| Lapisan | Teknologi |
|---|---|
| Bahasa pemrograman | PHP 8.4 (minimum requirement: PHP ^8.3) |
| Framework | Laravel 13 (laravel/framework ^13.17) |
| Database | MongoDB 7.0 (via mongodb/laravel-mongodb ^5.11, driver Eloquent) |
| Autentikasi | Token Bearer custom (SHA-256) + Middleware; Google OAuth (google/apiclient ^2.0) |
| Email | Laravel Mailable + SMTP Gmail (tls, port 587) |
| Payment gateway | **Midtrans** (Snap API — VA, e-wallet, QRIS, retail) |
| Server (dev) | PHP built-in server — `php artisan serve` (port 8000) |
| API style | REST JSON, stateless, CORS aktif |
| Rate limiting | Laravel Throttle Middleware (per-endpoint) |
| Testing/QA | PHPUnit 12, Laravel Pint (code style), Laravel Pail (log live) |
| Version control | Git → GitHub |

---

## 2. Rincian per Lapisan

### 2.1 Bahasa & Framework
- **PHP 8.4** (runtime) dengan requirement `^8.3` pada `composer.json`.
- **Laravel 13** — framework utama: routing, Eloquent ORM, validation,
  middleware, mail, rate limiter, config cache.
- **Composer 2** — manajemen dependensi (`composer install`, autoloader PSR-4).

### 2.2 Database
- **MongoDB 7.0.15** (Laragon), koneksi `mongodb://127.0.0.1:27017`, db `pc_store`.
- **mongodb/laravel-mongodb ^5.11** — paket resmi yang membuat model Eloquent
  (`MongoDB\Laravel\Eloquent\Model`) bekerja langsung di koleksi MongoDB
  (tanpa SQL/migrasi tabel — setup via `php artisan db:setup-mongo --fresh`).
- Koleksi utama: `users`, `products`, `carts`, `cart_items`, `orders`,
  `prebuilt_packages`, `package_items`, `promotions`.
- Session & cache memakai driver **file** (bukan database) agar 100% Mongo.

### 2.3 Autentikasi & Otorisasi
- **Token Bearer native**: token acak disimpan ter-hash SHA-256 di field
  `users.api_token`, berlaku **30 hari**, di-rotate setiap login (tanpa Sanctum,
  agar tidak butuh tabel SQL).
- **Middleware kustom**:
  - `TokenAuth` — validasi header `Authorization: Bearer …`.
  - `AdminOnly` — hanya role `admin`.
  - `EnsureOwner` — pemilik resource (user_id/session_id) atau admin (anti-spoofing).
- **Google Login**: `POST /api/login/google` memverifikasi *ID token* Google
  (Google Identity Services) memakai **google/apiclient ^2.0**.
- Role: `admin` / `customer`, dengan proteksi anti self-demote & minimal 1 admin.

### 2.4 Keamanan & Rate Limiting
- Hash password: Laravel `Hash` (bcrypt).
- **Throttle** per endpoint, contoh: login `10,1`, forgot/reset `5,1`
  (mencegah brute-force & spam email).
- Validasi input menyeluruh (`$request->validate`) + cek stok/harga saat checkout.
- **CORS** dikonfigurasi (`config/cors.php`) untuk frontend `localhost:5173`.
- Token verifikasi/reset email disimpan ter-hash + kadaluarsa (reset 1 jam).

### 2.5 Email
- **Laravel Mail** dengan **Mailable** kustom:
  - `VerifyEmailMail` — kode verifikasi akun.
  - `ResetPasswordMail` — kode reset password.
- Pengiriman via **SMTP Gmail** (`smtp.gmail.com:587`, enkripsi TLS)
  memakai **App Password**; gagal SMTP tidak mematikan request (fallback ke log).

### 2.6 Fitur Bisnis (dibangun di atas stack di atas)
- Katalog produk (CRUD admin), prebuilt package, promo.
- PC Builder: validasi kompatibilitas 7 komponen + cek wattage/stok.
- Cart multi-sumber: `catalog`, `builder`, `package` (digrupkan per `build_id`).
- Checkout & order: revalidasi harga, kurangi stok, `order_number` unik,
  status `pending → processing → shipped/delivered/cancelled`.
- Payment gateway: **Midtrans** (lihat bagian 2.7).
- Statistik dashboard admin: `GET /admin/stats` (user, produk, order, omzet, stok menipis).

### 2.7 Payment Gateway — Midtrans
- **Midtrans Snap API** dipilih sebagai payment gateway resmi proyek ini
  (tercantum pada dokumen SRS sebagai komponen pembayaran).
- Metode pembayaran yang didukung Snap: **Virtual Account** (bank BCA/BNI/BRI/Mandiri),
  **e-wallet** (GoPay, OVO, DANA, ShopeePay, LinkAja), **QRIS**, kartu kredit,
  dan gerai retail (Alfamart/Indomaret).
- Mode implementasi: **sandbox** terlebih dahulu (Server Key + Client Key sandbox),
  lalu produksi setelah akun Midtrans aktif.
- Alur pada backend:
  1. Checkout menghasilkan order berstatus `pending`.
  2. Endpoint pembayaran memanggil Snap → mengembalikan `redirect_url` / token
     untuk ditampilkan di frontend.
  3. **Callback notifikasi** dari Midtrans (server-to-server) diverifikasi
     signature-nya, lalu status order diperbarui otomatis
     (`settlement` → lunas, `deny/cancel/expiry` → gagal + restock stok).
- Status saat ini: endpoint `pay` masih **simulasi** (menunggu kredensial
  Midtrans); integrasi penuh dijadwalkan setelah akun sandbox tersedia.

### 2.8 Dokumentasi & Alat Bantu
- **Postman collection** (`backend/postman_collection.json`) — 10 grup, 50+ request.
- **DFD.md** — diagram alur level 0–1 + peta endpoint untuk frontend.
- **SETUP.md** — panduan instalasi & menjalankan.
- Seeder produk placeholder (gambar via `placehold.co`, field `image` = URL).

### 2.9 Development & Testing
- **PHPUnit 12** — unit/feature test.
- **Laravel Pint** — formatter & style checker kode.
- **Laravel Pail** — monitor log real-time.
- **Faker** (seed data), **Mockery** (mocking test).
- **Laravel Tinker** — REPL interaktif.
- Server dev: `php artisan serve --port=8000`.

---

## 3. Arsitektur Singkat

```
Klien (Postman / Frontend React localhost:5173)
        │  HTTP JSON + Bearer token
        ▼
Laravel 13 (REST API, port 8000)
   ├── Middleware: TokenAuth / AdminOnly / EnsureOwner / Throttle
   ├── Controllers (Auth, Product, Cart, Order, Builder, Package, Promo, Stats)
   ├── Mailable → SMTP Gmail
   ├── Payment → Midtrans Snap (sandbox)
   └── Eloquent (mongodb/laravel-mongodb)
              ▼
        MongoDB 7 (pc_store)
```

---

## 4. Versi Lingkungan Saat Ini
- PHP 8.4.26 (CLI, Laragon)
- Composer 2.10.3
- Laravel framework 13.x
- mongodb/laravel-mongodb 5.11
- MongoDB 7.0.15
