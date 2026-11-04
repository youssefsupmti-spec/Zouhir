<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Référentiel des statuts colis (ids legacy `trackingstates` conservés). */
class Statut extends Model
{
    use SoftDeletes;

    public const GROUPES = [
        'nouveau' => 'Nouveau',
        'en_cours' => 'En cours',
        'livre' => 'Livré',
        'retour' => 'Retour',
        'annule' => 'Annulé / échec',
    ];

    public const ORIGINES = [
        'referentiel_legacy' => 'Référentiel de l’ancien site',
        'hors_referentiel' => 'Libellé trouvé sur des colis, hors référentiel',
        'application' => 'Créé dans la plateforme',
    ];

    protected $fillable = ['libelle', 'couleur', 'groupe', 'kpi_legacy', 'acteurs_legacy', 'actif', 'origine', 'ordre'];

    protected $casts = ['actif' => 'boolean', 'ordre' => 'integer'];

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'statut_id');
    }
}
