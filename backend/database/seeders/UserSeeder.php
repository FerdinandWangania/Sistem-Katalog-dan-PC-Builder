<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::truncate();

        // Superadmin pertama (PRD §4: dibuat lewat seeder)
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@pcstore.id',
            'password' => Hash::make('super123!'),
            'phone' => '081200000000',
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        // Admin operasional
        User::create([
            'name' => 'Admin Operasional',
            'email' => 'admin@pcstore.id',
            'password' => Hash::make('admin123!'),
            'phone' => '081200000001',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Keuangan (baca operasional + laporan keuangan)
        User::create([
            'name' => 'Staff Keuangan',
            'email' => 'keuangan@pcstore.id',
            'password' => Hash::make('keuangan123!'),
            'phone' => '081200000006',
            'role' => 'keuangan',
            'email_verified_at' => now(),
        ]);

        // Customers
        $customers = [
            ['name' => 'Budi Santoso',   'email' => 'budi@mail.com',   'phone' => '081200000002'],
            ['name' => 'Siti Rahayu',    'email' => 'siti@mail.com',   'phone' => '081200000003'],
            ['name' => 'Andi Pratama',   'email' => 'andi@mail.com',   'phone' => '081200000004'],
            ['name' => 'Dewi Kusuma',    'email' => 'dewi@mail.com',   'phone' => '081200000005'],
        ];

        foreach ($customers as $cust) {
            User::create([
                'name' => $cust['name'],
                'email' => $cust['email'],
                'password' => Hash::make('password123'),
                'phone' => $cust['phone'],
                'role' => 'customer',
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('✔ 1 superadmin + 1 admin + 1 keuangan + '.count($customers).' customer berhasil di-seed.');
    }
}
