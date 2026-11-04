<?php

namespace App\Models\Concerns;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Comportement commun aux bons (livraison, envoi, ramassage, retour). */
trait EstUnBon
{
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_id')->withTrashed();
    }

    /** @return array<string, string> */
    protected function castsDuBon(): array
    {
        return [
            'legacy_brut' => 'array',
            'legacy_dateadd_at' => 'datetime',
            'traite_at' => 'datetime',
            'expedie_at' => 'datetime',
            'total_colis' => 'integer',
            'montant_total_brut' => 'decimal:2',
            'frais_total' => 'decimal:2',
            'traite_sans_pointage_historique' => 'boolean',
        ];
    }
}
