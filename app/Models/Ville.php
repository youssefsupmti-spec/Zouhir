<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ville extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'villes';

    protected $fillable = [
        'name_fr',
        'name_ar',
        'zone_id',
        'code_zone',
    ];

    protected static function booted(): void
    {
        // `code_zone` est DÉRIVÉ de `zone_id` (jamais saisi) : une ville sans zone n'a pas de code.
        static::saving(function (self $ville): void {
            if ($ville->isDirty('zone_id') || ! $ville->exists) {
                $ville->code_zone = $ville->zone_id === null
                    ? null
                    : Zone::withTrashed()->whereKey($ville->zone_id)->value('code');
            }
        });
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    /** Barème standard de la ville (au plus un, contrainte UNIQUE). */
    public function standardTarif(): HasOne
    {
        return $this->hasOne(StandardTarif::class, 'ville_id');
    }

    public function clientTarifs(): HasMany
    {
        return $this->hasMany(ClientTarif::class, 'ville_id');
    }

    /** Commissions spécifiques des livreurs sur cette ville (actives ; corbeille via withTrashed()). */
    public function livreurTarifs(): HasMany
    {
        return $this->hasMany(LivreurTarif::class, 'ville_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'destinataire_ville_id');
    }
}
