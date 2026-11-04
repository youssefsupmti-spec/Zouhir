<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteMessage extends Model
{
    public const ROLES = ['livreur' => 'Livreur', 'moderateur' => 'Modérateur', 'client' => 'Client', 'externe' => 'Externe', 'inconnu' => 'Auteur inconnu'];

    protected $guarded = ['id'];

    protected $casts = ['date_exacte' => 'boolean', 'position' => 'integer'];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class)->withTrashed();
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
