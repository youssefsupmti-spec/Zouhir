<?php

namespace App\Models;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Clients\Enums\ClientType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modèle User - Aligné sur l'architecture WinningExpress / Zakaria2.
 */
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'nom_complet',
        'name',
        'civilite',
        'picture',
        'email',
        'password',
        'telephone',
        'phone',
        'sav',
        'ville_name',
        'city',
        'ville_id',
        'cin',
        'bank',
        'rib',
        'stockout',
        'emailstockout',
        'api_abilities',
        'active',
        'is_active',
        'affiliate',
        'maps',
        'affictaion',
        'emailsend',
        'inscription_statut',
        'inscription_traitee_par',
        'inscription_traitee_le',
        'inscription_motif',
        'inscription_refus_nombre',
        'inscription_dernier_refus_le',
        'role',
        'client_type',
        'store_name',
        'address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_abilities',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'inscription_traitee_le' => 'datetime',
            'inscription_dernier_refus_le' => 'datetime',
            'api_abilities' => 'array',
            'role' => UserRole::class,
            'client_type' => ClientType::class,
            'active' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Mot de passe - mutateur intelligent compatible hachages existants et clairs.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): array {
                if ($value === null || $value === '') {
                    return ['password' => $value];
                }
                // Si déjà un hash bcrypt ($2y$, $2a$) ou argon2, ne pas re-hacher
                if (preg_match('/^\$(2[aby]|argon2)/', $value) === 1) {
                    return ['password' => $value];
                }
                return ['password' => Hash::make($value)];
            },
        );
    }

    /**
     * Alias bidirectionnel: name / fullname <-> nom_complet
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['nom_complet'] ?? $value ?? '',
            set: fn ($value) => ['nom_complet' => $value],
        );
    }

    protected function fullname(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['nom_complet'] ?? '',
            set: fn ($value) => ['nom_complet' => $value],
        );
    }

    /**
     * Alias bidirectionnel: phone <-> telephone
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['telephone'] ?? $value ?? '',
            set: fn ($value) => ['telephone' => $value],
        );
    }

    /**
     * Alias bidirectionnel: city <-> ville_name
     */
    protected function city(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['ville_name'] ?? $value ?? '',
            set: fn ($value) => ['ville_name' => $value],
        );
    }

    /**
     * Alias bidirectionnel: is_active <-> active
     */
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => (bool) ($attributes['active'] ?? $value ?? true),
            set: fn ($value) => ['active' => (bool) $value],
        );
    }

    /* ════════════════════ Relations WinningExpress ════════════════════ */

    /**
     * Enseignes marchandes associées à ce compte (Businesses).
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'user_id', 'id')->orderBy('name');
    }

    /**
     * Comptes bancaires associés à ce compte (ComptesBancaires).
     */
    public function comptesBancaires(): HasMany
    {
        return $this->hasMany(CompteBancaire::class, 'owner_id', 'id');
    }

    /* ════════════════════ Rôles & Autorisations Spatie ════════════════════ */

    public function isClient(): bool
    {
        return $this->hasRole('client') || $this->role === UserRole::CLIENT;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']) || $this->role === UserRole::ADMIN;
    }

    public function isDriver(): bool
    {
        return $this->hasRole('livreur') || $this->role === UserRole::LIVREUR;
    }

    public function isAgency(): bool
    {
        return $this->hasRole('agence') || $this->role === UserRole::AGENCE;
    }

    public function isOperator(): bool
    {
        return $this->hasRole('moderator') || $this->hasRole('operateur') || $this->role === UserRole::OPERATEUR;
    }

    public function canAccessDashboard(): bool
    {
        return (bool) ($this->active ?? true);
    }

    /* ════════════════════ Scopes Eloquent ════════════════════ */

    public function scopeClients(Builder $query): Builder
    {
        return $query->role('client');
    }

    public function scopeDrivers(Builder $query): Builder
    {
        return $query->role('livreur');
    }
}
