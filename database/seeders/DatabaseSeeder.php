<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed semua collections MongoDB sesuai ERD.
     * Urutan penting: Products harus ada sebelum Packages & Cart/Order items.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            PromotionSeeder::class,
            PrebuiltPackageSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('? Database MongoDB (pc_store) berhasil di-seed!');
        $this->command->info('   Collections: users, products, promotions, prebuilt_packages, package_items');
    }
}
