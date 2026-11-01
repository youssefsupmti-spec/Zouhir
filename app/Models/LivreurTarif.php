<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commission SPÉCIFIQUE d'un livreur pour une ville : ce que l'agence VERSE au livreur.
 *
 * Adapté de WinningExpress (App\Models\LivreurTarif : `user_id`, `commission_livraison`,
 * `commission_refus`). Sans rapport avec ce que paie le marchand (`client_tarifs` /
 * `standard_tarifs`) : c'est l'autre côté de la marge.
 *
 * Une seule ligne par couple (livreur, ville), corbeille comprise (UNIQUE en base) :
 * une commission supprimée puis réaffectée est RESTAURÉE, jamais doublée.
 */
class LivreurTarif extends Model
{
    use SoftDeletes;

    protected $table = 'livreur_tarifs';

    protected $fillable = [
        'livreur_id',
        'ville_id',
        'commission_livraison',
        'commission_refus',
        'legacy_ids',
    ];

    protected $casts = [
        'livreur_id' => 'integer',
        'ville_id' => 'integer',
        'commission_livraison' => 'decimal:2',
        'commission_refus' => 'decimal:2',
        // Identifiants legacy `shippingfees` d'origine ; null = créé dans Zouhir.
        'legacy_ids' => 'array',
    ];

    /** Livreur payé à ce tarif (relation historique : compte archivé compris). */
    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_id')->withTrashed();
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id')->withTrashed();
    }

    public function scopePour(Builder $query, ?int $livreurId, ?int $villeId): Builder
    {
        if ($livreurId === null || $villeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('livreur_id'), $livreurId)
            ->where($this->qualifyColumn('ville_id'), $villeId);
    }
}
