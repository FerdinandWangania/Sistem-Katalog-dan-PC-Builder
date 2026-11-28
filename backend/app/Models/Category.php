<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Category — kategori katalog.
 *
 * @property string $_id
 * @property string $name
 * @property string $slug
 * @property string $type -- component | package | accessory
 * @property string|null $icon
 * @property bool $is_active
 */
class Category extends Model
{
    public const SLUGS = [
        'cpu', 'motherboard', 'ram', 'ssd', 'ssd_external', 'gpu', 'case', 'psu',
        'cooler', 'harddisk', 'case_fan', 'thermal_paste', 'operating_system',
        'sound_card', 'network_card', 'capture_card', 'mouse', 'mousepad',
        'keyboard', 'monitor', 'audio', 'bracket_screen', 'cable',
        'camera_streaming', 'cctv_security', 'converter_adapter', 'drawing_tablet',
        'enclosure', 'flashdisk', 'furniture', 'gaming_device', 'home_living',
        'laptop_accessory', 'memory_card', 'networking_tools', 'paper', 'printer',
        'printer_supply', 'projector', 'scanner', 'smartphone_accessory', 'software',
        'speaker', 'ups', 'usb_hub_card_reader',
    ];

    protected $connection = 'mongodb';

    protected $collection = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
