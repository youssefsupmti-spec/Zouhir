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
 * Rapprochement DÉTERMINISTE des tarifs legacy (stockout.clientfees / gshippingfees)
 * avec Zouhir. Lecture seule tant que {@see appliquer()} n'est pas appelé.
 *
 * Chaque ligne legacy reçoit UN statut explicite :
 *   identique | a_creer | a_restaurer | conflit_valeur | doublon_identique
 *   | doublon_conflictuel | ville_ambigue | ville_introuvable | client_introuvable
 *   | client_archive | identite_conflit | pas_client | montant_invalide | archivee_legacy
 *
 * Règles :
 *  - le client est vérifié par son IDENTITÉ (id + email, ou id + nom), jamais par l'id seul ;
 *  - la ville passe par VilleRef (exacte → normalisée → alias ; ambiguïtés détectées) ;
 *  - une valeur Zouhir existante n'est JAMAIS écrasée (conflit_valeur) ;
 *  - une ligne supprimée dans Zouhir n'est restaurée qu'avec l'option explicite `restaurer`.
 */
final class RapprochementClientFees
{
    public const MIGRABLES = ['a_creer', 'a_restaurer'];

    public function __construct(private readonly VilleRef $villes) {}

    /**
     * @return array{colonnes_frais: array{refus: bool, retour: bool}, lignes: list<array<string, mixed>>, compte: array<string, int>, clients: array<string, int>, villes: array<string, int>}
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

        // Refus / retour : repris UNIQUEMENT si les colonnes existent. Sinon la ligne n'est pas migrable
        // (aucune valeur inventée, ni 0.00 ni frais de livraison).
        $aRefus = in_array('refusedfees', $colonnes, true);
        $aRetour = in_array('returnedfees', $colonnes, true);

        $select = ['id', 'client', 'city', 'deliveredfees', 'trash'];
        if ($aRefus) {
            $select[] = 'refusedfees';
        }
        if ($aRetour) {
            $select[] = 'returnedfees';
        }

        $legacyRows = $legacy->table('clientfees')->orderBy('id')->get($select);

        $identites = $this->identitesClients($legacy, $legacyRows->pluck('client')->map(fn ($c) => (int) $c)->unique()->values()->all());

        $lignes = [];
        $vus = [];

        foreach ($legacyRows as $row) {
            $ligne = [
                'legacy_id' => (int) $row->id,
                'legacy_client' => (int) $row->client,
                'legacy_ville' => (string) $row->city,
                'client_id' => null,
                'ville_id' => null,
                'ville_methode' => null,
                'identite' => null,
                'livraison' => null,
                'refus' => null,
                'retour' => null,
                'statut' => null,
                'detail' => null,
            ];

            if ((int) $row->trash !== 1) {
                $lignes[] = ['statut' => 'archivee_legacy', 'detail' => 'trash ≠ 1'] + $ligne;

                continue;
            }

            $id = $identites[(int) $row->client] ?? ['statut' => 'client_introuvable', 'client_id' => null, 'methode' => null];
            $ligne['identite'] = $id['methode'];
            if ($id['statut'] !== 'ok') {
                $lignes[] = ['statut' => $id['statut'], 'detail' => $id['detail'] ?? null] + $ligne;

                continue;
            }
            $ligne['client_id'] = $id['client_id'];

            $ville = $this->villes->resoudre((string) $row->city);
            $ligne['ville_methode'] = $ville->methode;
            if (! $ville->estResolue()) {
                $statut = $ville->methode === VilleResolution::AMBIGUE ? 'ville_ambigue' : 'ville_introuvable';
                $lignes[] = ['statut' => $statut, 'detail' => $ville->candidats ? 'candidats : '.implode(', ', $ville->candidats) : null] + $ligne;

                continue;
            }
            $ligne['ville_id'] = $ville->villeId;

            $montants = $this->montants($row, $aRefus, $aRetour);
            if ($montants === null) {
                $detail = (! $aRefus || ! $aRetour)
                    ? 'colonnes refusedfees/returnedfees absentes du legacy'
                    : 'montant illisible ou négatif';
                $lignes[] = ['statut' => 'montant_invalide', 'detail' => $detail] + $ligne;

                continue;
            }
            $ligne = $montants + $ligne;

            $cle = $ligne['client_id'].'|'.$ligne['ville_id'];
            if (isset($vus[$cle])) {
                $premier = &$lignes[$vus[$cle]];
                $identique = $premier['livraison'] === $ligne['livraison']
                    && $premier['refus'] === $ligne['refus']
                    && $premier['retour'] === $ligne['retour'];

                if (! $identique && $premier['statut'] === null) {
                    // Deux montants différents pour le même couple : rien n'est migré pour ce couple.
                    $premier['statut'] = 'doublon_conflictuel';
                    $premier['detail'] = 'autre ligne legacy #'.$ligne['legacy_id'];
                }
                unset($premier);

                $lignes[] = ['statut' => $identique ? 'doublon_identique' : 'doublon_conflictuel', 'detail' => 'doublon de la ligne legacy #'.$lignes[$vus[$cle]]['legacy_id']] + $ligne;

                continue;
            }

            $vus[$cle] = count($lignes);
            $lignes[] = $ligne;
        }

        $this->comparerAvecZouhir($lignes);

        return [
            'colonnes_frais' => ['refus' => $aRefus, 'retour' => $aRetour],
            'lignes' => $lignes,
            'compte' => $this->compter($lignes, 'statut'),
            'clients' => $this->compter($lignes, 'identite'),
            'villes' => $this->compter($lignes, 'ville_methode'),
        ];
    }

    /**
     * Barème général legacy (gshippingfees) → standard_tarifs. Même logique de statut.
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
                $lignes[] = ['statut' => 'archivee_legacy'] + $ligne;

                continue;
            }

            $ville = $this->villes->resoudre((string) $row->city);
            if (! $ville->estResolue()) {
                $lignes[] = ['statut' => $ville->methode === VilleResolution::AMBIGUE ? 'ville_ambigue' : 'ville_introuvable'] + $ligne;

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
     * Écrit UNIQUEMENT les lignes `a_creer` (et `a_restaurer` si autorisé), en une transaction.
     *
     * @param  list<array<string, mixed>>  $lignes
     * @return array{crees: int, restaurees: int}
     */
    public function appliquer(array $lignes, bool $restaurer, array $standards = []): array
    {
        return DB::transaction(function () use ($lignes, $restaurer, $standards): array {
            $r = ['crees' => 0, 'restaurees' => 0, 'standards_crees' => 0];

            foreach ($lignes as $l) {
                $valeurs = ['frais_livraison' => $l['livraison'], 'frais_refus' => $l['refus'], 'frais_retour' => $l['retour']];

                if ($l['statut'] === 'a_creer') {
                    ClientTarif::create($valeurs + ['client_id' => $l['client_id'], 'ville_id' => $l['ville_id']]);
                    $r['crees']++;
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
                    ]);
                    $r['standards_crees']++;
                }
            }

            return $r;
        });
    }

    /**
     * Identité de chaque client legacy dans Zouhir : même id ET (même email OU même nom).
     *
     * @param  list<int>  $ids
     * @return array<int, array{statut: string, client_id: ?int, methode: ?string, detail?: string}>
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
        $clients = array_flip(User::clients()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all());

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
                $cible->deleted_at !== null => ['statut' => 'client_archive', 'client_id' => null, 'methode' => $methode],
                ! isset($clients[$id]) => ['statut' => 'pas_client', 'client_id' => null, 'methode' => $methode],
                default => ['statut' => 'ok', 'client_id' => $id, 'methode' => $methode],
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
                $e === null => 'a_creer',
                $e->trashed() => 'a_restaurer',
                $this->memesMontants($e, $l) => 'identique',
                default => 'conflit_valeur',
            };

            if ($l['statut'] === 'conflit_valeur') {
                $l['detail'] = sprintf('Zouhir : %s / %s / %s', $e->frais_livraison, $e->frais_refus, $e->frais_retour);
            }
        }
        unset($l);
    }

    private function memesMontants(object $e, array $l): bool
    {
        return (string) $e->frais_livraison === $l['livraison']
            && (string) $e->frais_refus === $l['refus']
            && (string) $e->frais_retour === $l['retour'];
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
