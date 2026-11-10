# Checklist Implementasi PRD — BuildWise PC Store

Sumber acuan: `PRD BuildWise PC Store` (32 halaman) dan urutan pengerjaan §15.

Legenda status:

- `[x]` selesai & terverifikasi
- `[~]` sedang dikerjakan
- `[ ]` belum dikerjakan

Prioritas mengikuti PRD §15: **P1 → P2 → P3**, lalu frontend.

## Ringkasan progres

| Area | Status |
|------|--------|
| P1 — Integritas Rakitan & Checkout | ✅ selesai |
| P1 — Kontrol Akses & Manajemen Akun | ✅ selesai (penegakan wajib ganti password menyusul di P3) |
| P1 — Modul Keuangan | ✅ selesai |
| P2 — Promo, Alamat, Ulasan, Midtrans | ⬜ belum |
| P3 — Auth Lanjutan & Kebersihan | ⬜ belum |
| Frontend React | ⬜ belum |

Verifikasi terakhir: `php artisan test` → **22 passed (83 assertions)**; smoke test dengan data seeder (validate, add-to-cart, replace build, login role, laporan keuangan) berhasil.

---

## P1 — Integritas Rakitan & Checkout

Target: rakitan tidak bisa lolos checkout dalam kondisi tidak lengkap/tidak kompatibel, dan data teknis konsisten.

- [x] Normalisasi socket CPU ↔ Motherboard (RULE-01): huruf besar, tanpa spasi, tanda hubung, dan garis bawah.
- [x] Field numerik baru: `specs.length` (GPU, mm) dan `specs.wattage_w` (PSU, W) + fallback parser untuk data lama (`"422mm"`, `"1000W"`).
- [x] RULE-03: PSU kurang daya menjadi **error** (bukan warning), dengan flag `ignore_psu_warning` untuk memaksa lanjut.
- [x] RULE-05: kelengkapan 7 slot + cek ketersediaan/stok di validation engine, tampilkan daftar slot kosong.
- [x] RULE-02 & RULE-06 memakai hierarki form factor dan angka numerik (bukan default 320/400 mm).
- [x] Validation engine dipindah ke service reusable (`backend/app/Services/CompatibilityService.php`).
- [x] `POST /builder/validate` memakai service + mendukung `build_name` max 60 karakter.
- [x] Item ekstra: `extra_product_ids[]` (13 slot) dicek keberadaan + stok dan ikut total.
- [x] `POST /builder/add-to-cart` menyimpan `build_name` dan rollback item jika gagal di tengah.
- [x] `PUT /builder/builds/{build_id}` — ganti rakitan atomik (validasi dulu, rollback bila gagal).
- [x] Checkout merevalidasi kompatibilitas **per `build_id`** sebelum order dibuat (422 + detail error).
- [x] `GET /cart` menampilkan `build_name` per grup rakitan.
- [x] Unit test untuk normalisasi socket / form factor / parser angka.
- [x] Feature test builder: normalisasi socket, PSU kurang daya, bypass flag, panjang GPU, slot kosong.
- [x] Feature test checkout: rakitan yang berubah tidak kompatibel ditolak 422 tanpa membuat order.

## P1 — Kontrol Akses & Manajemen Akun

Target: pemisahan hak akses `customer`, `admin`, `superadmin`, `keuangan` berjalan di API.

- [x] Role baru: `superadmin` dan `keuangan` + helper `isAdmin()`, `isSuperadmin()`, `isFinance()`, `canReadOperational()`.
- [x] Middleware baru: `superadmin`, `finance`, `admin.read`; alias didaftarkan di `bootstrap/app.php`.
- [x] `admin` (tulis operasional) = `admin` + `superadmin`.
- [x] `keuangan` bisa membaca data operasional (stats, daftar order, pelanggan) tetapi tidak bisa mengubah.
- [x] Manajemen Akun (superadmin): `POST /admin/users`, `PUT /admin/users/{id}`, `DELETE /admin/users/{id}`.
- [x] Password sementara + `must_change_password` saat akun dibuat/di-reset superadmin (password ditampilkan sekali).
- [x] `PATCH /users/{id}/role` hanya superadmin, menerima 4 role.
- [x] Proteksi: tidak bisa hapus/demote diri sendiri, minimal 1 superadmin selalu ada.
- [x] Seeder: akun `superadmin`, `admin`, `keuangan`, customer.
- [x] Update `SETUP.md` daftar akun seed.
- [x] Feature test role: keuangan baca laporan tetapi 403 saat tulis; admin 403 keuangan/manajemen akun; superadmin bisa CRUD akun; superadmin tidak bisa demote diri.
- [ ] Penegakan `must_change_password` (blokir endpoint sampai ganti password) + `PATCH /me/password` → **dijadwalkan di P3**.

