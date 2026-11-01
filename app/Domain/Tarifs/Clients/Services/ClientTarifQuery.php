<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Clients\Services;

use App\Domain\Tarifs\Commun\Support\TarifResolu;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Lectures de l'écran « Tarifs clients » : Client → Zone → Ville.
 *
 * Adapté de WinningExpress (ClientTarifQuery). Un client ne voit QUE les villes où
 * une règle ACTIVE existe ; le tarif standard est affiché en regard. Toutes les
 * agrégations sont faites en SQL (une requête par besoin, jamais une par client
 * ou par ville).
 */
final class ClientTarifQuery
{
    /**
     * Agrégat par client des tarifs actifs (sous-requête réutilisée).
     */
    private function agregatParClient(): QueryBuilder
    {
        return DB::table('client_tarifs')
            ->join('villes', 'villes.id', '=', 'client_tarifs.ville_id')
            ->whereNull('client_tarifs.deleted_at')
            ->whereNull('villes.deleted_at')
            ->groupBy('client_tarifs.client_id')
            ->selectRaw('client_tarifs.client_id as client_id')
            ->selectRaw('COUNT(*) as nb_tarifs')
            ->selectRaw('COUNT(DISTINCT villes.zone_id) as nb_zones')
            ->selectRaw('MIN(client_tarifs.frais_livraison) as livraison_min')
            ->selectRaw('MAX(client_tarifs.frais_livraison) as livraison_max');
    }

