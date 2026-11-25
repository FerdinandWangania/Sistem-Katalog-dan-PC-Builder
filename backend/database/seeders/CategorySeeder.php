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
            ['name' => 'Graphics Card', 'slug' => 'gpu', 'type' => 'component', 'icon' => 'monitor-play'],
            ['name' => 'Case', 'slug' => 'case', 'type' => 'component', 'icon' => 'box'],
            ['name' => 'Power Supply', 'slug' => 'psu', 'type' => 'component', 'icon' => 'plug'],
            ['name' => 'Harddisk', 'slug' => 'harddisk', 'type' => 'component', 'icon' => 'database'],
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
        ];

        foreach ($categories as $category) {
            Category::create([...$category, 'is_active' => true]);
        }

        $this->command->info('✓ '.count($categories).' kategori berhasil di-seed.');
    }
}
