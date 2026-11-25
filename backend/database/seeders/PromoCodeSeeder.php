<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        PromoCode::truncate();

        $codes = [
            [
                'code' => 'HARBOLNAS10',
                'discount_percentage' => 10.0,
                'brands' => null,
                'min_subtotal' => 0,
                'start_date' => now()->subDays(2),
                'end_date' => now()->addDays(30),
                'is_active' => true,
            ],
            [
                'code' => 'GAMING15',
                'discount_percentage' => 15.0,
                'brands' => ['NVIDIA', 'AMD', 'Intel'],
                'min_subtotal' => 5_000_000,
                'start_date' => now()->subDay(),
                'end_date' => now()->addDays(14),
                'is_active' => true,
            ],
        ];

        foreach ($codes as $code) {
            PromoCode::create($code);
        }

        $this->command->info('✓ '.count($codes).' kode promo berhasil di-seed.');
    }
}
