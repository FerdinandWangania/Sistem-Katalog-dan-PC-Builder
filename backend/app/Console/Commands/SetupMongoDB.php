<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupMongoDB extends Command
{
    protected $signature   = 'db:setup-mongo {--fresh : Drop semua collections lalu re-seed}';
    protected $description = 'Setup MongoDB: buat indexes + seed semua collections';

    public function handle(): int
    {
        $this->info('');
        $this->info('=== MongoDB Setup — PC Store ===');

        // Cek koneksi
        try {
            $conn = DB::connection('mongodb');
            $conn->command(['ping' => 1]);
            $this->info('Koneksi ke MongoDB berhasil');
        } catch (\Exception $e) {
            $this->error('Tidak bisa konek ke MongoDB: ' . $e->getMessage());
            $this->info('Pastikan MongoDB berjalan di: ' . config('database.connections.mongodb.dsn'));
            return self::FAILURE;
        }

        // Migrate
        $this->info('Menjalankan migrations (buat indexes)...');
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--database' => 'mongodb']);
        } else {
            $this->call('migrate', ['--database' => 'mongodb']);
        }

        // Seed
        $this->info('Menjalankan seeders...');
        $this->call('db:seed');

        $this->info('Setup selesai! Database: ' . config('database.connections.mongodb.database'));
        return self::SUCCESS;
    }
}
