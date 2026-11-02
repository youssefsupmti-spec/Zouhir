<?php

declare(strict_types=1);

namespace App\Application\Zones\Backfill;

use App\Domain\Zones\Support\ReferentielZones;
use Illuminate\Support\Facades\DB;

/**
 * Découpage des villes Zouhir en zones logistiques (remplace les 6 macro-zones legacy).
 *
 * Idempotent : seules les villes SANS zone ou encore dans une ancienne macro-zone sont
 * (re)classées ; une ville déjà placée dans une zone du référentiel (backfill précédent ou
 * classement manuel depuis la page Zones) n'est jamais déplacée. Les villes archivées sont
 * traitées comme les actives (l'historique garde une zone cohérente).
 *
 * Ne touche JAMAIS `commands` : `destinataire_zone_code` est un instantané figé à la création.
 */
final class DecoupageZones
{
    /**
     * @return array{zones_a_creer: list<string>, zones_existantes: list<string>, zones_conflit: list<string>,
     *     anciennes_a_archiver: list<string>, noms_a_liberer: array<string, string>, villes: list<array<string, mixed>>, par_zone: array<string, int>,
     *     par_regle: array<string, int>, inchangees: int, total: int}
     */
    public function analyser(): array
    {
        $zones = DB::table('zones')->get(['id', 'code', 'name', 'deleted_at'])->keyBy('code');
        $codesCibles = array_keys(ReferentielZones::ZONES);
        $anciennes = array_keys(ReferentielZones::ANCIENNES);

        $aCreer = $existantes = $conflits = $nomsALiberer = [];
        foreach ($codesCibles as $code) {
            $z = $zones->get($code);
            $homonyme = $zones->first(fn ($o) => $o->code !== $code && mb_strtolower((string) $o->name) === mb_strtolower(ReferentielZones::ZONES[$code]['name']));
            if ($z === null && $homonyme !== null && ! in_array($homonyme->code, $anciennes, true)) {
                $conflits[] = $code; // nom déjà porté par une zone hors référentiel : jamais écrasée
            } elseif ($z === null) {
                // Une ancienne macro-zone porte ce nom (ex. « Zone Oriental ») : elle sera renommée avant archivage.
                if ($homonyme !== null) {
                    $nomsALiberer[$homonyme->code] = $homonyme->name.' (ancienne '.$homonyme->code.')';
                }
                $aCreer[] = $code;
            } elseif ($z->deleted_at !== null) {
                $conflits[] = $code; // en corbeille : jamais ressuscitée d'office
            } else {
                $existantes[] = $code;
            }
        }

        $idsAnciennes = $zones->only($anciennes)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $villes = DB::table('villes')
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->orderBy('villes.id')
            ->get(['villes.id', 'villes.name_fr', 'villes.zone_id', 'villes.deleted_at', 'zones.code as zone_code']);

        $lignes = [];
        $parZone = $parRegle = [];
        $inchangees = 0;
        foreach ($villes as $v) {
            $aClasser = $v->zone_id === null || in_array((int) $v->zone_id, $idsAnciennes, true);
            if (! $aClasser) {
                $inchangees++;

                continue;
            }
            $cible = ReferentielZones::zonePour((string) $v->name_fr, $v->zone_code);
            if ($cible['code'] !== null && in_array($cible['code'], $conflits, true)) {
                $cible = ['code' => null, 'regle' => 'zone_en_corbeille'];
            }
            $parZone[$cible['code'] ?? '—'] = ($parZone[$cible['code'] ?? '—'] ?? 0) + 1;
            $parRegle[$cible['regle']] = ($parRegle[$cible['regle']] ?? 0) + 1;
            $lignes[] = [
                'id' => (int) $v->id,
                'nom' => (string) $v->name_fr,
                'archivee' => $v->deleted_at !== null,
                'avant' => $v->zone_code,
                'apres' => $cible['code'],
                'regle' => $cible['regle'],
            ];
        }
        ksort($parZone);

        // Une ancienne macro-zone est archivée si plus aucune ville (même archivée) n'y reste.
        $anciennesAArchiver = $zones->only($anciennes)->filter(fn ($z) => $z->deleted_at === null)->keys()->values()->all();

        return [
            'zones_a_creer' => $aCreer,
            'zones_existantes' => $existantes,
            'zones_conflit' => $conflits,
            'anciennes_a_archiver' => $anciennesAArchiver,
            'noms_a_liberer' => $nomsALiberer,
            'villes' => $lignes,
            'par_zone' => $parZone,
            'par_regle' => $parRegle,
            'inchangees' => $inchangees,
            'total' => count($villes),
        ];
    }

    /**
     * Applique le découpage en une transaction : zones créées, villes rattachées par lot
     * (un UPDATE par zone cible), anciennes macro-zones vidées puis archivées (soft delete).
     *
     * @param  array<string, mixed>  $analyse
     * @return array{zones_creees: int, villes_rattachees: int, villes_sans_zone: int, anciennes_archivees: int}
     */
    public function appliquer(array $analyse): array
    {
        return DB::transaction(function () use ($analyse) {
            $now = now();
            foreach ($analyse['noms_a_liberer'] as $ancien => $nom) {
                DB::table('zones')->where('code', $ancien)->update(['name' => $nom, 'updated_at' => $now]);
            }
            foreach ($analyse['zones_a_creer'] as $code) {
                $z = ReferentielZones::ZONES[$code];
                DB::table('zones')->insert([
                    'name' => $z['name'], 'code' => $code, 'type' => $z['type'], 'description' => $z['description'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $ids = DB::table('zones')->whereNull('deleted_at')->pluck('id', 'code');
            $groupes = [];
            foreach ($analyse['villes'] as $l) {
                $groupes[$l['apres'] ?? ''][] = $l['id'];
            }

            $rattachees = $sansZone = 0;
            foreach ($groupes as $code => $villeIds) {
                foreach (array_chunk($villeIds, 500) as $lot) {
                    $n = DB::table('villes')->whereIn('id', $lot)->update($code === ''
                        ? ['zone_id' => null, 'code_zone' => null, 'updated_at' => $now]
                        : ['zone_id' => $ids[$code], 'code_zone' => $code, 'updated_at' => $now]);
                    $code === '' ? $sansZone += $n : $rattachees += $n;
                }
            }

            $archivees = 0;
            foreach ($analyse['anciennes_a_archiver'] as $code) {
                $zone = DB::table('zones')->where('code', $code)->whereNull('deleted_at')->first(['id']);
                if ($zone !== null && ! DB::table('villes')->where('zone_id', $zone->id)->exists()) {
                    $archivees += DB::table('zones')->where('id', $zone->id)->update(['deleted_at' => $now, 'updated_at' => $now]);
                }
            }

            return [
                'zones_creees' => count($analyse['zones_a_creer']),
                'villes_rattachees' => $rattachees,
                'villes_sans_zone' => $sansZone,
                'anciennes_archivees' => $archivees,
            ];
        });
    }
}
