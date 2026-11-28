<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::truncate();

        $categories = [
            ['name' => 'Processor', 'slug' => 'cpu', 'type' => 'component', 'icon' => 'cpu'],
            ['name' => 'Motherboard', 'slug' => 'motherboard', 'type' => 'component', 'icon' => 'circuit-board'],
            ['name' => 'RAM', 'slug' => 'ram', 'type' => 'component', 'icon' => 'memory-stick'],
            ['name' => 'SSD', 'slug' => 'ssd', 'type' => 'component', 'icon' => 'hard-drive'],
            ['name' => 'SSD External', 'slug' => 'ssd_external', 'type' => 'component', 'icon' => 'hard-drive'],
            ['name' => 'Graphics Card', 'slug' => 'gpu', 'type' => 'component', 'icon' => 'monitor-play'],
            ['name' => 'Case', 'slug' => 'case', 'type' => 'component', 'icon' => 'box'],
            ['name' => 'Power Supply', 'slug' => 'psu', 'type' => 'component', 'icon' => 'plug'],
            ['name' => 'Harddisk', 'slug' => 'harddisk', 'type' => 'component', 'icon' => 'database'],
            ['name' => 'CPU Cooler', 'slug' => 'cooler', 'type' => 'component', 'icon' => 'snowflake'],
            ['name' => 'Case Fan', 'slug' => 'case_fan', 'type' => 'accessory', 'icon' => 'fan'],
            ['name' => 'Thermal Paste', 'slug' => 'thermal_paste', 'type' => 'accessory', 'icon' => 'droplet'],
            ['name' => 'Operating System', 'slug' => 'operating_system', 'type' => 'accessory', 'icon' => 'app-window'],
            ['name' => 'Sound Card', 'slug' => 'sound_card', 'type' => 'accessory', 'icon' => 'audio-lines'],
            ['name' => 'Network Card', 'slug' => 'network_card', 'type' => 'accessory', 'icon' => 'wifi'],
            ['name' => 'Capture Card', 'slug' => 'capture_card', 'type' => 'accessory', 'icon' => 'video'],
            ['name' => 'Mouse', 'slug' => 'mouse', 'type' => 'accessory', 'icon' => 'mouse'],
            ['name' => 'Mousepad', 'slug' => 'mousepad', 'type' => 'accessory', 'icon' => 'square'],
            ['name' => 'Keyboard', 'slug' => 'keyboard', 'type' => 'accessory', 'icon' => 'keyboard'],
            ['name' => 'Monitor', 'slug' => 'monitor', 'type' => 'accessory', 'icon' => 'monitor'],
            ['name' => 'Audio, Headset & TWS', 'slug' => 'audio', 'type' => 'accessory', 'icon' => 'headphones'],
            ['name' => 'Bracket & Screen', 'slug' => 'bracket_screen', 'type' => 'accessory', 'icon' => 'monitor'],
            ['name' => 'Kabel', 'slug' => 'cable', 'type' => 'accessory', 'icon' => 'cable'],
            ['name' => 'Camera & Streaming', 'slug' => 'camera_streaming', 'type' => 'accessory', 'icon' => 'camera'],
            ['name' => 'CCTV & Security', 'slug' => 'cctv_security', 'type' => 'accessory', 'icon' => 'camera'],
            ['name' => 'Converter & Adapter', 'slug' => 'converter_adapter', 'type' => 'accessory', 'icon' => 'cable'],
            ['name' => 'Drawing Tablet', 'slug' => 'drawing_tablet', 'type' => 'accessory', 'icon' => 'pen-tool'],
            ['name' => 'Enclosure', 'slug' => 'enclosure', 'type' => 'accessory', 'icon' => 'box'],
            ['name' => 'Flashdisk', 'slug' => 'flashdisk', 'type' => 'accessory', 'icon' => 'usb'],
            ['name' => 'Meja & Kursi Gaming', 'slug' => 'furniture', 'type' => 'accessory', 'icon' => 'armchair'],
            ['name' => 'Gaming Device', 'slug' => 'gaming_device', 'type' => 'accessory', 'icon' => 'gamepad-2'],
            ['name' => 'Modern Home Living', 'slug' => 'home_living', 'type' => 'accessory', 'icon' => 'home'],
            ['name' => 'Aksesoris Laptop', 'slug' => 'laptop_accessory', 'type' => 'accessory', 'icon' => 'laptop'],
            ['name' => 'Memory Flash', 'slug' => 'memory_card', 'type' => 'accessory', 'icon' => 'sd-card'],
            ['name' => 'Networking Tools', 'slug' => 'networking_tools', 'type' => 'accessory', 'icon' => 'network'],
            ['name' => 'Kertas & Media Cetak', 'slug' => 'paper', 'type' => 'accessory', 'icon' => 'file-text'],
            ['name' => 'Printer', 'slug' => 'printer', 'type' => 'accessory', 'icon' => 'printer'],
            ['name' => 'Tinta, Toner & Cartridge', 'slug' => 'printer_supply', 'type' => 'accessory', 'icon' => 'droplet'],
            ['name' => 'Projector', 'slug' => 'projector', 'type' => 'accessory', 'icon' => 'projector'],
            ['name' => 'Scanner', 'slug' => 'scanner', 'type' => 'accessory', 'icon' => 'scan'],
            ['name' => 'Aksesoris Smartphone', 'slug' => 'smartphone_accessory', 'type' => 'accessory', 'icon' => 'smartphone'],
            ['name' => 'Software', 'slug' => 'software', 'type' => 'accessory', 'icon' => 'app-window'],
            ['name' => 'Speaker', 'slug' => 'speaker', 'type' => 'accessory', 'icon' => 'speaker'],
            ['name' => 'UPS & Stabilizer', 'slug' => 'ups', 'type' => 'accessory', 'icon' => 'battery-charging'],
            ['name' => 'USB Hub & Card Reader', 'slug' => 'usb_hub_card_reader', 'type' => 'accessory', 'icon' => 'usb'],
        ];

        foreach ($categories as $category) {
            Category::create([...$category, 'is_active' => true]);
        }

        $this->command->info('✓ '.count($categories).' kategori berhasil di-seed.');
    }
}
