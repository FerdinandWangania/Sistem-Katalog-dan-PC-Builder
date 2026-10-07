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

        // Admin
        User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@pcstore.id',
            'password' => Hash::make('admin123!'),
            'phone'    => '081200000001',
            'role'     => 'admin',
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
                'name'     => $cust['name'],
                'email'    => $cust['email'],
                'password' => Hash::make('password123'),
                'phone'    => $cust['phone'],
                'role'     => 'customer',
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('✓ 1 admin + ' . count($customers) . ' customer berhasil di-seed.');
    }
}
