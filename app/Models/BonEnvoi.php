<?php

namespace App\Models;

use App\Models\Concerns\EstUnBon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonEnvoi extends Model
{
    use EstUnBon;
    use SoftDeletes;

    public const STATUTS = ['brouillon' => 'Brouillon', 'expedie' => 'Expédié', 'traite' => 'Traité'];

    protected $table = 'bons_envoi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return $this->castsDuBon();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BonEnvoiLigne::class, 'bon_envoi_id')->orderBy('sequence');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }
}
