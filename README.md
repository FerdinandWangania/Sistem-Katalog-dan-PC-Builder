# Sistem Katalog dan PC Builder

Struktur monorepo:

- `backend/` — aplikasi Laravel (dipindah dari root).
- `frontend/` — project Vite terpisah, konsumsi API backend.

## Backend

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

## Frontend

```sh
cd frontend
npm install
npm run dev
```
