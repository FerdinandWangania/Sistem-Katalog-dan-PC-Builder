<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::truncate();

        $products = [
            // CPU
            [
                'category' => 'CPU',
                'brand'    => 'Intel',
                'name'     => 'Intel Core i9-14900K',
                'price'    => 7500000,
                'discount_price' => 7000000,
                'stock'    => 20,
                'image'    => 'https://placehold.co/600x400?text=i9-14900K',
                'specs'    => [
                    'cores'          => 24,
                    'threads'        => 32,
                    'base_clock'     => '3.2 GHz',
                    'boost_clock'    => '6.0 GHz',
                    'tdp'            => '125W',
                    'socket'         => 'LGA1700',
                ],
            ],
            [
                'category' => 'CPU',
                'brand'    => 'AMD',
                'name'     => 'AMD Ryzen 9 7950X',
                'price'    => 8200000,
                'discount_price' => 0,
                'stock'    => 15,
                'image'    => 'https://placehold.co/600x400?text=Ryzen-9-7950X',
                'specs'    => [
                    'cores'          => 16,
                    'threads'        => 32,
                    'base_clock'     => '4.5 GHz',
                    'boost_clock'    => '5.7 GHz',
                    'tdp'            => '170W',
                    'socket'         => 'AM5',
                ],
            ],
            // GPU
            [
                'category' => 'GPU',
                'brand'    => 'NVIDIA',
                'name'     => 'NVIDIA RTX 4090 24GB',
                'price'    => 25000000,
                'discount_price' => 23500000,
                'stock'    => 8,
                'image'    => 'https://placehold.co/600x400?text=RTX-4090',
                'specs'    => [
                    'vram'        => '24GB GDDR6X',
                    'cuda_cores'  => 16384,
                    'tdp'         => '450W',
                    'pcie'        => 'PCIe 4.0 x16',
                ],
            ],
            [
                'category' => 'GPU',
                'brand'    => 'AMD',
                'name'     => 'AMD RX 7900 XTX',
                'price'    => 14500000,
                'discount_price' => 0,
                'stock'    => 12,
                'image'    => 'https://placehold.co/600x400?text=RX-7900-XTX',
                'specs'    => [
                    'vram'       => '24GB GDDR6',
                    'stream_processors' => 6144,
                    'tdp'        => '355W',
                    'pcie'       => 'PCIe 4.0 x16',
                ],
            ],
            // RAM
            [
                'category' => 'RAM',
                'brand'    => 'Corsair',
                'name'     => 'Corsair Vengeance 32GB DDR5-6000',
                'price'    => 1800000,
                'discount_price' => 1650000,
                'stock'    => 50,
                'image'    => 'https://placehold.co/600x400?text=Vengeance-32GB',
                'specs'    => [
                    'capacity'  => '32GB (2x16GB)',
                    'type'      => 'DDR5',
                    'speed'     => '6000MHz',
                    'latency'   => 'CL36',
                ],
            ],
            [
                'category' => 'RAM',
                'brand'    => 'G.Skill',
                'name'     => 'G.Skill Trident Z5 64GB DDR5-6400',
                'price'    => 3500000,
                'discount_price' => 0,
                'stock'    => 25,
                'image'    => 'https://placehold.co/600x400?text=Trident-Z5-64GB',
                'specs'    => [
                    'capacity'  => '64GB (2x32GB)',
                    'type'      => 'DDR5',
                    'speed'     => '6400MHz',
                    'latency'   => 'CL32',
                ],
            ],
            // SSD
            [
                'category' => 'SSD',
                'brand'    => 'Samsung',
                'name'     => 'Samsung 990 Pro 2TB NVMe',
                'price'    => 2200000,
                'discount_price' => 2000000,
                'stock'    => 40,
                'image'    => 'https://placehold.co/600x400?text=990-Pro-2TB',
                'specs'    => [
                    'capacity'        => '2TB',
                    'interface'       => 'PCIe 4.0 NVMe M.2',
                    'read_speed'      => '7450 MB/s',
                    'write_speed'     => '6900 MB/s',
                    'form_factor'     => 'M.2 2280',
                ],
            ],
            // Motherboard
            [
                'category' => 'Motherboard',
                'brand'    => 'ASUS',
                'name'     => 'ASUS ROG Maximus Z790 Hero',
                'price'    => 6800000,
                'discount_price' => 0,
                'stock'    => 10,
                'image'    => 'https://placehold.co/600x400?text=Maximus-Z790',
                'specs'    => [
                    'socket'       => 'LGA1700',
                    'chipset'      => 'Z790',
                    'form_factor'  => 'ATX',
                    'memory_slots' => 4,
                    'memory_type'  => 'DDR5',
                    'max_memory'   => '128GB DDR5',
                ],
            ],
            [
                'category' => 'Motherboard',
                'brand'    => 'ASUS',
                'name'     => 'ASUS ROG Strix B650E-F Gaming WiFi',
                'price'    => 4500000,
                'discount_price' => 4200000,
                'stock'    => 15,
                'image'    => 'https://placehold.co/600x400?text=Strix-B650E',
                'specs'    => [
                    'socket'       => 'AM5',
                    'chipset'      => 'B650E',
                    'form_factor'  => 'ATX',
                    'memory_slots' => 4,
                    'memory_type'  => 'DDR5',
                    'max_memory'   => '128GB DDR5',
                ],
            ],
            // PSU
            [
                'category' => 'PSU',
                'brand'    => 'Seasonic',
                'name'     => 'Seasonic FOCUS GX-1000W 80+ Gold',
                'price'    => 2500000,
                'discount_price' => 2300000,
                'stock'    => 30,
                'image'    => 'https://placehold.co/600x400?text=Seasonic-1000W',
                'specs'    => [
                    'wattage'      => '1000W',
                    'efficiency'   => '80+ Gold',
                    'modular'      => 'Full Modular',
                    'fan_size'     => '135mm',
                ],
            ],
            [
                'category' => 'PSU',
                'brand'    => 'Corsair',
                'name'     => 'Corsair RM750e 750W 80+ Gold',
                'price'    => 1750000,
                'discount_price' => 0,
                'stock'    => 25,
                'image'    => 'https://placehold.co/600x400?text=RM750e',
                'specs'    => [
                    'wattage'      => '750W',
                    'efficiency'   => '80+ Gold',
                    'modular'      => 'Full Modular',
                    'fan_size'     => '120mm',
                ],
            ],
            // Casing
            [
                'category' => 'Case',
                'brand'    => 'Lian Li',
                'name'     => 'Lian Li O11 Dynamic EVO',
                'price'    => 2100000,
                'discount_price' => 0,
                'stock'    => 18,
                'image'    => 'https://placehold.co/600x400?text=O11-Dynamic-EVO',
                'specs'    => [
                    'form_factor'      => 'ATX',
                    'max_gpu'          => '422mm',
                    'drive_bays'       => '2x 2.5" + 4x 3.5"',
                    'radiator_support' => '360mm top/front',
                ],
            ],
            [
                'category' => 'Case',
                'brand'    => 'NZXT',
                'name'     => 'NZXT H5 Flow RGB',
                'price'    => 1450000,
                'discount_price' => 1350000,
                'stock'    => 20,
                'image'    => 'https://placehold.co/600x400?text=H5-Flow',
                'specs'    => [
                    'form_factor'      => 'ATX',
                    'max_gpu'          => '365mm',
                    'drive_bays'       => '1x 2.5" + 1x 3.5"',
                    'radiator_support' => '280mm front',
                ],
            ],
        ];

        foreach ($products as $data) {
            Product::create($data);
        }

        $this->command->info('✓ ' . count($products) . ' produk berhasil di-seed.');
    }
}
