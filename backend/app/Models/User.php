<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Notifications\Notifiable;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Model User � unified users collection (role: customer | admin)
 *
 * @property ObjectId $_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property string $role � 'customer' | 'admin'
 * @property Carbon $created_at
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable, Notifiable;

    public const ROLES = ['customer', 'admin', 'superadmin', 'keuangan'];

    protected $connection = 'mongodb';

    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'must_change_password',
        'google_id',
        'avatar',
        'api_token',
        'token_expires_at',
        'email_verified_at',
        'verify_token',
        'reset_token',
        'reset_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'token_expires_at',
        'verify_token',
        'reset_token',
        'reset_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'reset_expires_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'created_at' => 'datetime',
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
        return $query->whereIn('role', ['admin', 'superadmin']);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin'], true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isFinance(): bool
    {
        return in_array($this->role, ['keuangan', 'superadmin'], true);
    }

    /**
     * Boleh membaca data operasional (termasuk role keuangan read-only).
     */
    public function canReadOperational(): bool
    {
        return in_array($this->role, ['admin', 'superadmin', 'keuangan'], true);
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
}
