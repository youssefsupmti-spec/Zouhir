<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Clients\Services;

use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Models\ClientTarif;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Toutes les écritures de la grille « Tarifs clients » (point unique).
 *
 * Adapté de WinningExpress (ClientTarifEcriture) avec les garanties suivantes :
 *  - une seule ligne par couple (client, ville), corbeille comprise (UNIQUE en base) :
 *    une ville supprimée puis renégociée est RESTAURÉE, jamais doublée ;
 *  - une ligne active existante n'est JAMAIS écrasée sans décision explicite
 *    (`existants` = ignorer | mettre_a_jour) — WinningExpress la mettait à jour d'office ;
 *  - toute opération est validée intégralement AVANT écriture, puis écrite dans
 *    une transaction : pas d'écriture partielle ;
 *  - 0.00 est un montant valide ; un montant vide ou négatif est refusé.
 *
 * Aucune écriture ne touche `commands` : les colis existants gardent leur snapshot.
 */
final class ClientTarifEcriture
{
    public const EXISTANTS_IGNORER = 'ignorer';
    public const EXISTANTS_METTRE_A_JOUR = 'mettre_a_jour';

    public function __construct(private readonly FeeResolutionService $fees) {}

    /**
     * État de chaque ville demandée pour ce client, sans rien écrire (aperçu du modal).
     *
     * @param  list<int>  $villeIds
     * @return array<int, array{ville_id: int, statut: string, tarif_id: ?int, livraison: ?string, refus: ?string, retour: ?string}>
     */
    public function apercu(int $clientId, array $villeIds): array
    {
        $villeIds = $this->idsUniques($villeIds);

        $lignes = ClientTarif::withTrashed()
            ->where('client_id', $clientId)
            ->whereIn('ville_id', $villeIds)
            ->get()
            ->keyBy('ville_id');

        $etat = [];
        foreach ($villeIds as $villeId) {
            $ligne = $lignes->get($villeId);
            $etat[$villeId] = [
                'ville_id' => $villeId,
                'statut' => $ligne === null ? 'nouveau' : ($ligne->trashed() ? 'supprime' : 'actif'),
                'tarif_id' => $ligne?->id,
                'livraison' => $ligne?->frais_livraison,
                'refus' => $ligne?->frais_refus,
                'retour' => $ligne?->frais_retour,
            ];
        }

        return $etat;
    }

