<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zone d'un coursier de la flotte régionale (livraison | ramassage) — structure Zakaria2. */
class LivreurZone extends Model
{
    public const TYPES = ['livraison' => 'Livraison', 'ramassage' => 'Ramassage'];

    protected $fillable = ['user_id', 'zone_id', 'zone_type'];

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
