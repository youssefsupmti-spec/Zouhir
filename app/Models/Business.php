<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'businesses';

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'city',
        'address',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Data Isolation : toutes les enseignes pour la gestion des comptes
     * (manage_users / admin), sinon uniquement celles du marchand (user_id).
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user->can(\App\Domain\Auth\Enums\Permission::MANAGE_USERS->value)) {
            return $query;
        }

        return $user->isClient()
            ? $query->where('user_id', $user->id)
            : $query->whereRaw('1 = 0');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'business_id');
    }
}

