<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Barème STANDARD : le prix d'une ville, tous clients confondus.
 *
 * Source de vérité UNIQUE du tarif standard dans Zouhir (au plus une ligne par
 * ville, contrainte UNIQUE en base). Une ville sans ligne n'a PAS de tarif
 * standard : la résolution renvoie alors une anomalie `missing`, jamais un
 * montant inventé.
 */
class StandardTarif extends Model
{
    protected $table = 'standard_tarifs';

    protected $fillable = [
        'ville_id',
        'frais_livraison',
        'frais_refus',
        'frais_retour',
    ];

    protected $casts = [
        'ville_id' => 'integer',
        'frais_livraison' => 'decimal:2',
        'frais_refus' => 'decimal:2',
        'frais_retour' => 'decimal:2',
    ];

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }
}
