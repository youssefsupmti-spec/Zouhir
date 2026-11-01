<?php

declare(strict_types=1);

namespace App\Application\Tarifs\Backfill;

use App\Domain\Villes\Support\VilleRef;
use App\Domain\Villes\Support\VilleResolution;
use App\Models\LivreurTarif;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rapprochement des commissions livreurs legacy (`stockout.shippingfees`) avec Zouhir.
 *
 *  PASS 1 — règles SPÉCIFIQUES (dlm > 0)  → `livreur_tarifs` (actives ET corbeille, comme le legacy) ;
 *  PASS 2 — règles dlm = 0               : ANALYSÉES uniquement (villes, montants, doublons, conflits),
 *                                           JAMAIS écrites : Zouhir n'a pas de barème standard des
 *                                           commissions (décision du propriétaire, comme WinningExpress) ;
 *  PASS 3 — règles ARCHIVÉES (trash ≠ 1)  : spécifiques reprises EN CORBEILLE, dlm = 0 non repris.
 *
 * Même politique que le backfill des tarifs clients et des colis : villes via {@see VilleRef}
 * (mode historique, active d'abord puis plus petit id), identité = même id ET (même email OU
 * même nom), comptes archivés inclus. Doublons identiques fusionnés (identifiants legacy
 * conservés), montants différents JAMAIS dédupliqués ni migrés. Ne touche jamais `commands`.
 */
final class RapprochementLivreurFees
{
    public const MIGRABLES = ['a_creer', 'a_creer_corbeille', 'a_restaurer'];

    public const PORTEURS = ['a_creer', 'a_creer_corbeille', 'a_restaurer', 'identique', 'conflit_valeur', 'conflit_etat'];

    public function __construct(private readonly VilleRef $villes) {}

    /**
     * @return array{specifiques: list<array<string, mixed>>, standards: list<array<string, mixed>>, compte_specifiques: array<string, int>, compte_standards: array<string, int>, livreurs: array<string, int>, villes: array<string, int>, synthese: array<string, mixed>}
     */
    public function analyser(ConnectionInterface $legacy): array
    {
        $schema = $legacy->getSchemaBuilder();
        if (! $schema->hasTable('shippingfees')) {
            throw new RuntimeException('Table legacy `shippingfees` introuvable sur cette connexion.');
        }

        $colonnes = array_map('strtolower', $schema->getColumnListing('shippingfees'));
        foreach (['id', 'dlm', 'city', 'deliveredfees', 'trash'] as $requise) {
            if (! in_array($requise, $colonnes, true)) {
                throw new RuntimeException("Colonne legacy `shippingfees.$requise` absente : rapprochement impossible.");
            }
        }
        $aRefus = in_array('refusedfees', $colonnes, true);

        $rows = $legacy->table('shippingfees')->orderBy('id')
            ->get(array_values(array_filter(['id', 'dlm', 'city', 'deliveredfees', 'trash', $aRefus ? 'refusedfees' : null])));

        $specRows = $rows->filter(fn ($r) => (int) $r->dlm > 0)->values();
        $stdRows = $rows->filter(fn ($r) => (int) $r->dlm <= 0)->values();
        $identites = $this->identitesLivreurs($legacy, $specRows->pluck('dlm')->map(fn ($d) => (int) $d)->unique()->values()->all());

        $specifiques = $this->passeSpecifiques($specRows, $identites, $aRefus);
        $standards = $this->passeStandards($stdRows, $aRefus);

        return [
            'colonne_refus' => $aRefus,
            'specifiques' => $specifiques,
            'standards' => $standards,
            'compte_specifiques' => $this->compter($specifiques, 'statut'),
            'compte_standards' => $this->compter($standards, 'statut'),
            'livreurs' => $this->compter($specifiques, 'identite'),
            'villes' => $this->compter(array_merge($specifiques, $standards), 'ville_methode'),
            'synthese' => $this->synthese($rows->all(), $specifiques, $standards, $identites),
        ];
    }

