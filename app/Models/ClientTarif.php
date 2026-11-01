<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Barème NÉGOCIÉ : le prix d'une ville pour UN client marchand.
 *
 * Échelon prioritaire de la cascade client ({@see FeeResolutionService}) :
 * la PRÉSENCE d'une ligne active l'emporte sur le standard, même à 0.00.
 * La supprimer (SoftDeletes) renvoie la ville au tarif standard pour les
 * FUTURES résolutions, sans toucher aux colis existants.
 *
 * Adapté de WinningExpress (App\Models\ClientTarif) :
 *  - unicité (client_id, ville_id) imposée par la base (WinningExpress n'en avait pas) ;
 *  - montants en `decimal:2` (chaînes exactes) au lieu de `float` ;
 *  - pas de TracksActivity (absent de Zouhir).
 */
class ClientTarif extends Model
{
    use SoftDeletes;

    protected $table = 'client_tarifs';

    protected $fillable = [
        'client_id',
        'ville_id',
        'frais_livraison',
        'frais_refus',
        'frais_retour',
        'legacy_ids',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'ville_id' => 'integer',
        'frais_livraison' => 'decimal:2',
        'frais_refus' => 'decimal:2',
        'frais_retour' => 'decimal:2',
        // Identifiants legacy d'origine (clientfees / gshippingfees) ; null = créé dans Zouhir.
        'legacy_ids' => 'array',
    ];

    /** Relation HISTORIQUE : un client archivé reste rattaché à ses tarifs (rapport #35, décision 3). */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id')->withTrashed();
    }

    /** Relation HISTORIQUE : une ville archivée reste rattachée à ses tarifs (rapport #35, décision 2). */
    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id')->withTrashed();
    }

    /** Le barème d'un couple (client, ville) désigné par identifiants. */
    public function scopePour(Builder $query, ?int $clientId, ?int $villeId): Builder
    {
        if ($clientId === null || $villeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('client_id'), $clientId)
            ->where($this->qualifyColumn('ville_id'), $villeId);
    }

    public function scopeDuClient(Builder $query, int $clientId): Builder
    {
        return $query->where($this->qualifyColumn('client_id'), $clientId);
    }
}
