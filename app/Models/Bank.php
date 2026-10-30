<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'banks';

    protected $fillable = [
        'name',
        'code_etablissement',
        'swift_code',
        'logo',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /** Relations */
    public function comptes(): HasMany
    {
        return $this->hasMany(CompteBancaire::class, 'bank_id');
    }

    /** Scopes */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
