<?php

namespace App\Models;

use App\Models\Concerns\EstUnBon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonLivraison extends Model
{
    use EstUnBon;
    use SoftDeletes;

    public const STATUTS = ['brouillon' => 'Brouillon', 'expedie' => 'Expédié', 'traite' => 'Traité'];

    protected $table = 'bons_livraison';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return $this->castsDuBon();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BonLivraisonLigne::class, 'bon_livraison_id')->orderBy('sequence');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
