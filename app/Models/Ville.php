<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ville extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'villes';

    protected $fillable = [
        'name_fr',
        'name_ar',
        'zone_id',
        'code_zone',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'destinataire_ville_id');
    }
}
