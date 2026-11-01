<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Livreurs\Services;

use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Domain\Tarifs\Commun\Support\Montants;
use App\Models\LivreurStandardTarif;
use App\Models\LivreurTarif;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Écritures des commissions SPÉCIFIQUES des livreurs (point unique).
 *
 * Adapté de WinningExpress LivreurTarifEcriture, avec les garanties de ClientTarifEcriture :
 *  - une ligne par couple (livreur, ville), corbeille comprise : restauration, jamais doublon ;
 *  - une commission existante n'est jamais écrasée sans choix explicite (`existants`) ;
 *  - validation complète avant écriture, puis transaction ;
 *  - aucune écriture sur `commands` : les colis gardent leur commission figée.
 */
final class LivreurTarifEcriture
{
    public const EXISTANTS_IGNORER = 'ignorer';
    public const EXISTANTS_METTRE_A_JOUR = 'mettre_a_jour';

    private const COLONNES = ['livraison' => 'commission_livraison', 'refus' => 'commission_refus'];

    public function __construct(private readonly FeeResolutionService $fees) {}

    /**
     * État de chaque ville visée pour ce livreur, sans écrire : nouveau / actif / supprimé,
     * montants actuels et commission standard en regard.
     *
     * @param  list<int>  $villeIds
     * @return list<array<string, mixed>>
     */
    public function apercu(int $livreurId, array $villeIds, ?int $zoneId = null): array
    {
        $cibles = $this->cibles($villeIds, $zoneId);
        $lignes = LivreurTarif::withTrashed()->where('livreur_id', $livreurId)->whereIn('ville_id', $cibles)->get()->keyBy('ville_id');
        $standards = LivreurStandardTarif::query()->whereIn('ville_id', $cibles)->get()->keyBy('ville_id');
        $noms = Ville::query()->whereKey($cibles)->pluck('name_fr', 'id');

        return array_map(function (int $villeId) use ($lignes, $standards, $noms): array {
            $l = $lignes->get($villeId);
            $s = $standards->get($villeId);

            return [
                'ville_id' => $villeId,
                'ville_nom' => (string) ($noms[$villeId] ?? '#'.$villeId),
                'statut' => $l === null ? 'nouveau' : ($l->trashed() ? 'supprime' : 'actif'),
                'livraison' => $l?->commission_livraison,
                'refus' => $l?->commission_refus,
                'standard' => $s?->commission_livraison,
            ];
        }, $cibles);
    }

    /**
     * Affecte une commission spécifique à un livreur sur des villes (ou une zone).
     *
     * @param  list<int>  $villeIds
     * @param  array{livraison: mixed, refus: mixed}  $montants
     * @return array{crees: int, mises_a_jour: int, restaurees: int, ignorees: int, villes: int}
     */
    public function ajouter(int $livreurId, array $villeIds, ?int $zoneId, array $montants, string $existants = self::EXISTANTS_IGNORER, bool $restaurer = true): array
    {
        $this->assurerLivreur($livreurId);
        $valeurs = Montants::lire($montants, self::COLONNES);

        if (! in_array($existants, [self::EXISTANTS_IGNORER, self::EXISTANTS_METTRE_A_JOUR], true)) {
            throw ValidationException::withMessages(['existants' => 'Choix de traitement des commissions existantes invalide.']);
        }

        $cibles = $this->cibles($villeIds, $zoneId);
        if ($cibles === []) {
            throw ValidationException::withMessages(['ville_ids' => 'Sélectionnez au moins une ville active.']);
        }

        $r = DB::transaction(function () use ($livreurId, $cibles, $valeurs, $existants, $restaurer): array {
            $lignes = LivreurTarif::withTrashed()->where('livreur_id', $livreurId)->whereIn('ville_id', $cibles)->lockForUpdate()->get()->keyBy('ville_id');
            $r = ['crees' => 0, 'mises_a_jour' => 0, 'restaurees' => 0, 'ignorees' => 0, 'villes' => count($cibles)];

            foreach ($cibles as $villeId) {
                $ligne = $lignes->get($villeId);

                if ($ligne === null) {
                    LivreurTarif::create($valeurs + ['livreur_id' => $livreurId, 'ville_id' => $villeId]);
                    $r['crees']++;
                } elseif ($ligne->trashed()) {
                    if (! $restaurer) {
                        $r['ignorees']++;

                        continue;
                    }
                    $ligne->restore();
                    $ligne->update($valeurs);
                    $r['restaurees']++;
                } elseif ($existants === self::EXISTANTS_METTRE_A_JOUR) {
                    $ligne->update($valeurs);
                    $r['mises_a_jour']++;
                } else {
                    $r['ignorees']++;
                }
            }

            return $r;
        });

        $this->fees->oublier();

        return $r;
    }

