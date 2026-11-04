<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Transporteur du réseau national ; `user_id` = compte utilisé par l'ancien site pour ses bons. */
class Partenaire extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'raison_sociale', 'ice', 'telephone', 'email', 'user_id', 'notes', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function bonsEnvoi(): HasMany
    {
        return $this->hasMany(BonEnvoi::class);
    }
}
