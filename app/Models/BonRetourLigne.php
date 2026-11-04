<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonRetourLigne extends Model
{
    protected $table = 'bon_retour_command';

    protected $guarded = ['id'];

    protected $casts = ['prix_brut' => 'decimal:2', 'total' => 'decimal:2'];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class)->withTrashed();
    }
}
