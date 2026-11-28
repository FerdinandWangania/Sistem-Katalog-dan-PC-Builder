<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::truncate();

        $path = database_path('blessing_products.json');

        if (! is_file($path)) {
            $this->command->error("Dataset produk tidak ditemukan: {$path}");

            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->command->error("Dataset produk tidak bisa dibaca: {$path}");

            return;
        }

        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $products = $payload['products'] ?? [];
        $now = Carbon::now();
        $total = 0;

        foreach (array_chunk($products, 200) as $chunk) {
            $rows = [];

            foreach ($chunk as $product) {
                $rows[] = [
                    'category' => $product['category'],
                    'brand' => $product['brand'],
                    'name' => $product['name'],
                    'sku' => $product['sku'] !== '' ? $product['sku'] : null,
                    'price' => (float) $product['price'],
                    'discount_price' => 0.0,
                    'hpp' => null,
                    'stock' => (int) ($product['stock'] ?? 10),
                    'image' => null,
                    'badge' => null,
                    'rating' => 0.0,
                    'review_count' => 0,
                    'specs' => $product['specs'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Product::insert($rows);
            $total += count($rows);
        }

        $this->command->info('✓ '.$total.' produk berhasil di-seed dari dataset BLESSING.');
    }
}