    /** PASS 1 (+ PASS 3 pour les spécifiques archivées). */
    private function passeSpecifiques(iterable $rows, array $identites, bool $aRefus): array
    {
        $lignes = [];
        $porteur = [];

        foreach ($rows as $row) {
            $ligne = $this->base($row) + ['livreur_id' => null, 'livreur_archive' => false, 'identite' => null];

            $id = $identites[(int) $row->dlm] ?? ['statut' => 'livreur_introuvable', 'livreur_id' => null, 'methode' => null];
            $ligne['identite'] = $id['methode'];
            if ($id['statut'] !== 'ok') {
                $lignes[] = ['statut' => $id['statut'], 'detail' => $id['detail'] ?? null] + $ligne;

                continue;
            }
            $ligne['livreur_id'] = $id['livreur_id'];
            $ligne['livreur_archive'] = $id['archive'];

            $ligne = $this->villeEtMontants($row, $ligne, $aRefus);
            if ($ligne['statut'] !== null) {
                $lignes[] = $ligne;

                continue;
            }

            $cle = $ligne['livreur_id'].'|'.$ligne['ville_id'];
            if (! isset($porteur[$cle])) {
                $porteur[$cle] = count($lignes);
                $lignes[] = $ligne;

                continue;
            }

            $p = $porteur[$cle];
            if ($lignes[$p]['corbeille'] !== $ligne['corbeille']) {
                // Une seule règle par couple : l'ACTIVE l'emporte sur celle en corbeille.
                if ($ligne['corbeille']) {
                    $lignes[] = ['statut' => 'doublon_corbeille', 'detail' => 'règle active retenue : legacy #'.$lignes[$p]['legacy_id']] + $ligne;

                    continue;
                }
                $lignes[$p]['statut'] = 'doublon_corbeille';
                $lignes[$p]['detail'] = 'règle active retenue : legacy #'.$ligne['legacy_id'];
                $porteur[$cle] = count($lignes);
                $lignes[] = $ligne;

                continue;
            }

            if ($this->memes($lignes[$p], $ligne)) {
                $lignes[$p]['legacy_ids'][] = $ligne['legacy_id'];
                $lignes[] = ['statut' => 'doublon_identique', 'detail' => 'fusionnée dans legacy #'.$lignes[$p]['legacy_id']] + $ligne;

                continue;
            }

            if ($lignes[$p]['statut'] === null) {
                $lignes[$p]['statut'] = 'doublon_conflictuel';
                $lignes[$p]['detail'] = 'montants différents de legacy #'.$ligne['legacy_id'];
            }
            $lignes[] = ['statut' => 'doublon_conflictuel', 'detail' => 'montants différents de legacy #'.$lignes[$p]['legacy_id']] + $ligne;
        }

        $this->comparerSpecifiques($lignes);

        return $lignes;
    }

    /** PASS 2 (+ PASS 3) : analyse des règles dlm = 0, sans aucune écriture. */
    private function passeStandards(iterable $rows, bool $aRefus): array
    {
        $lignes = [];
        $parVille = [];

        foreach ($rows as $row) {
            $ligne = $this->base($row);

            if ($ligne['corbeille']) {
                $lignes[] = ['statut' => 'archive_legacy_non_repris', 'detail' => 'barème standard sans corbeille dans Zouhir'] + $ligne;

                continue;
            }

            $ligne = $this->villeEtMontants($row, $ligne, $aRefus);
            if ($ligne['statut'] !== null) {
                $lignes[] = $ligne;

                continue;
            }

            $parVille[$ligne['ville_id']][] = count($lignes);
            $lignes[] = $ligne;
        }

        // Une ville : montants tous identiques → une règle (identifiants fusionnés) ; sinon conflit.
        foreach ($parVille as $villeId => $indices) {
            $premier = $indices[0];
            $identiques = array_reduce($indices, fn (bool $ok, int $i) => $ok && $this->memes($lignes[$premier], $lignes[$i]), true);

            if (! $identiques) {
                $montants = implode(' / ', array_unique(array_map(fn ($i) => $lignes[$i]['livraison'].'+'.$lignes[$i]['refus'], $indices)));
                foreach ($indices as $i) {
                    $lignes[$i]['statut'] = 'conflit_montants';
                    $lignes[$i]['detail'] = 'montants différents pour la ville : '.$montants;
                }

                continue;
            }

            foreach (array_slice($indices, 1) as $i) {
                $lignes[$premier]['legacy_ids'][] = $lignes[$i]['legacy_id'];
                $lignes[$i]['statut'] = 'doublon_identique';
                $lignes[$i]['detail'] = 'fusionnée dans legacy #'.$lignes[$premier]['legacy_id'];
            }
        }

        // Aucun barème standard des commissions (décision du propriétaire, 01/11/2026, comme
        // WinningExpress) : les règles dlm = 0 sont ANALYSÉES et rapportées, jamais écrites.
        foreach ($lignes as &$l) {
            if ($l['statut'] === null) {
                $l['statut'] = 'non_repris_sans_bareme';
                $l['detail'] ??= 'dlm = 0 : pas de barème standard livreur dans Zouhir';
            }
        }
        unset($l);

        return $lignes;
    }

