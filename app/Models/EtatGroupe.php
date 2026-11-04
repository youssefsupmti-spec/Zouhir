<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groupe d'états (axe « flux » = onglets de la page Colis). Structure de WinningExpress / Zakaria2.
 * Les rattachements passent par ClassificationEtats (jamais d'écriture directe du pivot ailleurs).
 */
class EtatGroupe extends Model
{
    public const AXE_FLUX = 'flux';

    /** Groupes utilisés par les règles métier (jalons, statistiques) : désactivables, jamais supprimables. */
    public const CODES_SYSTEME = ['nouveau', 'en_cours', 'planifie', 'livre', 'echec', 'retour_agence', 'retour_stock', 'retour_client'];

    protected $table = 'etat_groupes';

    protected $fillable = ['axe', 'code', 'nom', 'description', 'couleur', 'sort_order', 'actif', 'suivi_actif', 'suivi_planifie'];

    protected $casts = ['sort_order' => 'integer', 'actif' => 'boolean', 'suivi_actif' => 'boolean', 'suivi_planifie' => 'boolean'];

    public function estSysteme(): bool
    {
        return in_array($this->code, self::CODES_SYSTEME, true);
    }

    public function etats(): BelongsToMany
    {
        return $this->belongsToMany(Statut::class, 'etat_groupe_etats', 'groupe_id', 'etat_id')
            ->using(EtatGroupeEtat::class)->withPivot('axe')->withTimestamps();
    }

    public function rattachements(): HasMany
    {
        return $this->hasMany(EtatGroupeEtat::class, 'groupe_id');
    }
}
