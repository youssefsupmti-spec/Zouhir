<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Livreurs\Services;

use App\Domain\Tarifs\Commun\Support\Montants;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lectures de l'écran « Tarifs livreurs » (adapté de WinningExpress LivreurTarifQuery).
 * Agrégats en SQL ; une requête par besoin, jamais une par livreur ou par ville.
 */
final class LivreurTarifQuery
{
    /**
     * Livreurs ACTIFS (non archivés) proposés au sélecteur, avec leur nombre de villes affectées.
     *
     * @return list<array{id: int, nom: string, telephone: ?string, avatar: ?string, nb_villes: int}>
     */
    public function livreurs(): array
    {
        $compte = DB::table('livreur_tarifs')
            ->join('villes', 'villes.id', '=', 'livreur_tarifs.ville_id')
            ->whereNull('livreur_tarifs.deleted_at')
            ->whereNull('villes.deleted_at')
            ->groupBy('livreur_tarifs.livreur_id')
            ->selectRaw('livreur_tarifs.livreur_id, COUNT(*) as n')
            ->pluck('n', 'livreur_id');

        return User::drivers()
            ->orderBy('nom_complet')
            ->get(['id', 'nom_complet', 'telephone', 'picture'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'nom' => (string) $u->nom_complet,
                'telephone' => $u->telephone,
                'avatar' => $u->picture ?: null,
                'nb_villes' => (int) ($compte[$u->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Indicateurs (livreurs et villes ACTIFS uniquement).
     *
     * @return array{livreurs: int, regles: int, villes: int, moyenne: ?string, zero: int}
     */
    public function statistiques(): array
    {
        $s = DB::table('livreur_tarifs')
            ->join('villes', 'villes.id', '=', 'livreur_tarifs.ville_id')
            ->join('users', 'users.id', '=', 'livreur_tarifs.livreur_id')
            ->whereNull('livreur_tarifs.deleted_at')
            ->whereNull('villes.deleted_at')
            ->whereNull('users.deleted_at')
            ->selectRaw('COUNT(DISTINCT livreur_tarifs.livreur_id) as livreurs')
            ->selectRaw('COUNT(*) as regles')
            ->selectRaw('COUNT(DISTINCT livreur_tarifs.ville_id) as villes')
            ->selectRaw('AVG(livreur_tarifs.commission_livraison) as moy')
            ->selectRaw('SUM(CASE WHEN livreur_tarifs.commission_livraison = 0 THEN 1 ELSE 0 END) as zero')
            ->first();

        $regles = (int) ($s->regles ?? 0);

        return [
            'livreurs' => (int) ($s->livreurs ?? 0),
            'regles' => $regles,
            'villes' => (int) ($s->villes ?? 0),
            'moyenne' => $regles > 0 ? number_format((float) $s->moy, 2, '.', '') : null,
            'zero' => (int) ($s->zero ?? 0),
        ];
    }

    /**
     * Grille d'UN livreur : commissions spécifiques (actives et supprimées) groupées par zone.
     * Une seule requête.
     *
     * @return array{zones: list<array<string, mixed>>, supprimees: list<array<string, mixed>>}
     */
    public function grilleDuLivreur(int $livreurId, ?int $zoneId = null): array
    {
        $lignes = DB::table('livreur_tarifs')
            ->join('villes', 'villes.id', '=', 'livreur_tarifs.ville_id')
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->where('livreur_tarifs.livreur_id', $livreurId)
            ->whereNull('villes.deleted_at')
            ->when($zoneId !== null, fn ($q) => $q->where('villes.zone_id', $zoneId))
            ->orderByRaw('CASE WHEN zones.name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('zones.name')
            ->orderBy('villes.name_fr')
            ->get([
                'zones.id as zone_id', 'zones.name as zone_nom', 'zones.code as zone_code',
                'villes.id as ville_id', 'villes.name_fr as ville_nom',
                'livreur_tarifs.id', 'livreur_tarifs.commission_livraison', 'livreur_tarifs.commission_refus',
                'livreur_tarifs.deleted_at', 'livreur_tarifs.updated_at',
            ]);

        $zones = [];
        $supprimees = [];

        foreach ($lignes as $l) {
            $livraison = Montants::lire(['v' => $l->commission_livraison], ['v' => 'v'])['v'];
            $ligne = [
                'id' => (int) $l->id,
                'ville_id' => (int) $l->ville_id,
                'ville_nom' => (string) $l->ville_nom,
                'zone_nom' => (string) ($l->zone_nom ?? 'Villes sans zone'),
                'livraison' => $livraison,
                'refus' => Montants::lire(['v' => $l->commission_refus], ['v' => 'v'])['v'],
                'zero' => (float) $livraison === 0.0,
                'updated_at' => $l->updated_at,
            ];

            if ($l->deleted_at !== null) {
                $supprimees[] = $ligne;

                continue;
            }

            $cle = $l->zone_id === null ? 'sans-zone' : 'z'.$l->zone_id;
            $zones[$cle] ??= [
                'zone_id' => $l->zone_id === null ? null : (int) $l->zone_id,
                'zone_nom' => (string) ($l->zone_nom ?? 'Villes sans zone'),
                'zone_code' => $l->zone_code,
                'lignes' => [],
            ];
            $zones[$cle]['lignes'][] = $ligne;
        }

        return ['zones' => array_values($zones), 'supprimees' => $supprimees];
    }
}
