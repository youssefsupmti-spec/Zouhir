<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonLivraisonLigne extends Model
{
    protected $table = 'bon_livraison_command';

    protected $guarded = ['id'];

    protected $casts = ['is_checked' => 'boolean', 'is_anomalie' => 'boolean', 'prix_brut' => 'decimal:2', 'total' => 'decimal:2', 'checked_at' => 'datetime'];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class)->withTrashed();
    }

    public function bon(): BelongsTo
    {
        return $this->belongsTo(BonLivraison::class, 'bon_livraison_id');
    }
}
