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
                'title'               => 'Promo Harbolnas NVIDIA',
                'brands'              => ['NVIDIA'],
                'discount_percentage' => 10.0,
                'start_date'          => now()->subDays(2),
                'end_date'            => now()->addDays(5),
            ],
            [
                'title'               => 'Diskon Akhir Tahun Intel & Corsair',
                'brands'              => ['Intel', 'Corsair'],
                'discount_percentage' => 15.0,
                'start_date'          => now()->subDay(),
                'end_date'            => now()->addDays(10),
            ],
            [
                'title'               => 'Flash Sale Samsung SSD',
                'brands'              => ['Samsung'],
                'discount_percentage' => 20.0,
                'start_date'          => now()->addDays(3),
                'end_date'            => now()->addDays(4),
            ],
        ];

        foreach ($promos as $promo) {
            Promotion::create($promo);
        }

        $this->command->info('✓ ' . count($promos) . ' promosi berhasil di-seed.');
    }
}
