<?php

namespace Database\Seeders;

use App\Models\PackageItem;
use App\Models\PrebuiltPackage;
use App\Models\Product;
use Illuminate\Database\Seeder;

class PrebuiltPackageSeeder extends Seeder
{
    public function run(): void
    {
        PrebuiltPackage::truncate();
        PackageItem::truncate();

        // Ambil produk berdasarkan nama
        $i9    = Product::where('name', 'like', '%i9-14900K%')->first();
        $rtx   = Product::where('name', 'like', '%RTX 4090%')->first();
        $ram32 = Product::where('name', 'like', '%Corsair Vengeance 32GB%')->first();
        $ssd   = Product::where('name', 'like', '%Samsung 990 Pro%')->first();
        $mobo  = Product::where('name', 'like', '%ROG Maximus%')->first();
        $psu   = Product::where('name', 'like', '%Seasonic%')->first();
        $case  = Product::where('name', 'like', '%O11 Dynamic%')->first();

        $r9    = Product::where('name', 'like', '%Ryzen 9 7950X%')->first();
        $rx7   = Product::where('name', 'like', '%RX 7900 XTX%')->first();
        $ram64 = Product::where('name', 'like', '%Trident Z5 64GB%')->first();

        // Paket 1: Gaming Beast
        if ($i9 && $rtx && $ram32 && $ssd && $mobo && $psu && $case) {
            $total = collect([$i9, $rtx, $ram32, $ssd, $mobo, $psu, $case])->sum('price');
            $pkg = PrebuiltPackage::create([
                'name'        => 'Gaming Beast — Intel + NVIDIA',
                'tag'         => 'gaming',
                'total_price' => $total,
            ]);

            foreach ([$i9, $rtx, $ram32, $ssd, $mobo, $psu, $case] as $product) {
                PackageItem::create([
                    'package_id' => $pkg->_id,
                    'product_id' => $product->_id,
                ]);
            }
        }

        // Paket 2: Content Creator Workstation
        if ($r9 && $rx7 && $ram64 && $ssd && $psu && $case) {
            $total = collect([$r9, $rx7, $ram64, $ssd, $psu, $case])->sum('price');
            $pkg2 = PrebuiltPackage::create([
                'name'        => 'Content Creator — AMD Platform',
                'tag'         => 'workstation',
                'total_price' => $total,
            ]);

            foreach ([$r9, $rx7, $ram64, $ssd, $psu, $case] as $product) {
                PackageItem::create([
                    'package_id' => $pkg2->_id,
                    'product_id' => $product->_id,
                ]);
            }
        }

        $this->command->info('✓ Prebuilt packages berhasil di-seed.');
    }
}
