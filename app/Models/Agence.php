<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agence extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'nom', 'ville_id', 'telephone', 'adresse', 'est_principale', 'actif'];

    protected $casts = ['est_principale' => 'boolean', 'actif' => 'boolean'];

    public static function principale(): ?self
    {
        return static::query()->orderByDesc('est_principale')->orderBy('id')->first();
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    public function membres(): HasMany
    {
        return $this->hasMany(AgenceMembre::class);
    }
}
