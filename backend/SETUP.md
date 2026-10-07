# Setup Backend — PC Store API (Laravel + MongoDB)

## Syarat
- PHP ^8.3 + extensi `mongodb`
- Composer
- MongoDB jalan di `mongodb://127.0.0.1:27017` (database `pc_store`)

## Langkah
```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan db:setup-mongo --fresh   # migrate + seed (13 produk, 5 user, promo, paket)
php artisan serve --port=8000        # API di http://127.0.0.1:8000/api
```

## Akun seed
| Role     | Email             | Password    |
|----------|-------------------|-------------|
| admin    | admin@pcstore.id  | admin123!   |
| customer | budi@mail.com     | password123 |
| customer | siti@mail.com     | password123 |

## Env penting (lihat `.env.example`)
- `MONGODB_URI`, `MONGODB_DATABASE`
- `GOOGLE_CLIENT_ID` — wajib diisi untuk `POST /api/login/google` (OAuth Web dari Google Cloud Console)

## Alur API utama
- Auth: `POST /api/register|login|logout|forgot-password|reset-password|verify-email|resend-verification`, `GET /api/me`
- Katalog publik: `GET /api/products|packages|promotions`
- Admin (Bearer + role admin): tulis produk, `GET /api/users`, `PATCH /api/users/{id}/role`, `PATCH /api/orders/{id}/status`
- Cart & checkout: pakai `user_id` (wajib token milik sendiri) atau `session_id` (guest)
- Builder: `POST /api/builder/validate`, `POST /api/builder/add-to-cart`
- Order user: `GET /api/orders`, `POST /api/orders/{id}/cancel` (pending saja)

## Catatan
- Auth pakai token Bearer (30 hari, rotate tiap login). Header: `Authorization: Bearer <token>`.
- `APP_DEBUG=true` membocorkan token verifikasi/reset di response — matikan di produksi + sambungkan email beneran.
- Payment masih simulasi (`POST /api/payments/{orderId}/pay`), Midtrans belum disambung.
