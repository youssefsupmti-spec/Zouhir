<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonRamassageLigne extends Model
{
    protected $table = 'bon_ramassage_lines';

    protected $guarded = ['id'];

    protected $casts = ['prix' => 'decimal:2'];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class)->withTrashed();
    }
}
