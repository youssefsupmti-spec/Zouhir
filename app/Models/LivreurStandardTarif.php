<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Barème STANDARD des commissions livreurs : commission par défaut d'une ville
 * lorsqu'un livreur n'a pas de commission spécifique (legacy `shippingfees` dlm = 0).
 *
 * Distinct de {@see StandardTarif} (frais facturés au MARCHAND) : les deux barèmes
 * ne partagent ni table ni calcul. Au plus une ligne par ville (UNIQUE ville_id).
 */
class LivreurStandardTarif extends Model
{
    protected $table = 'livreur_standard_tarifs';

    protected $fillable = [
        'ville_id',
        'commission_livraison',
        'commission_refus',
        'legacy_ids',
    ];

    protected $casts = [
        'ville_id' => 'integer',
        'commission_livraison' => 'decimal:2',
        'commission_refus' => 'decimal:2',
        'legacy_ids' => 'array',
    ];

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id')->withTrashed();
    }
}
