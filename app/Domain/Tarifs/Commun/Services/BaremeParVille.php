<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Commun\Services;

use App\Domain\Tarifs\Commun\Support\Montants;
use App\Models\Ville;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grille d'un barème « une ligne par ville » : lecture paginée Zone → Ville → Tarif,
 * indicateurs, aperçu et écritures (lot inline, application à plusieurs villes ou à une zone).
 *
 * Adapté de WinningExpress TarifStandardGrilleService (lectures groupées, mutations par lot
 * transactionnelles, montants ≥ 0 à 2 décimales). Deux barèmes l'utilisent, SANS jamais
 * partager de table : {@see \App\Domain\Tarifs\Standard\Services\StandardTarifGrille}
 * (frais facturés au marchand) et {@see \App\Domain\Tarifs\Livreurs\Services\LivreurStandardGrille}
 * (commission standard versée au livreur).
 *
 * Garanties :
 *  - seules les villes ACTIVES sont proposées et écrites (une ville archivée garde sa ligne) ;
 *  - une ligne existante n'est jamais écrasée sans choix explicite (`existants`) ;
 *  - validation complète AVANT écriture, erreurs rendues PAR LIGNE, puis une transaction ;
 *  - une ligne inchangée n'est pas réécrite (aucun écrasement de valeurs non modifiées) ;
 *  - aucune écriture sur `commands` : les colis gardent leur snapshot.
 */
abstract class BaremeParVille
{
    public const EXISTANTS_IGNORER = 'ignorer';
    public const EXISTANTS_METTRE_A_JOUR = 'mettre_a_jour';

    public function __construct(protected readonly FeeResolutionService $fees) {}

    /** Table du barème (colonne `ville_id` UNIQUE). */
    abstract protected function table(): string;

    /** @return class-string<Model> */
    abstract protected function modele(): string;

    /** @return array<string, string> clé métier => colonne de montant */
    abstract protected function colonnes(): array;

    /** Colonne texte libre facultative (délai) ; null si le barème n'en a pas. */
    protected function colonneDelai(): ?string
    {
        return null;
    }

    /** Point d'extension : colonnes supplémentaires de la grille (une seule requête). */
    protected function enrichir(QueryBuilder $requete): void {}

    /** @return array<string, mixed> */
    protected function extra(object $ligne): array
    {
        return [];
    }

