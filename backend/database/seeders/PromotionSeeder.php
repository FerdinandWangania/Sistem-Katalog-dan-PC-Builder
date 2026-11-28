<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        Promotion::truncate();

        $promos = [
            [
                'title' => 'Promo Harbolnas NVIDIA',
                'banner_image' => 'https://placehold.co/1200x400/1e293b/ffffff?text=Promo+NVIDIA',
                'click_target_type' => 'brand',
                'click_target_value' => 'NVIDIA',
                'brands' => ['NVIDIA'],
                'product_ids' => [],
                'excluded_product_ids' => [],
                'override_manual_discount' => true,
                'discount_percentage' => 10.0,
                'start_date' => now()->subDays(2),
                'end_date' => now()->addDays(5),
                'is_active' => false,
            ],
            [
                'title' => 'Diskon Akhir Tahun Intel & Corsair',
                'banner_image' => 'https://placehold.co/1200x400/0f172a/ffffff?text=Intel+%26+Corsair',
                'click_target_type' => 'brand',
                'click_target_value' => 'Intel',
                'brands' => ['Intel', 'Corsair'],
                'product_ids' => [],
                'excluded_product_ids' => [],
                'override_manual_discount' => true,
                'discount_percentage' => 15.0,
                'start_date' => now()->subDay(),
                'end_date' => now()->addDays(10),
                'is_active' => false,
            ],
            [
                'title' => 'Flash Sale Samsung SSD',
                'banner_image' => 'https://placehold.co/1200x400/111827/ffffff?text=Flash+Sale+SSD',
                'click_target_type' => 'category',
                'click_target_value' => 'ssd',
                'brands' => ['Samsung'],
                'product_ids' => [],
                'excluded_product_ids' => [],
                'override_manual_discount' => true,
                'discount_percentage' => 20.0,
                'start_date' => now()->addDays(3),
                'end_date' => now()->addDays(4),
                'is_active' => false,
            ],
        ];

        foreach ($promos as $promo) {
            Promotion::create($promo);
        }

        $this->command->info('✓ '.count($promos).' promosi berhasil di-seed.');
    }
}
