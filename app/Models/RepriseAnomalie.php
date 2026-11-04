<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Écart constaté par la reprise de l'ancienne base, en attente d'une décision. */
class RepriseAnomalie extends Model
{
    public const DOMAINES = [
        'commandes' => 'Colis',
        'clients' => 'Clients',
        'livreurs' => 'Livreurs',
        'villes' => 'Villes',
        'zones' => 'Zones',
        'statuts' => 'Statuts',
        'historique' => 'Historique',
        'notes' => 'Notes',
        'bons_livraison' => 'Bons de livraison',
        'bons_envoi' => 'Bons d’envoi',
        'bons_ramassage' => 'Bons de ramassage',
        'bons_retour' => 'Bons de retour',
        'banques' => 'Banques & RIB',
    ];

    public const GRAVITES = ['bloquant' => 'Bloquant', 'attention' => 'À vérifier', 'decision' => 'Décision à prendre', 'info' => 'Information'];

    public const STATUTS = ['ouverte' => 'Ouverte', 'resolue' => 'Résolue', 'ignoree' => 'Ignorée'];

    protected $guarded = ['id'];

    protected $casts = ['details' => 'array', 'traite_le' => 'datetime', 'vue_le' => 'datetime'];

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par')->withTrashed();
    }
}
