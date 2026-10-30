<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'zones';

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    public function villes(): HasMany
    {
        return $this->hasMany(Ville::class, 'zone_id');
    }
}
