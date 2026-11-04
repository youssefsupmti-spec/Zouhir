<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class)->withTrashed();
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id')->withTrashed();
    }
}
