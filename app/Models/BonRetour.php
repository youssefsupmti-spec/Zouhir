<?php

namespace App\Models;

use App\Models\Concerns\EstUnBon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonRetour extends Model
{
    use EstUnBon;
    use SoftDeletes;

    public const STATUTS = ['brouillon' => 'Brouillon', 'enregistre' => 'Enregistré', 'traite' => 'Traité'];

    public const TYPES = ['client' => 'Retour marchand', 'livreur' => 'Décharge livreur'];

    protected $table = 'bons_retour';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return $this->castsDuBon();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BonRetourLigne::class, 'bon_retour_id')->orderBy('sequence');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id')->withTrashed();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
