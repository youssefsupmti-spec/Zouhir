<?php

declare(strict_types=1);

namespace App\Domain\Villes\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sentinelle « Uknow-ville » (zone « Uknow-zone », code UKN) — décision du propriétaire (rapport 56).
 *
 * Reçoit les colis legacy dont la ville est vide, « = » ou introuvable dans le référentiel : les
 * clés étrangères restent valides, le libellé d'origine reste sur le colis (`destinataire_ville`)
 * et chaque cas est listé dans les anomalies de reprise pour correction.
 *
 * La sentinelle n'est JAMAIS renvoyée par VilleRef : seul le code de reprise l'utilise, explicitement.
 */
final class VilleInconnue
{
    public const NOM_VILLE = 'Uknow-ville';

    public const NOM_ZONE = 'Uknow-zone';

    public const CODE_ZONE = 'UKN';

    /** Crée (ou relie) la zone et la ville sentinelles ; renvoie [ville_id, zone_id]. */
    public static function assurer(): array
    {
        $zoneId = DB::table('zones')->where('code', self::CODE_ZONE)->orWhere('name', self::NOM_ZONE)->value('id');

        if ($zoneId === null) {
            $zone = [
                'name' => self::NOM_ZONE,
                'code' => self::CODE_ZONE,
                'description' => "Zone d'attente des colis sans ville ou à destination inconnue (à corriger)",
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('zones', 'type')) {
                $zone['type'] = 'nationale';
            }
            $zoneId = DB::table('zones')->insertGetId($zone);
        }

        $villeId = DB::table('villes')->where('name_fr', self::NOM_VILLE)->value('id');

        if ($villeId === null) {
            $villeId = DB::table('villes')->insertGetId([
                'name_fr' => self::NOM_VILLE,
                'name_ar' => 'مدينة غير محددة',
                'zone_id' => $zoneId,
                'code_zone' => self::CODE_ZONE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('villes')->where('id', $villeId)->update(['zone_id' => $zoneId, 'code_zone' => self::CODE_ZONE, 'updated_at' => now()]);
        }

        return [(int) $villeId, (int) $zoneId];
    }
}
