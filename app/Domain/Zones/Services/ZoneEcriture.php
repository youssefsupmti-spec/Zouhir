<?php

declare(strict_types=1);

namespace App\Domain\Zones\Services;

use App\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Écritures de l'écran « Zones ». Chaque geste est une transaction ; les rattachements de
 * villes sont des UPDATE par lot (zone_id + code_zone ensemble, jamais désynchronisés).
 * Les colis ne sont jamais réécrits (leur code de zone est figé à la création).
 */
final class ZoneEcriture
{
    /** @param  array{name: string, code: string, type: string, description: ?string}  $data */
    public function creer(array $data): Zone
    {
        return Zone::query()->create($data);
    }

    /** @param  array{name: string, code: string, type: string, description: ?string}  $data */
    public function modifier(Zone $zone, array $data): Zone
    {
        // Le hook Zone::saved reprend le nouveau code sur toutes les villes de la zone.
        DB::transaction(fn () => $zone->update($data));

        return $zone;
    }

    /** Corbeille : les villes sont DÉTACHÉES (jamais supprimées), comme WinningExpress. */
    public function supprimer(Zone $zone): int
    {
        return DB::transaction(function () use ($zone) {
            $n = DB::table('villes')->where('zone_id', $zone->id)->update(['zone_id' => null, 'code_zone' => null, 'updated_at' => now()]);
            $zone->delete();

            return $n;
        });
    }

    /** Restaure une zone (vide : ses anciennes villes ont pu être reclassées entre-temps). */
    public function restaurer(Zone $zone): void
    {
        $zone->restore();
    }

    /**
     * Rattache des villes à la zone (une ville d'une autre zone y est DÉPLACÉE).
     *
     * @param  list<int>  $villeIds
     */
    public function rattacher(Zone $zone, array $villeIds): int
    {
        return DB::transaction(fn () => DB::table('villes')
            ->whereIn('id', $villeIds)
            ->whereNull('deleted_at')
            ->update(['zone_id' => $zone->id, 'code_zone' => $zone->code, 'updated_at' => now()]));
    }

    /**
     * Retire des villes de la zone (elles deviennent « sans zone »). Seules les villes
     * réellement dans cette zone sont touchées.
     *
     * @param  list<int>  $villeIds
     */
    public function detacher(Zone $zone, array $villeIds): int
    {
        return DB::transaction(fn () => DB::table('villes')
            ->whereIn('id', $villeIds)
            ->where('zone_id', $zone->id)
            ->update(['zone_id' => null, 'code_zone' => null, 'updated_at' => now()]));
    }
}
