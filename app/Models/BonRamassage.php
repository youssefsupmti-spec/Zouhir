<?php

namespace App\Models;

use App\Models\Concerns\EstUnBon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonRamassage extends Model
{
    use EstUnBon;
    use SoftDeletes;

    public const STATUTS = ['brouillon' => 'Brouillon', 'valide' => 'Validé', 'traite' => 'Traité'];

    protected $table = 'bon_ramassage';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return $this->castsDuBon() + ['total' => 'decimal:2', 'client_validated_at' => 'datetime'];
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BonRamassageLigne::class, 'bon_ramassage_id')->orderBy('sequence');
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
