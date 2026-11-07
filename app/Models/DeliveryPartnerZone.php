<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Zone desservie par un partenaire, avec son tarif de transit et son délai garanti. */
class DeliveryPartnerZone extends Pivot
{
    protected $table = 'delivery_partner_zones';

    public $incrementing = true;

    protected $fillable = ['delivery_partner_id', 'zone_id', 'tarif_transit_unitaire', 'delai_garanti_h', 'is_exclusif'];

    protected $casts = ['tarif_transit_unitaire' => 'decimal:2', 'delai_garanti_h' => 'integer', 'is_exclusif' => 'boolean'];

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class, 'delivery_partner_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
