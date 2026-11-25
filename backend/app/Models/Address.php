<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Address — alamat pembeli, mendukung user login maupun tamu (session_id).
 *
 * @property string $_id
 * @property string|null $user_id
 * @property string|null $session_id
 * @property string $label
 * @property string $recipient
 * @property string $phone
 * @property string $address
 * @property string $city
 * @property bool $is_default
 */
class Address extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'addresses';

    protected $fillable = [
        'user_id',
        'session_id',
        'label',
        'recipient',
        'phone',
        'address',
        'city',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
