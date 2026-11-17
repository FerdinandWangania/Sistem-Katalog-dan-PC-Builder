# Frontend Rules — BuildWise PC Store

## Design tokens (wajib)
- Sumber kebenaran visual: `frontend/src/styles/tokens.css` (Tailwind v4 `@theme`).
- Jangan hardcode hex di komponen. Pakai CSS variables (`var(--color-bg)`) atau utility Tailwind yang dipetakan dari token (misal `bg-surface`, `text-ink`, `border-border`, `bg-accent`).
- Warna baru hanya boleh masuk lewat `tokens.css` + komentar `/* replaces: ... */` (atau `/* new: ... */` bila memang belum ada padanannya).
- Referensi PRD: bg `#ECE9F3`, accent lime `#C6F135`, responsif mobile + desktop.
- Font default seluruh halaman: Plus Jakarta Sans via var(--font-sans). Jangan hardcode font-family di komponen. Weight yang dipakai: 400 body, 600 semibold, 700/800 heading.

## Ikon (wajib)
- Satu library saja: lucide-react. Jangan tambah Tabler/Heroicons/dll, jangan paste SVG mentah.
- Import dari 'lucide-react'. Ukuran via Tailwind (w-4 h-4, w-5 h-5), warna via text-* (ikon pakai currentColor).
- Mapping kategori: CPU→Cpu, MOBO→CircuitBoard, RAM→MemoryStick, GPU→Gpu, SSD/HDD→HardDrive, PSU→Plug, CASE→Box, COOL→Fan. Kalau tidak ada padanan persis, pilih makna terdekat.
- Tidak boleh ada ikon kotak kosong (missing glyph). Kalau ragu, tampilkan tanpa ikon + console.warn.

## Batasan kerja
- Hanya ubah `frontend/`. Jangan sentuh `backend/` dan `.github/`.
- Pakai endpoint real dari `backend/routes/api.php` saja. Jangan ngarang endpoint.
- Base URL via `.env` (`VITE_API_URL`), proxy `/api` -> `http://localhost:8000` (lihat `frontend/vite.config.js`).
- Auth: guest kirim `session_id` (UUID localStorage), login kirim `user_id` + Bearer. Tangani 401/403/422/429 terstruktur.
- Setiap selesai: `cd frontend && npm run build` harus lolos.
