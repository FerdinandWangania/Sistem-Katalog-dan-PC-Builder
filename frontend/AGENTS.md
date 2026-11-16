# Frontend Rules — BuildWise PC Store

## Design tokens (wajib)
- Sumber kebenaran visual: `frontend/src/styles/tokens.css`.
- Jangan hardcode hex di komponen. Pakai CSS variables (misal `var(--color-bg)`).
- Kalau butuh warna baru: tambahkan dulu ke `tokens.css` dengan komentar `/* replaces: ... */`, baru pakai.
- Referensi PRD: bg `#ECE9F3`, accent lime `#C6F135`, responsif mobile + desktop.

## Batasan kerja
- Hanya ubah `frontend/`. Jangan sentuh `backend/` dan `.github/`.
- Pakai endpoint real dari `backend/routes/api.php` saja. Jangan ngarang endpoint.
- Base URL via `.env` (`VITE_API_URL`), proxy `/api` -> `http://localhost:8000` (lihat `frontend/vite.config.js`).
- Auth: guest kirim `session_id` (UUID localStorage), login kirim `user_id` + Bearer. Tangani 401/403/422/429 terstruktur.
- Setiap selesai: `cd frontend && npm run build` harus lolos.