    /**
     * Négocie des tarifs pour un client sur des villes choisies ou sur une zone entière.
     *
     * @param  list<int>  $villeIds
     * @param  array{livraison: string|float|int, refus: string|float|int, retour: string|float|int}  $montants
     * @return array{crees: int, mises_a_jour: int, restaurees: int, ignorees: int, villes: int}
     */
    public function ajouter(
        int $clientId,
        array $villeIds,
        ?int $zoneId,
        array $montants,
        string $existants = self::EXISTANTS_IGNORER,
        bool $restaurer = true,
    ): array {
        $this->assurerClient($clientId);
        $valeurs = $this->montants($montants);

        if (! in_array($existants, [self::EXISTANTS_IGNORER, self::EXISTANTS_METTRE_A_JOUR], true)) {
            throw ValidationException::withMessages(['existants' => 'Choix de traitement des tarifs existants invalide.']);
        }

        $villes = $zoneId !== null
            ? Ville::query()->where('zone_id', $zoneId)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $this->idsUniques($villeIds);

        if ($villes === []) {
            throw ValidationException::withMessages(['ville_ids' => $zoneId !== null
                ? 'Cette zone ne contient aucune ville active.'
                : 'Sélectionnez au moins une ville.']);
        }

        $connues = Ville::query()->whereKey($villes)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $inconnues = array_values(array_diff($villes, $connues));
        if ($inconnues !== []) {
            throw ValidationException::withMessages(['ville_ids' => 'Villes inexistantes ou archivées : '.implode(', ', $inconnues).'.']);
        }

        $resultat = DB::transaction(function () use ($clientId, $villes, $valeurs, $existants, $restaurer): array {
            $lignes = ClientTarif::withTrashed()
                ->where('client_id', $clientId)
                ->whereIn('ville_id', $villes)
                ->lockForUpdate()
                ->get()
                ->keyBy('ville_id');

            $r = ['crees' => 0, 'mises_a_jour' => 0, 'restaurees' => 0, 'ignorees' => 0, 'villes' => count($villes)];

            foreach ($villes as $villeId) {
                $ligne = $lignes->get($villeId);

                if ($ligne === null) {
                    ClientTarif::create($valeurs + ['client_id' => $clientId, 'ville_id' => $villeId]);
                    $r['crees']++;

                    continue;
                }

                if ($ligne->trashed()) {
                    if (! $restaurer) {
                        $r['ignorees']++;

                        continue;
                    }

                    $ligne->restore();
                    $ligne->update($valeurs);
                    $r['restaurees']++;

                    continue;
                }

                if ($existants === self::EXISTANTS_METTRE_A_JOUR) {
                    $ligne->update($valeurs);
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
     * Modifie un lot de lignes ACTIVES (édition individuelle = lot d'une ligne).
     * Toutes les lignes doivent exister, être actives et appartenir au client indiqué.
     *
     * @param  list<array{id: int, livraison: mixed, refus: mixed, retour: mixed}>  $lignes
     */
    public function modifierLot(int $clientId, array $lignes): int
    {
        if ($lignes === []) {
            throw ValidationException::withMessages(['lignes' => 'Aucune ligne à modifier.']);
        }

        $parId = [];
        foreach ($lignes as $i => $ligne) {
            $id = (int) ($ligne['id'] ?? 0);
            if ($id <= 0 || isset($parId[$id])) {
                throw ValidationException::withMessages(["lignes.$i.id" => 'Ligne invalide ou dupliquée.']);
            }
            $parId[$id] = $this->montants($ligne, "lignes.$i.");
        }

        $ecrites = DB::transaction(function () use ($clientId, $parId): int {
            $tarifs = ClientTarif::query()
                ->whereKey(array_keys($parId))
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->get();

            if ($tarifs->count() !== count($parId)) {
                throw ValidationException::withMessages(['lignes' => 'Certaines lignes n\'existent plus ou n\'appartiennent pas à ce client. Rechargez la grille.']);
            }

            $n = 0;
            foreach ($tarifs as $tarif) {
                $tarif->fill($parId[$tarif->id]);
                if ($tarif->isDirty()) {
                    $tarif->save();
                    $n++;
                }
            }

            return $n;
        });

        $this->fees->oublier();

        return $ecrites;
    }

    /**
     * Suppression LOGIQUE d'un lot de tarifs d'un client : les villes repassent au standard
     * pour les futures résolutions. Aucun colis n'est modifié.
     *
     * @param  list<int>  $ids
     */
    public function supprimerLot(int $clientId, array $ids): int
    {
        $ids = $this->idsUniques($ids);

        if ($ids === []) {
            throw ValidationException::withMessages(['ids' => 'Aucun tarif sélectionné.']);
        }

        $supprimes = DB::transaction(function () use ($clientId, $ids): int {
            $tarifs = ClientTarif::query()
                ->whereKey($ids)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->get();

            if ($tarifs->count() !== count($ids)) {
                throw ValidationException::withMessages(['ids' => 'Certains tarifs n\'existent plus ou n\'appartiennent pas à ce client. Rechargez la grille.']);
            }

            $tarifs->each->delete();

            return $tarifs->count();
        });

        $this->fees->oublier();

        return $supprimes;
    }

    /**
     * Normalise et valide trois montants : obligatoires, numériques, ≥ 0, 2 décimales max.
     *
     * @return array{frais_livraison: string, frais_refus: string, frais_retour: string}
     */
    public function montants(array $source, string $prefixe = ''): array
    {
        $sortie = [];
        foreach (['livraison' => 'frais_livraison', 'refus' => 'frais_refus', 'retour' => 'frais_retour'] as $cle => $colonne) {
            $valeur = $source[$cle] ?? null;
            $texte = is_string($valeur) ? trim(str_replace(',', '.', $valeur)) : $valeur;

            if ($texte === null || $texte === '' || ! is_numeric($texte)) {
                throw ValidationException::withMessages([$prefixe.$cle => 'Montant obligatoire (0 accepté).']);
            }

            $nombre = (float) $texte;
            if ($nombre < 0) {
                throw ValidationException::withMessages([$prefixe.$cle => 'Un tarif ne peut pas être négatif.']);
            }

            if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $texte)) {
                throw ValidationException::withMessages([$prefixe.$cle => 'Montant invalide : nombre positif, 2 décimales maximum.']);
            }

            $sortie[$colonne] = number_format($nombre, 2, '.', '');
        }

        return $sortie;
    }

    private function assurerClient(int $clientId): void
    {
        // Défense en profondeur : Gate::before laisse passer l'administration, la règle métier reste ici.
        if ($clientId <= 0 || ! User::clients()->whereKey($clientId)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Ce compte n\'est pas un client marchand.']);
        }
    }

    /** @return list<int> */
    private function idsUniques(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
    }
}
