<?php

declare(strict_types=1);

namespace App\Application\Tarifs\Backfill;

use App\Domain\Villes\Support\VilleRef;
use App\Domain\Villes\Support\VilleResolution;
use App\Models\ClientTarif;
use App\Models\StandardTarif;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rapprochement DÉTERMINISTE des tarifs legacy (stockout.clientfees / gshippingfees) avec Zouhir.
 * Lecture seule tant que {@see appliquer()} n'est pas appelé.
 *
 * Règles validées par le propriétaire (rapport #35, décisions 1 à 6) — alignées sur WinningExpress :
 *  - VILLE : {@see VilleRef} en mode historique — ville active d'abord, homonymes départagés par le
 *    plus petit id, ville archivée acceptée si elle est la seule correspondance (relation conservée) ;
 *  - CLIENT : identité vérifiée (même id ET même email, ou à défaut même nom) ; un client ARCHIVÉ
 *    garde ses tarifs (recherche sans filtrer deleted_at) ;
 *  - CORBEILLE : trash = 1 → règle active ; sinon règle reprise EN CORBEILLE ;
 *  - MONTANTS : deliveredfees / refusedfees / returnedfees repris EXACTEMENT ;
 *  - DOUBLONS : deux lignes du même couple et de MÊMES montants → une seule règle portant les deux
 *    identifiants legacy (`legacy_ids`) ; montants différents → rien n'est migré pour ce couple ;
 *  - ZOUHIR : une règle existante n'est JAMAIS écrasée (conflit explicite).
 *
 * Statuts : a_creer | a_creer_corbeille | a_restaurer | identique | conflit_valeur | conflit_etat
 *   | doublon_identique | doublon_conflictuel | doublon_corbeille | ville_introuvable
 *   | client_introuvable | identite_conflit | pas_client | montant_invalide.
 */
final class RapprochementClientFees
{
    public const MIGRABLES = ['a_creer', 'a_creer_corbeille', 'a_restaurer'];

    /** Statuts d'une ligne qui PORTE une règle unique (créée, déjà présente ou en conflit). */
    public const PORTEURS = ['a_creer', 'a_creer_corbeille', 'a_restaurer', 'identique', 'conflit_valeur', 'conflit_etat'];

    public function __construct(private readonly VilleRef $villes) {}

    /**
     * @return array{colonnes_frais: array{refus: bool, retour: bool}, lignes: list<array<string, mixed>>, compte: array<string, int>, clients: array<string, int>, villes: array<string, int>, synthese: array<string, mixed>}
     */
    public function analyserClientFees(ConnectionInterface $legacy): array
    {
        $schema = $legacy->getSchemaBuilder();

        if (! $schema->hasTable('clientfees')) {
            throw new RuntimeException('Table legacy `clientfees` introuvable sur cette connexion.');
        }

        $colonnes = array_map('strtolower', $schema->getColumnListing('clientfees'));
        foreach (['id', 'client', 'city', 'deliveredfees', 'trash'] as $requise) {
            if (! in_array($requise, $colonnes, true)) {
                throw new RuntimeException("Colonne legacy `clientfees.$requise` absente : rapprochement impossible.");
            }
        }

        // Refus / retour : repris UNIQUEMENT si les colonnes existent (aucune valeur inventée).
        $aRefus = in_array('refusedfees', $colonnes, true);
        $aRetour = in_array('returnedfees', $colonnes, true);
        $select = array_values(array_filter(['id', 'client', 'city', 'deliveredfees', 'trash', $aRefus ? 'refusedfees' : null, $aRetour ? 'returnedfees' : null]));

        $legacyRows = $legacy->table('clientfees')->orderBy('id')->get($select);
        $identites = $this->identitesClients($legacy, $legacyRows->pluck('client')->map(fn ($c) => (int) $c)->unique()->values()->all());

        $lignes = [];
        /** @var array<string, int> "client|ville" => index de la ligne porteuse */
        $porteur = [];

        foreach ($legacyRows as $row) {
            $ligne = [
                'legacy_id' => (int) $row->id,
                'legacy_ids' => [(int) $row->id],
                'legacy_client' => (int) $row->client,
                'legacy_ville' => (string) $row->city,
                'client_id' => null,
                'client_archive' => false,
                'ville_id' => null,
                'ville_methode' => null,
                'identite' => null,
                'corbeille' => (int) $row->trash !== 1,
                'livraison' => null,
                'refus' => null,
                'retour' => null,
                'statut' => null,
                'detail' => null,
            ];

            $id = $identites[(int) $row->client] ?? ['statut' => 'client_introuvable', 'client_id' => null, 'methode' => null];
            $ligne['identite'] = $id['methode'];
            if ($id['statut'] !== 'ok') {
                $lignes[] = ['statut' => $id['statut'], 'detail' => $id['detail'] ?? null] + $ligne;

                continue;
            }
            $ligne['client_id'] = $id['client_id'];
            $ligne['client_archive'] = $id['archive'];

            $ville = $this->villes->resoudre((string) $row->city, historique: true);
            $ligne['ville_methode'] = $ville->methode;
            if (! $ville->estResolue()) {
                $lignes[] = ['statut' => 'ville_introuvable'] + $ligne;

                continue;
            }
            $ligne['ville_id'] = $ville->villeId;
            $ligne['detail'] = match ($ville->methode) {
                VilleResolution::HOMONYMES => 'homonymes '.implode('/', $ville->candidats).' : '.$ville->villeId.' retenu',
                VilleResolution::ARCHIVEE => 'ville archivée',
                default => null,
            };

            $montants = $this->montants($row, $aRefus, $aRetour);
            if ($montants === null) {
                $lignes[] = ['statut' => 'montant_invalide', 'detail' => (! $aRefus || ! $aRetour)
                    ? 'colonnes refusedfees/returnedfees absentes du legacy'
                    : 'montant illisible ou négatif'] + $ligne;

                continue;
            }
            $ligne = $montants + $ligne;

            $cle = $ligne['client_id'].'|'.$ligne['ville_id'];

            if (! isset($porteur[$cle])) {
                $porteur[$cle] = count($lignes);
                $lignes[] = $ligne;

                continue;
            }

            $p = $porteur[$cle];

            // Une seule règle par couple : la règle ACTIVE l'emporte sur celle en corbeille.
            if ($lignes[$p]['corbeille'] !== $ligne['corbeille']) {
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

            if ($this->memesMontantsLignes($lignes[$p], $ligne)) {
                // Doublon identique : neutralisé, son identifiant est conservé sur la règle porteuse.
                $lignes[$p]['legacy_ids'][] = $ligne['legacy_id'];
                $lignes[] = ['statut' => 'doublon_identique', 'detail' => 'fusionnée dans legacy #'.$lignes[$p]['legacy_id']] + $ligne;

                continue;
            }

            // Montants différents : jamais dédupliqués, rien n'est migré pour ce couple.
            if ($lignes[$p]['statut'] === null) {
                $lignes[$p]['statut'] = 'doublon_conflictuel';
                $lignes[$p]['detail'] = 'montants différents de legacy #'.$ligne['legacy_id'];
            }
            $lignes[] = ['statut' => 'doublon_conflictuel', 'detail' => 'montants différents de legacy #'.$lignes[$p]['legacy_id']] + $ligne;
        }

        $this->comparerAvecZouhir($lignes);

        return [
            'colonnes_frais' => ['refus' => $aRefus, 'retour' => $aRetour],
            'lignes' => $lignes,
            'compte' => $this->compter($lignes, 'statut'),
            'clients' => $this->compter($lignes, 'identite'),
            'villes' => $this->compter($lignes, 'ville_methode'),
            'synthese' => $this->synthese($lignes, $identites),
        ];
    }

    /**
     * Barème général legacy (gshippingfees) → standard_tarifs (source unique du standard).
     *
     * @return array{lignes: list<array<string, mixed>>, compte: array<string, int>}
     */
    public function analyserStandards(ConnectionInterface $legacy): array
    {
        $schema = $legacy->getSchemaBuilder();
        if (! $schema->hasTable('gshippingfees')) {
            return ['lignes' => [], 'compte' => ['table_absente' => 1]];
        }

        $colonnes = array_map('strtolower', $schema->getColumnListing('gshippingfees'));
        $aRefus = in_array('refusedfees', $colonnes, true);
        $aRetour = in_array('returnedfees', $colonnes, true);
        $select = array_values(array_filter(['id', 'city', 'deliveredfees', 'trash', $aRefus ? 'refusedfees' : null, $aRetour ? 'returnedfees' : null]));

        $lignes = [];
        $vus = [];
        foreach ($legacy->table('gshippingfees')->orderBy('id')->get($select) as $row) {
            $ligne = ['legacy_id' => (int) $row->id, 'legacy_ville' => (string) $row->city, 'ville_id' => null, 'statut' => null, 'detail' => null];

            if ((int) ($row->trash ?? 1) !== 1) {
                // standard_tarifs n'a pas de corbeille : un barème legacy archivé n'est pas repris.
                $lignes[] = ['statut' => 'archive_legacy_non_repris'] + $ligne;

                continue;
            }

            $ville = $this->villes->resoudre((string) $row->city, historique: true);
            if (! $ville->estResolue()) {
                $lignes[] = ['statut' => 'ville_introuvable'] + $ligne;

                continue;
            }

            $montants = $this->montants($row, $aRefus, $aRetour);
            if ($montants === null) {
                $lignes[] = ['statut' => 'montant_invalide', 'ville_id' => $ville->villeId] + $ligne;

                continue;
            }

            $ligne = $montants + ['ville_id' => $ville->villeId] + $ligne;

            if (isset($vus[$ville->villeId])) {
                $lignes[] = ['statut' => 'doublon_conflictuel', 'detail' => 'ville déjà vue'] + $ligne;
                $lignes[$vus[$ville->villeId]]['statut'] = 'doublon_conflictuel';

                continue;
            }

            $existant = StandardTarif::query()->where('ville_id', $ville->villeId)->first();
            $ligne['statut'] = match (true) {
                $existant === null => 'a_creer',
                $this->memesMontants($existant, $ligne) => 'identique',
                default => 'conflit_valeur',
            };

            $vus[$ville->villeId] = count($lignes);
            $lignes[] = $ligne;
        }

        return ['lignes' => $lignes, 'compte' => $this->compter($lignes, 'statut')];
    }

    /**
     * Écrit UNIQUEMENT les lignes migrables, en une transaction. Ne touche jamais `commands`.
     *
     * @param  list<array<string, mixed>>  $lignes
     * @param  list<array<string, mixed>>  $standards
     * @return array{crees: int, crees_corbeille: int, restaurees: int, standards_crees: int}
     */
    public function appliquer(array $lignes, bool $restaurer, array $standards = []): array
    {
        return DB::transaction(function () use ($lignes, $restaurer, $standards): array {
            $r = ['crees' => 0, 'crees_corbeille' => 0, 'restaurees' => 0, 'standards_crees' => 0];

            foreach ($lignes as $l) {
                $valeurs = [
                    'frais_livraison' => $l['livraison'],
                    'frais_refus' => $l['refus'],
                    'frais_retour' => $l['retour'],
                    'legacy_ids' => $l['legacy_ids'],
                ];

                if ($l['statut'] === 'a_creer') {
                    ClientTarif::create($valeurs + ['client_id' => $l['client_id'], 'ville_id' => $l['ville_id']]);
                    $r['crees']++;
                } elseif ($l['statut'] === 'a_creer_corbeille') {
                    ClientTarif::create($valeurs + ['client_id' => $l['client_id'], 'ville_id' => $l['ville_id']])->delete();
                    $r['crees_corbeille']++;
                } elseif ($l['statut'] === 'a_restaurer' && $restaurer) {
                    $tarif = ClientTarif::onlyTrashed()->pour($l['client_id'], $l['ville_id'])->firstOrFail();
                    $tarif->restore();
                    $tarif->update($valeurs);
                    $r['restaurees']++;
                }
            }

            foreach ($standards as $s) {
                if ($s['statut'] === 'a_creer') {
                    StandardTarif::create([
                        'ville_id' => $s['ville_id'],
                        'frais_livraison' => $s['livraison'],
                        'frais_refus' => $s['refus'],
                        'frais_retour' => $s['retour'],
                        'legacy_ids' => [$s['legacy_id']],
                    ]);
                    $r['standards_crees']++;
                }
            }

            return $r;
        });
    }

    /**
     * Identité de chaque client legacy dans Zouhir : même id ET (même email OU même nom).
     * Les comptes ARCHIVÉS sont inclus (conservation historique, comme WinningExpress).
     *
     * @param  list<int>  $ids
     * @return array<int, array{statut: string, client_id: ?int, methode: ?string, archive?: bool, detail?: string}>
     */
    private function identitesClients(ConnectionInterface $legacy, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        if (! $legacy->getSchemaBuilder()->hasTable('users')) {
            throw new RuntimeException('Table legacy `users` introuvable : identité des clients non vérifiable.');
        }

        $sources = $legacy->table('users')->whereIn('id', $ids)->get(['id', 'email', 'fullname'])->keyBy('id');
        $cibles = User::withTrashed()->whereIn('id', $ids)->get(['id', 'email', 'nom_complet', 'deleted_at'])->keyBy('id');
        $clients = array_flip(User::withTrashed()->clients()->whereIn('users.id', $ids)->pluck('users.id')->map(fn ($id) => (int) $id)->all());

        $norm = fn (?string $s): string => mb_strtolower(trim((string) $s));
        $res = [];

        foreach ($ids as $id) {
            $source = $sources->get($id);
            $cible = $cibles->get($id);

            if ($source === null || $cible === null) {
                $res[$id] = ['statut' => 'client_introuvable', 'client_id' => null, 'methode' => null,
                    'detail' => $source === null ? 'absent du legacy users' : 'absent de Zouhir'];

                continue;
            }

            $methode = match (true) {
                $norm($source->email) !== '' && $norm($source->email) === $norm($cible->email) => 'email',
                $norm($source->fullname) !== '' && $norm($source->fullname) === $norm($cible->nom_complet) => 'nom',
                default => null,
            };

            $res[$id] = match (true) {
                $methode === null => ['statut' => 'identite_conflit', 'client_id' => null, 'methode' => 'conflit', 'detail' => 'email et nom différents'],
                ! isset($clients[$id]) => ['statut' => 'pas_client', 'client_id' => null, 'methode' => $methode],
                default => ['statut' => 'ok', 'client_id' => $id, 'methode' => $methode, 'archive' => $cible->deleted_at !== null],
            };
        }

        return $res;
    }

    /** @param  list<array<string, mixed>>  $lignes */
    private function comparerAvecZouhir(array &$lignes): void
    {
        $candidats = array_filter($lignes, fn ($l) => $l['statut'] === null);
        if ($candidats === []) {
            return;
        }

        $existants = ClientTarif::withTrashed()
            ->whereIn('client_id', array_unique(array_column($candidats, 'client_id')))
            ->whereIn('ville_id', array_unique(array_column($candidats, 'ville_id')))
            ->get()
            ->keyBy(fn (ClientTarif $t) => $t->client_id.'|'.$t->ville_id);

        foreach ($lignes as &$l) {
            if ($l['statut'] !== null) {
                continue;
            }

            $e = $existants->get($l['client_id'].'|'.$l['ville_id']);
            $l['statut'] = match (true) {
                $e === null => $l['corbeille'] ? 'a_creer_corbeille' : 'a_creer',
                $l['corbeille'] && $e->trashed() => $this->memesMontants($e, $l) ? 'identique' : 'conflit_valeur',
                // Actif dans Zouhir mais en corbeille dans le legacy : jamais supprimé d'office.
                $l['corbeille'] => 'conflit_etat',
                $e->trashed() => 'a_restaurer',
                $this->memesMontants($e, $l) => 'identique',
                default => 'conflit_valeur',
            };

            if (in_array($l['statut'], ['conflit_valeur', 'conflit_etat'], true)) {
                $l['detail'] = sprintf('Zouhir : %s / %s / %s%s', $e->frais_livraison, $e->frais_refus, $e->frais_retour, $e->trashed() ? ' (corbeille)' : '');
            }
        }
        unset($l);
    }

    /**
     * Synthèse de contrôle (décision 8) : règles uniques et leur répartition par catégorie.
     *
     * @param  list<array<string, mixed>>  $lignes
     * @param  array<int, array<string, mixed>>  $identites
     * @return array<string, mixed>
     */
    private function synthese(array $lignes, array $identites): array
    {
        $porteuses = array_filter($lignes, fn ($l) => in_array($l['statut'], self::PORTEURS, true));

        // Une catégorie par règle, par priorité : client archivé > ville archivée > homonymes > simple.
        $categories = ['simples' => 0, 'homonymes' => 0, 'villes_archivees' => 0, 'clients_archives' => 0];
        foreach ($porteuses as $l) {
            $categories[match (true) {
                $l['client_archive'] => 'clients_archives',
                $l['ville_methode'] === VilleResolution::ARCHIVEE => 'villes_archivees',
                $l['ville_methode'] === VilleResolution::HOMONYMES => 'homonymes',
                default => 'simples',
            }]++;
        }

        $ok = array_filter($identites, fn ($i) => $i['statut'] === 'ok');

        return [
            'lignes_examinees' => count($lignes),
            'regles_uniques' => count($porteuses),
            'neutralisees' => count(array_filter($lignes, fn ($l) => $l['statut'] === 'doublon_identique')),
            'categories' => $categories,
            'clients_legacy' => count($identites),
            'clients_identifies' => count($ok),
            'clients_archives' => count(array_filter($ok, fn ($i) => $i['archive'] ?? false)),
            'refus_lignes_legacy' => $this->distribution(array_filter($lignes, fn ($l) => $l['refus'] !== null), 'refus'),
            'retour_lignes_legacy' => $this->distribution(array_filter($lignes, fn ($l) => $l['retour'] !== null), 'retour'),
            'conflits' => count(array_filter($lignes, fn ($l) => in_array($l['statut'], ['conflit_valeur', 'conflit_etat'], true))),
        ];
    }

    /** @return array<string, int> montant => nombre de lignes */
    private function distribution(array $lignes, string $champ): array
    {
        $d = [];
        foreach ($lignes as $l) {
            $d[(string) $l[$champ]] = ($d[(string) $l[$champ]] ?? 0) + 1;
        }
        ksort($d, SORT_NUMERIC);

        return $d;
    }

    private function memesMontants(object $e, array $l): bool
    {
        return (string) $e->frais_livraison === $l['livraison']
            && (string) $e->frais_refus === $l['refus']
            && (string) $e->frais_retour === $l['retour'];
    }

    private function memesMontantsLignes(array $a, array $b): bool
    {
        return $a['livraison'] === $b['livraison'] && $a['refus'] === $b['refus'] && $a['retour'] === $b['retour'];
    }

    /** @return array{livraison: string, refus: string, retour: string}|null */
    private function montants(object $row, bool $aRefus, bool $aRetour): ?array
    {
        if (! $aRefus || ! $aRetour) {
            return null;
        }

        $valeurs = [];
        foreach (['livraison' => 'deliveredfees', 'refus' => 'refusedfees', 'retour' => 'returnedfees'] as $cle => $col) {
            $v = $row->{$col} ?? null;
            if ($v === null || $v === '' || ! is_numeric($v) || (float) $v < 0) {
                return null;
            }
            $valeurs[$cle] = number_format((float) $v, 2, '.', '');
        }

        return $valeurs;
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
