<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgenceMembre extends Model
{
    public const ROLES = [
        'livreur' => 'Livreur',
        'ramasseur' => 'Ramasseur',
        'agent' => 'Agent',
        'suivi' => 'Suivi',
        'manager' => 'Manager',
    ];

    protected $fillable = ['agence_id', 'user_id', 'role', 'actif', 'depuis', 'jusqua'];

    protected $casts = ['actif' => 'boolean', 'depuis' => 'date', 'jusqua' => 'date'];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
