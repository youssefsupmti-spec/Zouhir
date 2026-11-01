<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Livreurs\Services;

use App\Domain\Tarifs\Commun\Support\Montants;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Lectures de l'écran « Tarifs livreurs ».
 *
 * Structure reprise de WinningExpress (GrilleTarifsLivreur / LivreurTarifQuery) : cartes de
 * compteurs servant d'onglets, puis Livreur → Zone → Ville. Toutes les commissions ACTIVES
 * sont montrées, y compris celles des comptes livreurs archivés (signalés, jamais masqués :
 * leurs commissions restent dues et lisibles). Agrégats en SQL, une requête par besoin.
 */
final class LivreurTarifQuery
{
    public const STATUTS = ['tous', 'actifs', 'archives', 'corbeille'];

    /**
     * Livreurs ACTIFS proposés au dialog d'affectation (comptes archivés exclus).
     *
     * @return list<array{id: int, nom: string, telephone: ?string, avatar: ?string, nb_villes: int}>
     */
    public function livreurs(): array
    {
        $compte = $this->actives()->groupBy('livreur_tarifs.livreur_id')
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

    /** Commissions ACTIVES sur villes actives (base commune des compteurs). */
    private function actives(): QueryBuilder
    {
        return DB::table('livreur_tarifs')
            ->join('villes', 'villes.id', '=', 'livreur_tarifs.ville_id')
            ->whereNull('livreur_tarifs.deleted_at')
            ->whereNull('villes.deleted_at');
    }

    /**
     * Compteurs des cartes (toutes les commissions actives, livreurs archivés compris).
     *
     * @return array{commissions: int, livreurs: int, actifs: int, archives: int, villes: int, moyenne: ?string, zero: int, corbeille: int, sans_commission: int}
     */
    public function statistiques(): array
    {
        $s = $this->actives()
            ->join('users', 'users.id', '=', 'livreur_tarifs.livreur_id')
            ->selectRaw('COUNT(*) as commissions')
            ->selectRaw('COUNT(DISTINCT livreur_tarifs.livreur_id) as livreurs')
            ->selectRaw('COUNT(DISTINCT CASE WHEN users.deleted_at IS NULL THEN livreur_tarifs.livreur_id END) as actifs')
            ->selectRaw('COUNT(DISTINCT CASE WHEN users.deleted_at IS NOT NULL THEN livreur_tarifs.livreur_id END) as archives')
            ->selectRaw('COUNT(DISTINCT livreur_tarifs.ville_id) as villes')
            ->selectRaw('AVG(livreur_tarifs.commission_livraison) as moy')
            ->selectRaw('SUM(CASE WHEN livreur_tarifs.commission_livraison = 0 THEN 1 ELSE 0 END) as zero')
            ->first();

        $commissions = (int) ($s->commissions ?? 0);
        $avecCommission = $this->actives()->distinct()->pluck('livreur_tarifs.livreur_id')->all();

        return [
            'commissions' => $commissions,
            'livreurs' => (int) ($s->livreurs ?? 0),
            'actifs' => (int) ($s->actifs ?? 0),
            'archives' => (int) ($s->archives ?? 0),
            'villes' => (int) ($s->villes ?? 0),
            'moyenne' => $commissions > 0 ? number_format((float) $s->moy, 2, '.', '') : null,
            'zero' => (int) ($s->zero ?? 0),
            'corbeille' => DB::table('livreur_tarifs')->whereNotNull('deleted_at')->count(),
            'sans_commission' => User::drivers()->whereKeyNot($avecCommission)->count(),
        ];
    }

    /**
     * Une carte par livreur (Livreur → Zone → Ville), filtrée et paginée côté serveur.
     *
     * statut : tous (livreurs portant des commissions, archivés compris) | actifs (comptes actifs,
     * y compris sans commission, pour en affecter) | archives (comptes archivés portant des commissions).
     *
     * @param  array{search?: string, zone_id?: ?int, statut?: string}  $filtres
     */
    public function resumes(array $filtres, int $parPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filtres['search'] ?? ''));
        $zoneId = $filtres['zone_id'] ?? null;
        $statut = (string) ($filtres['statut'] ?? 'tous');

        $agregat = $this->actives()
            ->groupBy('livreur_tarifs.livreur_id')
            ->selectRaw('livreur_tarifs.livreur_id as livreur_id')
            ->selectRaw('COUNT(*) as nb_villes')
            ->selectRaw('COUNT(DISTINCT villes.zone_id) as nb_zones')
            ->selectRaw('MIN(livreur_tarifs.commission_livraison) as mn')
            ->selectRaw('MAX(livreur_tarifs.commission_livraison) as mx');

