<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Couverture d'une zone : titulaire, remplaçant (flotte) ou partenaire (réseau national). */
class ZoneCouverture extends Model
{
    public const TYPES = ['regional_flotte' => 'Flotte régionale', 'national_partenaire' => 'Réseau national'];

    public const STATUTS = ['optimal' => 'Optimal', 'normal' => 'Normal', 'degrade' => 'Dégradé', 'non_couvert' => 'Non couvert'];

    protected $fillable = ['zone_id', 'type_couverture', 'titulaire_user_id', 'backup_user_id', 'delivery_partner_id', 'statut_couverture', 'capacite_journaliere', 'bascule_at', 'notes', 'updated_by'];

    protected $casts = ['capacite_journaliere' => 'integer', 'bascule_at' => 'datetime'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'titulaire_user_id')->withTrashed();
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(User::class, 'backup_user_id')->withTrashed();
    }

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class, 'delivery_partner_id')->withTrashed();
    }
}
