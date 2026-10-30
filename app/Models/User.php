<?php

namespace App\Models;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Clients\Enums\ClientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'client_type',
        'store_name',
        'city',
        'address',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'client_type' => ClientType::class,
            'is_active' => 'boolean',
        ];
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::CLIENT || $this->hasRole(UserRole::CLIENT->value);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN || $this->hasRole(UserRole::ADMIN->value);
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::LIVREUR || $this->hasRole(UserRole::LIVREUR->value);
    }

    public function isAgency(): bool
    {
        return $this->role === UserRole::AGENCE || $this->hasRole(UserRole::AGENCE->value);
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::OPERATEUR || $this->hasRole(UserRole::OPERATEUR->value);
    }

    public function canAccessDashboard(): bool
    {
        return (bool) $this->is_active;
    }
}