    /**
     * Écrit UNIQUEMENT les lignes migrables, en une transaction. Ne touche jamais `commands`.
     *
     * @return array{crees: int, crees_corbeille: int, restaurees: int}
     */
    public function appliquer(array $specifiques, bool $restaurer): array
    {
        return DB::transaction(function () use ($specifiques, $restaurer): array {
            $r = ['crees' => 0, 'crees_corbeille' => 0, 'restaurees' => 0];

            foreach ($specifiques as $l) {
                $valeurs = ['commission_livraison' => $l['livraison'], 'commission_refus' => $l['refus'], 'legacy_ids' => $l['legacy_ids']];

                if ($l['statut'] === 'a_creer') {
                    LivreurTarif::create($valeurs + ['livreur_id' => $l['livreur_id'], 'ville_id' => $l['ville_id']]);
                    $r['crees']++;
                } elseif ($l['statut'] === 'a_creer_corbeille') {
                    LivreurTarif::create($valeurs + ['livreur_id' => $l['livreur_id'], 'ville_id' => $l['ville_id']])->delete();
                    $r['crees_corbeille']++;
                } elseif ($l['statut'] === 'a_restaurer' && $restaurer) {
                    $t = LivreurTarif::onlyTrashed()->pour($l['livreur_id'], $l['ville_id'])->firstOrFail();
                    $t->restore();
                    $t->update($valeurs);
                    $r['restaurees']++;
                }
            }

            return $r;
        });
    }

    /** @return array<string, mixed> */
    private function base(object $row): array
    {
        return [
            'legacy_id' => (int) $row->id,
            'legacy_ids' => [(int) $row->id],
            'legacy_dlm' => (int) $row->dlm,
            'legacy_ville' => (string) $row->city,
            'ville_id' => null,
            'ville_methode' => null,
            'corbeille' => (int) $row->trash !== 1,
            'livraison' => null,
            'refus' => null,
            'statut' => null,
            'detail' => null,
        ];
    }

    private function villeEtMontants(object $row, array $ligne, bool $aRefus): array
    {
        $ville = $this->villes->resoudre((string) $row->city, historique: true);
        $ligne['ville_methode'] = $ville->methode;
        if (! $ville->estResolue()) {
            return ['statut' => 'ville_introuvable'] + $ligne;
        }
        $ligne['ville_id'] = $ville->villeId;
        $ligne['detail'] = match ($ville->methode) {
            VilleResolution::HOMONYMES => 'homonymes '.implode('/', $ville->candidats).' : '.$ville->villeId.' retenu',
            VilleResolution::ARCHIVEE => 'ville archivée',
            default => null,
        };

        $livraison = $this->montant($row->deliveredfees ?? null);
        $refus = $aRefus ? $this->montant($row->refusedfees ?? null) : null;
        if ($livraison === null || $refus === null) {
            return ['statut' => 'montant_invalide', 'detail' => $aRefus ? 'montant illisible ou négatif' : 'colonne refusedfees absente du legacy'] + $ligne;
        }

        return ['livraison' => $livraison, 'refus' => $refus] + $ligne;
    }

