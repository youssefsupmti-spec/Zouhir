<?php

namespace App\Models;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Clients\Enums\ClientType;
use App\Domain\Commande\Support\PerimetreColis;
use App\Domain\RolesPermissions\Support\RoleIds;
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
        'adresse',
        'ville',
        'status',
        'solde',
    ];

    protected $appends = [
        'ville',
        'status',
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
        ];
    }

    /**
     * Mot de passe - mutateur intelligent compatible hachages existants et clairs.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }
                // Si déjà un hash bcrypt ($2y$, $2a$) ou argon2, ne pas re-hacher
                if (preg_match('/^\$(2[aby]|argon2)/', $value) === 1) {
                    return $value;
                }
                return Hash::make($value);
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

    /**
     * Alias bidirectionnel: ville <-> ville_name
     */
    protected function ville(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['ville_name'] ?? $attributes['city'] ?? $value ?? '',
            set: fn ($value) => ['ville_name' => $value],
        );
    }

    /**
     * Alias bidirectionnel: status <-> active
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => isset($attributes['active']) && ! (bool) $attributes['active'] ? 'inactive' : 'active',
            set: fn ($value) => ['active' => in_array($value, ['active', '1', true, 1], true)],
        );
    }

    /* ════════════════════ Relations WinningExpress ════════════════════ */

    /** Tarifs négociés de ce compte marchand (actifs ; corbeille via withTrashed()). */
    public function clientTarifs(): HasMany
    {
        return $this->hasMany(ClientTarif::class, 'client_id');
    }

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

    /**
     * Alias snake_case pour sérialisation Inertia : comptes_bancaires
     */
    public function comptes_bancaires(): HasMany
    {
        return $this->hasMany(CompteBancaire::class, 'owner_id', 'id');
    }

    /**
     * Commandes expédiées en tant que client marchand.
     */
    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'client_id');
    }

    /**
     * Colis pris en charge en tant que livreur coursier.
     */
    public function driverCommands(): HasMany
    {
        return $this->hasMany(Command::class, 'livreur_id');
    }

    /* ════════════════════ Rôles & Autorisations Spatie ════════════════════ */

    public function isClient(): bool
    {
        return PerimetreColis::compteVendeur($this)
            || $this->hasRole('client')
            || $this->role === UserRole::CLIENT;
    }

    public function isAdmin(): bool
    {
        return PerimetreColis::administration($this)
            || $this->hasAnyRole(['admin', 'super_admin'])
            || $this->role === UserRole::ADMIN;
    }

    public function isDriver(): bool
    {
        return PerimetreColis::colisAffectes($this)
            || $this->hasRole('livreur')
            || $this->role === UserRole::LIVREUR;
    }

    public function isAgency(): bool
    {
        return PerimetreColis::agence($this)
            || $this->hasRole('agence')
            || $this->role === UserRole::AGENCE;
    }

    public function isOperator(): bool
    {
        return $this->hasRole('moderator')
            || $this->hasRole('operateur')
            || $this->role === UserRole::OPERATEUR;
    }

    public function isStaff(): bool
    {
        return ! $this->isClient() && ! $this->isDriver();
    }

    public function canAccessDashboard(): bool
    {
        return (bool) ($this->active ?? true);
    }

    /* ════════════════════ Scopes Eloquent Caches (RoleIds) ════════════════════
     * Remplacent les ->role('client') qui déclenchent un findByName() non caché
     * à chaque appel SQL.
     * Supportent également les rôles personnalisés dotés des permissions périmètres.
     */

    /**
     * Comptes portant un rôle Spatie donné (id résolu depuis le cache RoleIds).
     */
    public function scopeWithRoleNamed(Builder $query, string $role): Builder
    {
        $id = app(RoleIds::class)->id($role);

        if ($id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('roles', fn (Builder $q) => $q->whereKey($id));
    }

    /**
     * Comptes clients / marchands (rôle standard Spatie, permission ActAsClient:Command ou colonne role).
     */
    public function scopeClients(Builder $query): Builder
    {
        $clientRoleId = app(RoleIds::class)->id('client');

        return $query->where(function (Builder $q) use ($clientRoleId) {
            if ($clientRoleId !== null) {
                $q->whereHas('roles', fn (Builder $sub) => $sub->whereKey($clientRoleId));
            } else {
                $q->whereRaw('1 = 0');
            }

            $q->orWhereHas('roles.permissions', fn (Builder $sub) => $sub->where('name', PerimetreColis::PROPRE_COMPTE))
              ->orWhereHas('permissions', fn (Builder $sub) => $sub->where('name', PerimetreColis::PROPRE_COMPTE))
              ->orWhere('role', UserRole::CLIENT->value);
        });
    }

    /**
     * Comptes livreurs / chauffeurs (rôle standard Spatie, permission ActAsDriver:Command ou colonne role).
     */
    public function scopeDrivers(Builder $query): Builder
    {
        $driverRoleId = app(RoleIds::class)->id('livreur');

        return $query->where(function (Builder $q) use ($driverRoleId) {
            if ($driverRoleId !== null) {
                $q->whereHas('roles', fn (Builder $sub) => $sub->whereKey($driverRoleId));
            } else {
                $q->whereRaw('1 = 0');
            }

            $q->orWhereHas('roles.permissions', fn (Builder $sub) => $sub->where('name', PerimetreColis::COLIS_AFFECTES))
              ->orWhereHas('permissions', fn (Builder $sub) => $sub->where('name', PerimetreColis::COLIS_AFFECTES))
              ->orWhere('role', UserRole::LIVREUR->value);
        });
    }

    /**
     * Comptes actifs.
     */
    public function scopeActiveAccounts(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
