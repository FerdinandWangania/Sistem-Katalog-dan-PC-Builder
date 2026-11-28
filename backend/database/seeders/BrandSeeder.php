<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        Brand::truncate();

        $knownBrands = [
            'AMD', 'Intel', 'NVIDIA', 'ASUS', 'MSI', 'Corsair', 'Seasonic',
            'Lian Li', 'NZXT', 'Samsung', 'G.Skill', 'Gigabyte', 'WD',
            'Crucial', 'Logitech', 'Arctic', 'Noctua', 'DeepCool',
            'Cooler Master', 'be quiet!', 'TeamGroup', 'Kingston',
        ];

        $productBrands = Product::query()
            ->get(['brand'])
            ->pluck('brand')
            ->filter()
            ->map(static fn ($brand): string => trim((string) $brand))
            ->all();

        $brands = collect($knownBrands)
            ->merge($productBrands)
            ->filter()
            ->unique(static fn (string $brand): string => strtolower($brand))
            ->sort()
            ->values();

        foreach ($brands as $brand) {
            Brand::create([
                'name' => $brand,
                'logo' => null,
                'is_active' => true,
            ]);
        }

        $this->command->info('✓ '.$brands->count().' brand berhasil di-seed.');
    }
}
