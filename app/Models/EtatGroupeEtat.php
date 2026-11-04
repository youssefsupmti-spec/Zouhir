<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Rattachement d'un état (statut) à un groupe, unique par axe. */
class EtatGroupeEtat extends Pivot
{
    protected $table = 'etat_groupe_etats';

    public $incrementing = true;

    protected $fillable = ['groupe_id', 'axe', 'etat_id'];

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(EtatGroupe::class, 'groupe_id');
    }

    public function etat(): BelongsTo
    {
        return $this->belongsTo(Statut::class, 'etat_id');
    }
}
