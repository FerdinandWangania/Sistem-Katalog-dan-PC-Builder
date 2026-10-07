<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Model User — unified users collection (role: customer | admin)
 *
 * @property \MongoDB\BSON\ObjectId $_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property string $role  — 'customer' | 'admin'
 * @property \Carbon\Carbon $created_at
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'created_at'        => 'datetime',
        ];
    }

    // --- Relasi ---

    public function cart()
    {
        return $this->hasOne(Cart::class, 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    // --- Scopes ---

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
}