## P1 — Modul Keuangan

Target: tiga laporan keuangan + export CSV sesuai FR-023.

- [x] Field `products.hpp` (harga pokok) + validasi admin/superadmin.
- [x] Snapshot `order_items.hpp_snapshot` dan `price_list_snapshot` saat checkout.
- [x] `GET /admin/finance/profit-loss?from=&to=` — pendapatan, diskon, HPP, laba, margin %.
- [x] `GET /admin/finance/tax?from=&to=` — DPP dan PPN keluaran (rate default 11, inklusif).
- [x] `GET /admin/finance/margin?group_by=product|category|brand`.
- [x] Export CSV untuk ketiga laporan (`&format=csv`).
- [x] Config `config/finance.php` (`PPN_RATE`, default 11).
- [x] Tandai item tanpa HPP pada laporan (margin tidak terhitung).
- [x] Feature test kalkulasi laporan: laba rugi, PPN inklusif, margin per kategori, export CSV.

## P2 — Promo, Alamat, Ulasan, Midtrans

- [ ] Promo banner: `banner_image`, `click_target_type/value`, `product_ids`, `is_active`.
- [ ] Penimpaan/pelewatan diskon manual (Timpa / Lewati / Timpa semua).
- [ ] Kode promo (`promo_codes`) + validasi saat checkout.
- [ ] Alamat pembeli (max 10, satu default, guest via `session_id`).
- [ ] Ulasan (1 per pengguna per produk) + moderasi admin.
- [ ] Koleksi `categories` & `brands` + CRUD + validasi slug.
- [ ] Midtrans Snap: `snap-token`, callback SHA512, layar instruksi bayar.
- [ ] `payment_logs` + rekonsiliasi + halaman riwayat transaksi.
- [ ] Job kedaluwarsa order + restore stok.

## P3 — Auth Lanjutan & Kebersihan

- [ ] JWT HS256 (atau revisi PRD bila tetap memakai token Bearer acak).
- [ ] OTP 6 digit (hash, 5–10 menit, max 5 percobaan, cooldown resend).
- [ ] `/admin/login` terpisah + `PATCH /me` + `PATCH /me/password` + `password_set`.
- [ ] Login dengan email **atau** username.
- [ ] Hapus token debug dari response produksi (`APP_DEBUG=false`).
- [ ] Guard hapus produk 409 bila dirujuk paket/order/keranjang.
- [ ] Status `refunded` untuk order berbayar yang dibatalkan.

## Frontend (setelah API stabil)

- [ ] Setup React + routing + state management + API client.
- [ ] Home, Katalog, Detail Produk, PC Builder (20 baris, panel kompatibilitas).
- [ ] Cart 3 grup (accordion), Checkout, Instruksi Bayar, Berhasil/Gagal.
- [ ] Auth pembeli (login, register, OTP, lupa password) + dashboard pembeli.
- [ ] Dashboard admin (produk, paket, promo, pesanan, pelanggan, ulasan, keuangan, manajemen akun).
- [ ] Design system BuildWise (latar `#ECE9F3`, lime `#C6F135`, 20 ikon SVG, responsif).

---

## Cara verifikasi P1

```sh
cd backend
php artisan route:list --path=api
php artisan db:setup-mongo --fresh   # butuh MongoDB aktif
php artisan test
```

Skenario manual minimal (sudah lolos dengan data seeder):

1. `POST /api/builder/validate` dengan socket `"LGA 1700"` dan `"LGA1700"` → **kompatibel** (normalisasi).
2. `POST /api/builder/validate` dengan PSU 750W untuk total 660W → `errors` berisi RULE-03; kirim `ignore_psu_warning=true` → lolos dengan warning.
3. Tambah rakitan ke cart, ubah socket CPU, lalu checkout → **422** dengan `build_id` bermasalah.
4. Login sebagai `keuangan` → bisa `GET /api/admin/finance/profit-loss`, tetapi `POST /api/products` → **403**.
5. Login sebagai `superadmin` → bisa membuat akun admin baru; akun `admin` biasa → **403**.
