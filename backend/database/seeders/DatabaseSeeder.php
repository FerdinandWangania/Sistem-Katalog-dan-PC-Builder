<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed semua collections MongoDB sesuai ERD.
     * Urutan penting: Category harus ada sebelum Products, lalu Brand membaca
     * daftar brand dari Products.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            BrandSeeder::class,
            PromotionSeeder::class,
            PromoCodeSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('✓ Database MongoDB (pc_store) berhasil di-seed!');
        $this->command->info('   Collections: users, categories, products, brands, promotions, promo_codes');
    }
}