    /**
     * Modifie un lot de commissions ACTIVES d'un livreur (édition individuelle = lot d'une ligne).
     *
     * @param  list<array{id: int, livraison: mixed, refus: mixed}>  $lignes
     */
    public function modifierLot(int $livreurId, array $lignes): int
    {
        $parId = [];
        foreach (array_values($lignes) as $i => $ligne) {
            $id = (int) ($ligne['id'] ?? 0);
            if ($id <= 0 || isset($parId[$id])) {
                throw ValidationException::withMessages(["lignes.$i.id" => 'Ligne invalide ou dupliquée.']);
            }
            $parId[$id] = Montants::lire($ligne, self::COLONNES, "lignes.$i.");
        }

        if ($parId === []) {
            throw ValidationException::withMessages(['lignes' => 'Aucune ligne à modifier.']);
        }

        $n = DB::transaction(function () use ($livreurId, $parId): int {
            $tarifs = LivreurTarif::query()->whereKey(array_keys($parId))->where('livreur_id', $livreurId)->lockForUpdate()->get();

            if ($tarifs->count() !== count($parId)) {
                throw ValidationException::withMessages(['lignes' => 'Certaines commissions n\'existent plus ou n\'appartiennent pas à ce livreur. Rechargez la grille.']);
            }

            $n = 0;
            foreach ($tarifs as $t) {
                $t->fill($parId[$t->id]);
                if ($t->isDirty()) {
                    $t->save();
                    $n++;
                }
            }

            return $n;
        });

        $this->fees->oublier();

        return $n;
    }

    /** Suppression LOGIQUE : la ville repasse au standard livreur pour les FUTURS colis. */
    public function supprimerLot(int $livreurId, array $ids): int
    {
        return $this->basculer($livreurId, $ids, supprimer: true);
    }

    /** Restauration contrôlée de commissions supprimées (valeurs d'origine conservées). */
    public function restaurerLot(int $livreurId, array $ids): int
    {
        return $this->basculer($livreurId, $ids, supprimer: false);
    }

    private function basculer(int $livreurId, array $ids, bool $supprimer): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
        if ($ids === []) {
            throw ValidationException::withMessages(['ids' => 'Aucune commission sélectionnée.']);
        }

        $n = DB::transaction(function () use ($livreurId, $ids, $supprimer): int {
            $q = $supprimer ? LivreurTarif::query() : LivreurTarif::onlyTrashed();
            $tarifs = $q->whereKey($ids)->where('livreur_id', $livreurId)->lockForUpdate()->get();

            if ($tarifs->count() !== count($ids)) {
                throw ValidationException::withMessages(['ids' => 'Certaines commissions ont changé d\'état ou n\'appartiennent pas à ce livreur. Rechargez la grille.']);
            }

            $supprimer ? $tarifs->each->delete() : $tarifs->each->restore();

            return $tarifs->count();
        });

        $this->fees->oublier();

        return $n;
    }

    private function assurerLivreur(int $livreurId): void
    {
        if ($livreurId <= 0 || ! User::drivers()->whereKey($livreurId)->exists()) {
            throw ValidationException::withMessages(['livreur_id' => 'Ce compte n\'est pas un livreur actif.']);
        }
    }

    /** @return list<int> villes ACTIVES ciblées */
    private function cibles(array $villeIds, ?int $zoneId): array
    {
        if ($zoneId !== null) {
            return Ville::query()->where('zone_id', $zoneId)->orderBy('name_fr')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $villeIds), fn (int $id) => $id > 0)));
        $actives = Ville::query()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $inconnues = array_values(array_diff($ids, $actives));

        if ($inconnues !== []) {
            throw ValidationException::withMessages(['ville_ids' => 'Villes inexistantes ou archivées : '.implode(', ', $inconnues).'.']);
        }

        return $ids;
    }
}
