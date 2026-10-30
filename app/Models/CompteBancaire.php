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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
