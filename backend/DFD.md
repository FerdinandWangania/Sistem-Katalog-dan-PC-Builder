# DFD — PC Store API (acuan Frontend)

Sistem: **PC Store API** (`backend/`, base URL `http://127.0.0.1:8000/api`).
Auth: Bearer token (`Authorization: Bearer <token>`), kecuali endpoint publik.

## Level 0 — Diagram Konteks

```mermaid
flowchart LR
    G[Guest] -->|register/login, katalog, builder, cart session, checkout guest| API((PC Store API))
    C[Customer] -->|login, cart, checkout, bayar, riwayat, cancel| API
    A[Admin] -->|kelola produk, user/role, status pesanan| API
    GI[Google Identity] -->|ID token| API
    API -->|katalog, promo, validasi builder| G
    API -->|token, cart, order, payment| C
    API -->|data admin| A
    API -.->|simulasi, Midtrans belum disambung| PG[(Payment Gateway)]
```

## Level 1 — Rincian Proses

```mermaid
flowchart TD
    G[Guest] --> P1
    C[Customer] --> P1
    C --> P3
    G --> P2
    C --> P2
    A[Admin] --> P7
    GI[Google Identity] --> P1

    subgraph API [PC Store API]
        P1((1. Autentikasi))
        P2((2. Katalog & Promo))
        P3((3. PC Builder))
        P4((4. Keranjang))
        P5((5. Checkout & Pesanan))
        P6((6. Pembayaran))
        P7((7. Administrasi))
    end

    P1 <--> D1[(D1 users)]
    P2 --> D2[(D2 products)]
    P2 --> D6[(D6 promotions)]
    P2 --> D7[(D7 packages)]
    P3 --> D2
    P4 <--> D3[(D3 carts + items)]
    P4 --> D2
    P5 <--> D3
    P5 <--> D4[(D4 orders + items)]
    P5 --> D5[(D5 payments)]
    P5 --> D2
    P6 <--> D5
    P6 --> D4
    P7 <--> D1
    P7 <--> D2
    P7 --> D4

    P1 --> C
    P1 --> G
    P2 --> G
    P2 --> C
    P3 --> P4
    P4 --> P5
    P5 --> P6
    P5 --> C
    P6 --> C
    P7 --> A
```

## Aliran data per proses (endpoint)

### 1. Autentikasi → D1 users
| Aliran | Endpoint | Keterangan |
|---|---|---|
| Registrasi | `POST /register` | `{name,email,password,phone}` → user + token. Role dikunci customer |
| Login | `POST /login` | `{email,password}` → user + token (rotate, 30 hari) |
| Login Google | `POST /login/google` | `{id_token}` GIS → user + token, email auto-verified |
| Profil / logout | `GET /me`, `POST /logout` | Butuh token |
| Lupa password | `POST /forgot-password` → `POST /reset-password` | Token 1 jam (debug: tampil di response) |
| Verifikasi email | `POST /verify-email`, `POST /resend-verification` | Wajib sebelum checkout user terdaftar |

### 2. Katalog & Promo (publik) → D2/D6/D7
`GET /products (?category,brand,q,socket,ram_type,sort)`, `GET /products/{id}`,
`GET /packages`, `GET /packages/{id}`, `GET /promotions` (hanya yang aktif).
Beli paket: `POST /api/packages/{id}/add-to-cart` → masuk cart sebagai `package_builds` (grup `build_id`).
Item produk membawa: `_id, category, brand, name, price, discount_price (harga jual), stock, image (URL), specs`.

### 3. PC Builder → D2, lanjut ke P4
`POST /builder/validate` `{cpu_id, motherboard_id, ram_id, storage_id, gpu_id, case_id, psu_id}`
→ `{is_compatible, errors[], warnings[], total_price, power_analysis}`.
`POST /builder/add-to-cart` — sama + `user_id`/`session_id`; 422 bila tidak kompatibel/stok habis.

### 4. Keranjang ↔ D3 (+D2 harga/stok)
`GET /cart`, `POST /cart/items {product_id,qty,source_type}`, `DELETE /cart/items/{id}`, `POST /cart/clear`.
Identitas: `user_id` (wajib token milik sendiri) atau `session_id` (guest).
Response `show` memisahkan `catalog_items` dan `builder_builds` (grup `build_id`).

### 5. Checkout & Pesanan ↔ D3/D4/D5/D2
`POST /orders/checkout {user_id|session_id, shipping_address, shipping_fee?}`
→ validasi stok + refresh harga → `Order(pending)` + `OrderItem` snapshot + `Payment(pending)` → cart dikosongkan + stok dikurangi.
`GET /orders` (milik sendiri; admin semua), `GET /orders/{id}` (pemilik/admin),
`POST /orders/{id}/cancel` (pemilik, pending saja), `PATCH /orders/{id}/status` (admin).
Status: `pending → processing → shipped → delivered`, atau `cancelled`.

### 6. Pembayaran ↔ D5/D4
`POST /payments/{orderId}/pay` (pemilik/admin, simulasi) → payment `paid`, order `processing`.

### 7. Administrasi (Bearer + admin)
CRUD produk (`POST/PUT/DELETE /products`), CRUD paket (`POST/PUT/DELETE /packages`),
CRUD promo (`POST/PUT/DELETE /promotions`), `GET /users`, `PATCH /users/{id}/role`,
`PATCH /orders/{id}/status`, `GET /admin/stats` (ringkasan user/produk/order/omzet/stok menipis).

## Catatan untuk Frontend
- Harga jual = `discount_price > 0 ? discount_price : price`.
- Gambar = `product.image` (URL langsung ke `<img>`).
- Guest checkout didukung via `session_id`; user login wajib verifikasi email dulu.
- Simpan `token` + `user.role` setelah login untuk routing dashboard admin/user.
