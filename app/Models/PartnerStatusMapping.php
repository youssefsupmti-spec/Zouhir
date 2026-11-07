<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Correspondance « statut du partenaire » → statut OUAISS (libellé + statut du référentiel). */
class PartnerStatusMapping extends Model
{
    protected $fillable = ['delivery_partner_id', 'partner_status', 'colis_status', 'statut_id', 'notes'];

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class, 'delivery_partner_id');
    }

    public function statut(): BelongsTo
    {
        return $this->belongsTo(Statut::class);
    }
}