    /**
     * Identité de chaque livreur legacy : même id ET (même email OU même nom) ; comptes archivés inclus.
     *
     * @return array<int, array<string, mixed>>
     */
    private function identitesLivreurs(ConnectionInterface $legacy, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        if (! $legacy->getSchemaBuilder()->hasTable('users')) {
            throw new RuntimeException('Table legacy `users` introuvable : identité des livreurs non vérifiable.');
        }

        $sources = $legacy->table('users')->whereIn('id', $ids)->get(['id', 'email', 'fullname'])->keyBy('id');
        $cibles = User::withTrashed()->whereIn('id', $ids)->get(['id', 'email', 'nom_complet', 'deleted_at'])->keyBy('id');
        $livreurs = array_flip(User::withTrashed()->drivers()->whereIn('users.id', $ids)->pluck('users.id')->map(fn ($id) => (int) $id)->all());
        $norm = fn (?string $s): string => mb_strtolower(trim((string) $s));
        $res = [];

        foreach ($ids as $id) {
            $s = $sources->get($id);
            $c = $cibles->get($id);
            if ($s === null || $c === null) {
                $res[$id] = ['statut' => 'livreur_introuvable', 'livreur_id' => null, 'methode' => null, 'detail' => $s === null ? 'absent du legacy users' : 'absent de Zouhir'];

                continue;
            }

            $methode = match (true) {
                $norm($s->email) !== '' && $norm($s->email) === $norm($c->email) => 'email',
                $norm($s->fullname) !== '' && $norm($s->fullname) === $norm($c->nom_complet) => 'nom',
                default => null,
            };

            $res[$id] = match (true) {
                $methode === null => ['statut' => 'identite_conflit', 'livreur_id' => null, 'methode' => 'conflit', 'detail' => 'email et nom différents'],
                ! isset($livreurs[$id]) => ['statut' => 'pas_livreur', 'livreur_id' => null, 'methode' => $methode, 'detail' => 'compte Zouhir sans rôle livreur'],
                default => ['statut' => 'ok', 'livreur_id' => $id, 'methode' => $methode, 'archive' => $c->deleted_at !== null],
            };
        }

        return $res;
    }

    private function comparerSpecifiques(array &$lignes): void
    {
        $candidats = array_filter($lignes, fn ($l) => $l['statut'] === null);
        if ($candidats === []) {
            return;
        }

        $existants = LivreurTarif::withTrashed()
            ->whereIn('livreur_id', array_unique(array_column($candidats, 'livreur_id')))
            ->whereIn('ville_id', array_unique(array_column($candidats, 'ville_id')))
            ->get()
            ->keyBy(fn (LivreurTarif $t) => $t->livreur_id.'|'.$t->ville_id);

        foreach ($lignes as &$l) {
            if ($l['statut'] !== null) {
                continue;
            }
            $e = $existants->get($l['livreur_id'].'|'.$l['ville_id']);
            $memes = $e !== null && (string) $e->commission_livraison === $l['livraison'] && (string) $e->commission_refus === $l['refus'];

            $l['statut'] = match (true) {
                $e === null => $l['corbeille'] ? 'a_creer_corbeille' : 'a_creer',
                $l['corbeille'] && $e->trashed() => $memes ? 'identique' : 'conflit_valeur',
                $l['corbeille'] => 'conflit_etat',
                $e->trashed() => 'a_restaurer',
                $memes => 'identique',
                default => 'conflit_valeur',
            };

            if (in_array($l['statut'], ['conflit_valeur', 'conflit_etat'], true)) {
                $l['detail'] = sprintf('Zouhir : %s / %s%s', $e->commission_livraison, $e->commission_refus, $e->trashed() ? ' (corbeille)' : '');
            }
        }
        unset($l);
    }

