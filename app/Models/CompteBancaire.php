<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompteBancaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comptes_bancaires';

    public const OWNER_CLIENT = 'client';
    public const OWNER_LIVREUR = 'livreur';
    public const OWNER_AGENCE = 'agence';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'titulaire_nom',
        'titulaire_prenom',
        'bank_id',
        'bank_name',
        'rib',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'bank_id' => 'integer',
        ];
    }

    /** Relations */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /**
     * Data Isolation : tous les comptes pour la gestion des comptes
     * (manage_users / admin), sinon uniquement les comptes du titulaire (owner_id).
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user->can(\App\Domain\Auth\Enums\Permission::MANAGE_USERS->value)) {
            return $query;
        }

        return $user->can(\App\Domain\Auth\Enums\Permission::VIEW_FINANCES->value)
            ? $query->where('owner_id', $user->id)
            : $query->whereRaw('1 = 0');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