    /**
     * Clients ayant au moins un tarif actif, filtrés et paginés côté serveur.
     *
     * @param  array{search?: ?string, ville?: ?string, zone_id?: ?int, client_id?: ?int}  $filtres
     */
    public function clients(array $filtres, int $parPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filtres['search'] ?? ''));
        $ville = trim((string) ($filtres['ville'] ?? ''));
        $zoneId = $filtres['zone_id'] ?? null;
        $clientId = $filtres['client_id'] ?? null;

        // Filtres portant sur les LIGNES (ville, zone) : le client doit avoir au moins une ligne qui y répond.
        $ligneFiltree = function ($sub) use ($ville, $zoneId) {
            $sub->from('client_tarifs')
                ->join('villes', 'villes.id', '=', 'client_tarifs.ville_id')
                ->whereColumn('client_tarifs.client_id', 'users.id')
                ->whereNull('client_tarifs.deleted_at')
                ->whereNull('villes.deleted_at')
                ->when($ville !== '', fn ($q) => $q->where('villes.name_fr', 'like', '%'.$ville.'%'))
                ->when($zoneId !== null, fn ($q) => $q->where('villes.zone_id', $zoneId));
        };

        return User::query()
            ->joinSub($this->agregatParClient(), 'agg', 'agg.client_id', '=', 'users.id')
            ->when($ville !== '' || $zoneId !== null, fn ($q) => $q->whereExists($ligneFiltree))
            ->when($clientId !== null, fn ($q) => $q->whereKey($clientId))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('users.nom_complet', 'like', '%'.$search.'%')
                ->orWhere('users.store_name', 'like', '%'.$search.'%')
                ->orWhere('users.email', 'like', '%'.$search.'%')
                ->orWhereExists(fn ($b) => $b->from('businesses')
                    ->whereColumn('businesses.user_id', 'users.id')
                    ->where('businesses.name', 'like', '%'.$search.'%'))))
            ->with(['businesses:id,user_id,name'])
            ->orderBy('users.nom_complet')
            ->select([
                'users.id', 'users.nom_complet', 'users.email', 'users.telephone', 'users.store_name',
                'agg.nb_tarifs', 'agg.nb_zones', 'agg.livraison_min', 'agg.livraison_max',
            ])
            ->paginate($parPage)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => (int) $u->id,
                'nom' => (string) $u->nom_complet,
                'email' => $u->email,
                'telephone' => $u->telephone,
                'enseignes' => $u->businesses->pluck('name')->filter()->values()->all()
                    ?: array_values(array_filter([$u->store_name])),
                'nb_tarifs' => (int) $u->nb_tarifs,
                'nb_zones' => (int) $u->nb_zones,
                'livraison_min' => TarifResolu::montant($u->livraison_min),
                'livraison_max' => TarifResolu::montant($u->livraison_max),
            ]);
    }

    /**
     * Indicateurs globaux en UNE requête (règles actives, villes actives).
     *
     * « Tarif moyen » = moyenne arithmétique des frais de livraison des règles actives
     * (chaque règle client × ville pèse 1, sans pondération par volume de colis).
     *
     * @return array{clients: int, tarifs: int, villes: int, livraison_min: ?string, livraison_max: ?string, livraison_moyenne: ?string, gratuits: int, corbeille: int}
     */
    public function statistiques(): array
    {
        // Vue opérationnelle : ni villes ni clients archivés (leurs règles restent conservées en base).
        $s = DB::table('client_tarifs')
            ->join('villes', 'villes.id', '=', 'client_tarifs.ville_id')
            ->join('users', 'users.id', '=', 'client_tarifs.client_id')
            ->whereNull('client_tarifs.deleted_at')
            ->whereNull('villes.deleted_at')
            ->whereNull('users.deleted_at')
            ->selectRaw('COUNT(DISTINCT client_tarifs.client_id) as clients')
            ->selectRaw('COUNT(*) as tarifs')
            ->selectRaw('COUNT(DISTINCT client_tarifs.ville_id) as villes')
            ->selectRaw('MIN(client_tarifs.frais_livraison) as mn')
            ->selectRaw('MAX(client_tarifs.frais_livraison) as mx')
            ->selectRaw('AVG(client_tarifs.frais_livraison) as moy')
            ->selectRaw('SUM(CASE WHEN client_tarifs.frais_livraison = 0 THEN 1 ELSE 0 END) as gratuits')
            ->first();

        $tarifs = (int) ($s->tarifs ?? 0);

        return [
            'clients' => (int) ($s->clients ?? 0),
            'tarifs' => $tarifs,
            'villes' => (int) ($s->villes ?? 0),
            'livraison_min' => $tarifs > 0 ? TarifResolu::montant($s->mn) : null,
            'livraison_max' => $tarifs > 0 ? TarifResolu::montant($s->mx) : null,
            'livraison_moyenne' => $tarifs > 0 ? TarifResolu::montant($s->moy) : null,
            'gratuits' => (int) ($s->gratuits ?? 0),
            'corbeille' => DB::table('client_tarifs')->whereNotNull('deleted_at')->count(),
        ];
    }

    /**
     * Corbeille : tarifs négociés supprimés (soft delete), tous clients, à plat. Une requête.
     *
     * @return list<array<string, mixed>>
     */
    public function corbeille(string $search = ''): array
    {
        return DB::table('client_tarifs')
            ->join('villes', 'villes.id', '=', 'client_tarifs.ville_id')
            ->join('users', 'users.id', '=', 'client_tarifs.client_id')
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->whereNotNull('client_tarifs.deleted_at')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('users.nom_complet', 'like', '%'.$search.'%')
                ->orWhere('villes.name_fr', 'like', '%'.$search.'%')))
            ->orderByDesc('client_tarifs.deleted_at')
            ->orderByDesc('client_tarifs.id')
            ->get([
                'client_tarifs.id', 'users.nom_complet', 'users.deleted_at as client_archive', 'villes.name_fr', 'zones.name as zone_nom',
                'client_tarifs.frais_livraison', 'client_tarifs.frais_refus', 'client_tarifs.frais_retour', 'client_tarifs.deleted_at',
            ])
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'titulaire' => (string) $l->nom_complet,
                'titulaire_archive' => $l->client_archive !== null,
                'ville_nom' => (string) $l->name_fr,
                'zone_nom' => (string) ($l->zone_nom ?? 'Villes sans zone'),
                'montants' => [
                    TarifResolu::montant($l->frais_livraison),
                    TarifResolu::montant($l->frais_refus),
                    TarifResolu::montant($l->frais_retour),
                ],
                'deleted_at' => $l->deleted_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Grille d'UN client : ses villes tarifées (actives) groupées par zone, standard en regard.
     * Une seule requête (jointure gauche sur standard_tarifs).
     *
     * La comparaison au standard est calculée ICI (jamais dans React).
     *
     * @return list<array{zone_id: ?int, zone_nom: string, zone_code: ?string, lignes: list<array<string, mixed>>}>
     */
    public function grilleDuClient(int $clientId): array
    {
        $lignes = DB::table('client_tarifs')
            ->join('villes', 'villes.id', '=', 'client_tarifs.ville_id')
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->leftJoin('standard_tarifs', 'standard_tarifs.ville_id', '=', 'villes.id')
            ->where('client_tarifs.client_id', $clientId)
            ->whereNull('client_tarifs.deleted_at')
            ->whereNull('villes.deleted_at')
            ->orderByRaw('CASE WHEN zones.name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('zones.name')
            ->orderBy('villes.name_fr')
            ->get([
                'zones.id as zone_id',
                'zones.name as zone_nom',
                'zones.code as zone_code',
                'villes.id as ville_id',
                'villes.name_fr as ville_nom',
                'client_tarifs.id as tarif_id',
                'client_tarifs.frais_livraison',
                'client_tarifs.frais_refus',
                'client_tarifs.frais_retour',
                'client_tarifs.updated_at',
                'standard_tarifs.frais_livraison as standard_livraison',
            ]);

        $grille = [];

        foreach ($lignes as $l) {
            $cle = $l->zone_id === null ? 'sans-zone' : 'z'.$l->zone_id;
            $livraison = TarifResolu::montant($l->frais_livraison);
            $standard = $l->standard_livraison === null ? null : TarifResolu::montant($l->standard_livraison);

            $grille[$cle] ??= [
                'zone_id' => $l->zone_id === null ? null : (int) $l->zone_id,
                'zone_nom' => (string) ($l->zone_nom ?? 'Villes sans zone'),
                'zone_code' => $l->zone_code,
                'lignes' => [],
            ];

            $grille[$cle]['lignes'][] = [
                'id' => (int) $l->tarif_id,
                'ville_id' => (int) $l->ville_id,
                'ville_nom' => (string) $l->ville_nom,
                'livraison' => $livraison,
                'refus' => TarifResolu::montant($l->frais_refus),
                'retour' => TarifResolu::montant($l->frais_retour),
                'standard' => $standard,
                'comparaison' => self::comparer($livraison, $standard),
                'gratuit' => (float) $livraison === 0.0,
                'updated_at' => $l->updated_at,
            ];
        }

        return array_values($grille);
    }

    /** superieur | inferieur | egal | indisponible (aucun tarif standard pour la ville). */
    public static function comparer(string $negocie, ?string $standard): string
    {
        if ($standard === null) {
            return 'indisponible';
        }

        // Comparaison en centimes entiers (pas de flottant, pas de dépendance bcmath).
        $centimes = fn (string $m): int => (int) round(((float) $m) * 100);

        return match ($centimes($negocie) <=> $centimes($standard)) {
            1 => 'superieur',
            -1 => 'inferieur',
            default => 'egal',
        };
    }
}