        $requete = User::withTrashed()
            ->leftJoinSub($agregat, 'agg', 'agg.livreur_id', '=', 'users.id')
            ->when($statut === 'actifs',
                fn ($q) => $q->whereNull('users.deleted_at')->where(fn ($w) => $w->whereNotNull('agg.livreur_id')->orWhereIn('users.id', User::drivers()->select('users.id'))),
                fn ($q) => $q->whereNotNull('agg.livreur_id')->when($statut === 'archives', fn ($w) => $w->whereNotNull('users.deleted_at')))
            ->when($zoneId !== null, fn ($q) => $q->whereExists(fn ($sub) => $sub->from('livreur_tarifs')
                ->join('villes', 'villes.id', '=', 'livreur_tarifs.ville_id')
                ->whereColumn('livreur_tarifs.livreur_id', 'users.id')
                ->whereNull('livreur_tarifs.deleted_at')
                ->whereNull('villes.deleted_at')
                ->where('villes.zone_id', $zoneId)))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('users.nom_complet', 'like', '%'.$search.'%')
                ->orWhere('users.telephone', 'like', '%'.$search.'%')
                ->orWhere('users.email', 'like', '%'.$search.'%')))
            ->orderByRaw('CASE WHEN users.deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('users.nom_complet')
            ->select([
                'users.id', 'users.nom_complet', 'users.telephone', 'users.email', 'users.picture', 'users.deleted_at',
                'agg.nb_villes', 'agg.nb_zones', 'agg.mn', 'agg.mx',
            ]);

        return $requete->paginate($parPage)->withQueryString()->through(fn (User $u) => [
            'id' => (int) $u->id,
            'nom' => (string) $u->nom_complet,
            'telephone' => $u->telephone,
            'email' => $u->email,
            'avatar' => $u->picture ?: null,
            'archive' => $u->deleted_at !== null,
            'nb_villes' => (int) ($u->nb_villes ?? 0),
            'nb_zones' => (int) ($u->nb_zones ?? 0),
            'min' => $u->mn === null ? null : number_format((float) $u->mn, 2, '.', ''),
            'max' => $u->mx === null ? null : number_format((float) $u->mx, 2, '.', ''),
        ]);
    }

    /**
     * Grille d'UN livreur (compte archivé compris) : commissions ACTIVES groupées par zone.
     *
     * @return array{zones: list<array<string, mixed>>}
     */
    public function grilleDuLivreur(int $livreurId, ?int $zoneId = null): array
    {
        $lignes = $this->actives()
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->where('livreur_tarifs.livreur_id', $livreurId)
            ->when($zoneId !== null, fn ($q) => $q->where('villes.zone_id', $zoneId))
            ->orderByRaw('CASE WHEN zones.name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('zones.name')
            ->orderBy('villes.name_fr')
            ->get([
                'zones.id as zone_id', 'zones.name as zone_nom', 'zones.code as zone_code',
                'villes.id as ville_id', 'villes.name_fr as ville_nom',
                'livreur_tarifs.id', 'livreur_tarifs.commission_livraison', 'livreur_tarifs.commission_refus', 'livreur_tarifs.updated_at',
            ]);

        $zones = [];
        foreach ($lignes as $l) {
            $ligne = $this->ligne($l);
            $cle = $l->zone_id === null ? 'sans-zone' : 'z'.$l->zone_id;
            $zones[$cle] ??= [
                'zone_id' => $l->zone_id === null ? null : (int) $l->zone_id,
                'zone_nom' => (string) ($l->zone_nom ?? 'Villes sans zone'),
                'zone_code' => $l->zone_code,
                'lignes' => [],
            ];
            $zones[$cle]['lignes'][] = $ligne;
        }

        return ['zones' => array_values($zones)];
    }

    /**
     * Corbeille : commissions supprimées (soft delete), tous livreurs, à plat (comme l'onglet
     * « Corbeille » de l'ancien écran « Frais de livreurs »).
     *
     * @return list<array<string, mixed>>
     */
    public function corbeille(string $search = ''): array
    {
        return DB::table('livreur_tarifs')
            ->join('villes', 'villes.id', '=', 'livreur_tarifs.ville_id')
            ->join('users', 'users.id', '=', 'livreur_tarifs.livreur_id')
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->whereNotNull('livreur_tarifs.deleted_at')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('users.nom_complet', 'like', '%'.$search.'%')
                ->orWhere('villes.name_fr', 'like', '%'.$search.'%')))
            ->orderByDesc('livreur_tarifs.deleted_at')
            ->get([
                'livreur_tarifs.id', 'livreur_tarifs.livreur_id', 'users.nom_complet as livreur_nom', 'users.deleted_at as livreur_archive',
                'villes.id as ville_id', 'villes.name_fr as ville_nom', 'zones.name as zone_nom',
                'livreur_tarifs.commission_livraison', 'livreur_tarifs.commission_refus', 'livreur_tarifs.deleted_at', 'livreur_tarifs.updated_at',
            ])
            ->map(fn ($l) => $this->ligne($l) + [
                'livreur_id' => (int) $l->livreur_id,
                'livreur_nom' => (string) $l->livreur_nom,
                'livreur_archive' => $l->livreur_archive !== null,
                'deleted_at' => $l->deleted_at,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function ligne(object $l): array
    {
        $livraison = Montants::lire(['v' => $l->commission_livraison], ['v' => 'v'])['v'];

        return [
            'id' => (int) $l->id,
            'ville_id' => (int) $l->ville_id,
            'ville_nom' => (string) $l->ville_nom,
            'zone_nom' => (string) ($l->zone_nom ?? 'Villes sans zone'),
            'livraison' => $livraison,
            'refus' => Montants::lire(['v' => $l->commission_refus], ['v' => 'v'])['v'],
            'zero' => (float) $livraison === 0.0,
            'updated_at' => $l->updated_at,
        ];
    }
}