    /**
     * Villes actives (avec ou sans ligne de barème), filtrées et paginées côté serveur.
     *
     * @param  array{search?: string, zone_id?: ?int, statut?: string}  $filtres  statut : tous | tarifees | sans
     */
    public function villes(array $filtres, int $parPage = 50): LengthAwarePaginator
    {
        $t = $this->table();
        $search = trim((string) ($filtres['search'] ?? ''));
        $zoneId = $filtres['zone_id'] ?? null;
        $statut = (string) ($filtres['statut'] ?? 'tous');

        $colonnes = array_map(fn (string $c) => "$t.$c", array_values($this->colonnes()));
        if ($this->colonneDelai() !== null) {
            $colonnes[] = "$t.".$this->colonneDelai();
        }

        $requete = DB::table('villes')
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->leftJoin($t, "$t.ville_id", '=', 'villes.id')
            ->whereNull('villes.deleted_at')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('villes.name_fr', 'like', '%'.$search.'%')
                ->orWhere('villes.name_ar', 'like', '%'.$search.'%')))
            ->when($zoneId !== null, fn ($q) => $q->where('villes.zone_id', $zoneId))
            ->when($statut === 'tarifees', fn ($q) => $q->whereNotNull("$t.id"))
            ->when($statut === 'sans', fn ($q) => $q->whereNull("$t.id"))
            ->orderByRaw('CASE WHEN zones.name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('zones.name')
            ->orderBy('villes.name_fr')
            ->orderBy('villes.id')
            ->select(array_merge([
                'villes.id as ville_id', 'villes.name_fr', 'villes.name_ar', 'villes.zone_id',
                'zones.name as zone_nom', 'zones.code as zone_code', "$t.id as tarif_id",
            ], $colonnes));

        $this->enrichir($requete);

        return $requete->paginate($parPage)->withQueryString()->through(function (object $l): array {
            $tarif = null;
            if ($l->tarif_id !== null) {
                $tarif = ['id' => (int) $l->tarif_id];
                foreach ($this->colonnes() as $cle => $colonne) {
                    $tarif[$cle] = Montants::lire([$cle => $l->{$colonne}], [$cle => $cle])[$cle];
                }
                if ($this->colonneDelai() !== null) {
                    $tarif['delai'] = $l->{$this->colonneDelai()};
                }
            }

            return [
                'ville_id' => (int) $l->ville_id,
                'ville_nom' => (string) $l->name_fr,
                'ville_ar' => $l->name_ar,
                'zone_id' => $l->zone_id === null ? null : (int) $l->zone_id,
                'zone_nom' => (string) ($l->zone_nom ?? 'Villes sans zone'),
                'zone_code' => $l->zone_code,
                'tarif' => $tarif,
            ] + $this->extra($l);
        });
    }

    /**
     * Indicateurs calculés en base (villes ACTIVES).
     *
     * @return array{villes: int, tarifees: int, sans_tarif: int, moyenne: ?string, gratuites: int}
     */
    public function statistiques(): array
    {
        $t = $this->table();
        $livraison = array_values($this->colonnes())[0];

        $villes = Ville::query()->count();
        $s = DB::table($t)
            ->join('villes', 'villes.id', '=', "$t.ville_id")
            ->whereNull('villes.deleted_at')
            ->selectRaw('COUNT(*) as n')
            ->selectRaw("AVG($t.$livraison) as moy")
            ->selectRaw("SUM(CASE WHEN $t.$livraison = 0 THEN 1 ELSE 0 END) as gratuites")
            ->first();

        $tarifees = (int) ($s->n ?? 0);

        return [
            'villes' => $villes,
            'tarifees' => $tarifees,
            'sans_tarif' => max($villes - $tarifees, 0),
            'moyenne' => $tarifees > 0 ? number_format((float) $s->moy, 2, '.', '') : null,
            'gratuites' => (int) ($s->gratuites ?? 0),
        ];
    }

    /**
     * Villes visées par une application (villes choisies ou zone moins exclusions), sans écrire.
     *
     * @param  list<int>  $villeIds
     * @param  list<int>  $exclusions
     * @return list<array<string, mixed>>
     */
    public function apercu(array $villeIds, ?int $zoneId, array $exclusions = []): array
    {
        $cibles = $this->cibles($villeIds, $zoneId, $exclusions);
        $existants = $this->modele()::query()->whereIn('ville_id', $cibles)->get()->keyBy('ville_id');
        $noms = Ville::query()->whereKey($cibles)->pluck('name_fr', 'id');

        return array_map(function (int $villeId) use ($existants, $noms): array {
            $ligne = $existants->get($villeId);
            $actuel = null;
            if ($ligne !== null) {
                $actuel = [];
                foreach ($this->colonnes() as $cle => $colonne) {
                    $actuel[$cle] = number_format((float) $ligne->{$colonne}, 2, '.', '');
                }
            }

            return [
                'ville_id' => $villeId,
                'ville_nom' => (string) ($noms[$villeId] ?? '#'.$villeId),
                'statut' => $ligne === null ? 'nouveau' : 'existant',
                'actuel' => $actuel,
            ];
        }, $cibles);
    }

    /**
     * Applique un même tarif à plusieurs villes (ou à une zone, moins les exclusions).
     *
     * @param  list<int>  $villeIds
     * @param  list<int>  $exclusions
     * @param  array<string, mixed>  $saisie  montants (clés métier) + `delai` éventuel
     * @return array{crees: int, mises_a_jour: int, ignorees: int, villes: int}
     */
    public function appliquer(array $villeIds, ?int $zoneId, array $exclusions, array $saisie, string $existants = self::EXISTANTS_IGNORER): array
    {
        if (! in_array($existants, [self::EXISTANTS_IGNORER, self::EXISTANTS_METTRE_A_JOUR], true)) {
            throw ValidationException::withMessages(['existants' => 'Choix de traitement des tarifs existants invalide.']);
        }

        $valeurs = Montants::lire($saisie, $this->colonnes()) + $this->delai($saisie, 'delai');
        $cibles = $this->cibles($villeIds, $zoneId, $exclusions);

        if ($cibles === []) {
            throw ValidationException::withMessages(['ville_ids' => 'Aucune ville active ciblée.']);
        }

        $resultat = DB::transaction(function () use ($cibles, $valeurs, $existants): array {
            $lignes = $this->modele()::query()->whereIn('ville_id', $cibles)->lockForUpdate()->get()->keyBy('ville_id');
            $r = ['crees' => 0, 'mises_a_jour' => 0, 'ignorees' => 0, 'villes' => count($cibles)];

            foreach ($cibles as $villeId) {
                $ligne = $lignes->get($villeId);

                if ($ligne === null) {
                    $this->modele()::create($valeurs + ['ville_id' => $villeId]);
                    $r['crees']++;
                } elseif ($existants === self::EXISTANTS_METTRE_A_JOUR) {
                    $ligne->fill($valeurs);
                    if ($ligne->isDirty()) {
                        $ligne->save();
                    }
                    $r['mises_a_jour']++;
                } else {
                    $r['ignorees']++;
                }
            }

            return $r;
        });

        $this->fees->oublier();

        return $resultat;
    }

    /**
     * Enregistrement en lot de l'édition inline : une ligne par ville (créée si absente).
     * Toutes les lignes sont validées d'abord ; les erreurs sont rendues par ligne
     * (`lignes.{i}.{champ}`) et rien n'est écrit tant qu'une ligne est invalide.
     *
     * @param  list<array<string, mixed>>  $lignes
     * @return array{crees: int, modifiees: int, inchangees: int}
     */
    public function enregistrerLot(array $lignes): array
    {
        if ($lignes === []) {
            throw ValidationException::withMessages(['lignes' => 'Aucune modification à enregistrer.']);
        }

        $parVille = [];
        $erreurs = [];

        foreach (array_values($lignes) as $i => $ligne) {
            $villeId = (int) ($ligne['ville_id'] ?? 0);

            if ($villeId <= 0 || isset($parVille[$villeId])) {
                $erreurs["lignes.$i.ville_id"] = 'Ville invalide ou en double.';

                continue;
            }

            try {
                $parVille[$villeId] = ['index' => $i, 'valeurs' => Montants::lire($ligne, $this->colonnes(), "lignes.$i.") + $this->delai($ligne, "lignes.$i.delai")];
            } catch (ValidationException $e) {
                $erreurs += array_map(fn ($m) => $m[0], $e->errors());
            }
        }

        $actives = Ville::query()->whereKey(array_keys($parVille))->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (array_diff(array_keys($parVille), $actives) as $villeId) {
            $erreurs['lignes.'.$parVille[$villeId]['index'].'.ville_id'] = 'Ville inexistante ou archivée.';
        }

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        $resultat = DB::transaction(function () use ($parVille): array {
            $existants = $this->modele()::query()->whereIn('ville_id', array_keys($parVille))->lockForUpdate()->get()->keyBy('ville_id');
            $r = ['crees' => 0, 'modifiees' => 0, 'inchangees' => 0];

            foreach ($parVille as $villeId => ['valeurs' => $valeurs]) {
                $ligne = $existants->get($villeId);

                if ($ligne === null) {
                    $this->modele()::create($valeurs + ['ville_id' => $villeId]);
                    $r['crees']++;

                    continue;
                }

                $ligne->fill($valeurs);
                if ($ligne->isDirty()) {
                    $ligne->save();
                    $r['modifiees']++;
                } else {
                    $r['inchangees']++;
                }
            }

            return $r;
        });

        $this->fees->oublier();

        return $resultat;
    }

    /**
     * @param  list<int>  $villeIds
     * @param  list<int>  $exclusions
     * @return list<int> villes ACTIVES ciblées
     */
    protected function cibles(array $villeIds, ?int $zoneId, array $exclusions): array
    {
        $ids = $zoneId !== null
            ? Ville::query()->where('zone_id', $zoneId)->orderBy('name_fr')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : array_values(array_unique(array_filter(array_map('intval', $villeIds), fn (int $id) => $id > 0)));

        $ids = array_values(array_diff($ids, array_map('intval', $exclusions)));

        if ($zoneId === null && $ids !== []) {
            $actives = Ville::query()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $inconnues = array_values(array_diff($ids, $actives));
            if ($inconnues !== []) {
                throw ValidationException::withMessages(['ville_ids' => 'Villes inexistantes ou archivées : '.implode(', ', $inconnues).'.']);
            }
        }

        return $ids;
    }

    /** @return array<string, ?string> */
    private function delai(array $source, string $cle): array
    {
        $colonne = $this->colonneDelai();
        if ($colonne === null) {
            return [];
        }

        $valeur = trim((string) ($source['delai'] ?? ''));
        if (mb_strlen($valeur) > 50) {
            throw ValidationException::withMessages([$cle => 'Délai : 50 caractères maximum.']);
        }

        return [$colonne => $valeur === '' ? null : $valeur];
    }
}
