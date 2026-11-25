<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed semua collections MongoDB sesuai ERD.
     * Urutan penting: Products harus ada sebelum Packages, Category/Brand,
     * dan item keranjang/pesanan.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            PromotionSeeder::class,
            PromoCodeSeeder::class,
            PrebuiltPackageSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('✓ Database MongoDB (pc_store) berhasil di-seed!');
        $this->command->info('   Collections: users, products, categories, brands, promotions, promo_codes, prebuilt_packages, package_items');
    }
}