    /** Synthèse de contrôle : tous les chiffres du rapport #30 recalculés sur les données réelles. */
    private function synthese(array $rows, array $specifiques, array $standards, array $identites): array
    {
        $actives = array_filter($rows, fn ($r) => (int) $r->trash === 1);
        $spec = array_filter($rows, fn ($r) => (int) $r->dlm > 0);
        $specActives = array_filter($spec, fn ($r) => (int) $r->trash === 1);
        $stdActives = array_filter($rows, fn ($r) => (int) $r->dlm <= 0 && (int) $r->trash === 1);

        $porteuses = array_filter($specifiques, fn ($l) => in_array($l['statut'], self::PORTEURS, true));
        $stdRetenus = array_filter($standards, fn ($l) => $l['statut'] === 'non_repris_sans_bareme');
        $ok = array_filter($identites, fn ($i) => $i['statut'] === 'ok');

        $montantsSpec = array_map(fn ($r) => (float) $r->deliveredfees, $specActives);

        // Variantes : plusieurs libellés legacy résolus vers la même ville.
        $libellesParVille = [];
        foreach (array_merge($specifiques, $standards) as $l) {
            if ($l['ville_id'] !== null) {
                $libellesParVille[$l['ville_id']][mb_strtolower(trim($l['legacy_ville']))] = true;
            }
        }

        return [
            'lignes' => count($rows),
            'actives' => count($actives),
            'archivees' => count($rows) - count($actives),
            'specifiques_actives' => count($specActives),
            'specifiques_archivees' => count($spec) - count($specActives),
            'standards_actives' => count($stdActives),
            'standards_archivees' => count($rows) - count($spec) - count($stdActives),
            'livreurs_legacy' => count($identites),
            'livreurs_identifies' => count($ok),
            'livreurs_archives' => count(array_filter($ok, fn ($i) => $i['archive'] ?? false)),
            'villes_specifiques_legacy' => count(array_unique(array_map(fn ($r) => mb_strtolower(trim((string) $r->city)), $specActives))),
            'villes_specifiques_zouhir' => count(array_unique(array_filter(array_column($porteuses, 'ville_id')))),
            'regles_specifiques_uniques' => count($porteuses),
            'commission_min' => $montantsSpec === [] ? null : number_format(min($montantsSpec), 2, '.', ''),
            'commission_max' => $montantsSpec === [] ? null : number_format(max($montantsSpec), 2, '.', ''),
            'commission_moyenne' => $montantsSpec === [] ? null : number_format(array_sum($montantsSpec) / count($montantsSpec), 2, '.', ''),
            'refus_specifiques' => $this->distribution(array_filter($specifiques, fn ($l) => $l['refus'] !== null && ! $l['corbeille']), 'refus'),
            'standards_villes_legacy' => count(array_unique(array_map(fn ($r) => mb_strtolower(trim((string) $r->city)), $stdActives))),
            'standards_villes_zouhir' => count($stdRetenus),
            'standards_montants' => $this->distribution(array_filter($standards, fn ($l) => $l['livraison'] !== null && ! $l['corbeille']), 'livraison'),
            'standards_doublons' => count(array_filter($standards, fn ($l) => $l['statut'] === 'doublon_identique')),
            'standards_conflits_villes' => count(array_unique(array_column(array_filter($standards, fn ($l) => $l['statut'] === 'conflit_montants'), 'ville_id'))),
            'variantes_villes' => count(array_filter($libellesParVille, fn ($v) => count($v) > 1)),
            'conflits_zouhir' => count(array_filter(array_merge($specifiques, $standards), fn ($l) => in_array($l['statut'], ['conflit_valeur', 'conflit_etat'], true))),
        ];
    }

    private function memes(array $a, array $b): bool
    {
        return $a['livraison'] === $b['livraison'] && $a['refus'] === $b['refus'];
    }

    private function montant(mixed $v): ?string
    {
        if ($v === null || $v === '' || ! is_numeric($v) || (float) $v < 0) {
            return null;
        }

        return number_format((float) $v, 2, '.', '');
    }

    /** @return array<string, int> */
    private function distribution(array $lignes, string $champ): array
    {
        $d = [];
        foreach ($lignes as $l) {
            $d[(string) $l[$champ]] = ($d[(string) $l[$champ]] ?? 0) + 1;
        }
        ksort($d, SORT_NUMERIC);

        return $d;
    }

    /** @return array<string, int> */
    private function compter(array $lignes, string $champ): array
    {
        $c = [];
        foreach ($lignes as $l) {
            $k = (string) ($l[$champ] ?? '—');
            $c[$k] = ($c[$k] ?? 0) + 1;
        }
        ksort($c);

        return $c;
    }
}
