<?php

namespace App\Models;

use App\Domain\Zones\Enums\TypeZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Zone extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'zones';

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
    ];

    protected $attributes = [
        'type' => 'nationale',
    ];

    protected function casts(): array
    {
        return ['type' => TypeZone::class];
    }

    protected static function booted(): void
    {
        /*
         * Le CODE change ⇒ l'étiquette `code_zone` de TOUTES ses villes suit (comme WinningExpress).
         * Un seul UPDATE, hors modèle : une zone peut porter des centaines de villes.
         * Les colis gardent leur `destinataire_zone_code` figé à la création (jamais réécrit).
         */
        static::saved(function (self $zone): void {
            if ($zone->wasChanged('code')) {
                DB::table('villes')->where('zone_id', $zone->getKey())->update(['code_zone' => (string) $zone->code]);
            }
        });
    }

    public function villes(): HasMany
    {
        return $this->hasMany(Ville::class, 'zone_id');
    }
}
