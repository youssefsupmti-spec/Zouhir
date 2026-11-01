<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Commun\Services;

use App\Domain\Tarifs\Commun\Enums\TypeTarif;
use App\Domain\Tarifs\Commun\Support\TarifResolu;
use App\Domain\Tarifs\Livreurs\Enums\TypeCommission;
use App\Domain\Tarifs\Livreurs\Support\CommissionResolue;
use App\Domain\Villes\Support\VilleRef;
use App\Models\ClientTarif;
use App\Models\LivreurStandardTarif;
use App\Models\LivreurTarif;
use App\Models\StandardTarif;

/**
 * Point CENTRAL de résolution des frais client d'un colis.
 *
 * Adapté de WinningExpress (App\Domain\Tarifs\Commun\Services\FeeResolutionService).
 *
 * Cascade :
 *   1. MANUEL   — court-circuit : un colis dont `type_tarif_client = manual` garde son montant ({@see pourColis()}).
 *   2. PROMO    — non implémentée (Zouhir n'a pas de domaine Promotion) ; le type reste réservé.
 *   3. CLIENT   — ligne ACTIVE `client_tarifs` (client_id, ville_id). Prioritaire même à 0.00.
 *   4. STANDARD — ligne `standard_tarifs` de la ville.
 *   5. MISSING  — aucun barème : anomalie explicite, montants `null` (jamais 0, jamais 25 MAD).
 *
 * Différence assumée avec WinningExpress : l'absence ne LÈVE pas d'exception ici,
 * elle renvoie un résultat `missing`. Les appelants qui doivent bloquer (facturation)
 * utilisent {@see exiger()}.
 *
 * COMMISSIONS LIVREURS ({@see driverFees()}, comme WinningExpress) : domaine SÉPARÉ, caches
 * séparés, objet de résultat séparé ({@see CommissionResolue}). Les deux cascades ne se
 * croisent jamais : aucun montant client n'alimente une commission, et inversement.
 *
 * Service lié en « scoped » (AppServiceProvider) : les caches vivent le temps d'une
 * requête ou d'une commande ; l'ABSENCE est mémorisée aussi (clés = identifiants).
 */
final class FeeResolutionService
{
    /** @var array<string, ClientTarif|null> "clientId|villeId" */
    private array $clientRows = [];

    /** @var array<int, StandardTarif|null> villeId */
    private array $standardRows = [];

    /** @var array<string, LivreurTarif|null> "livreurId|villeId" */
    private array $livreurRows = [];

    /** @var array<int, LivreurStandardTarif|null> villeId */
    private array $livreurStandardRows = [];

    public function __construct(private readonly VilleRef $villes) {}

    /**
     * Frais client d'un COLIS (modèle Command ou tout objet portant les mêmes attributs).
     *
     * Un colis au tarif MANUEL n'est jamais résolu : son montant figé est rendu tel quel.
     */
    public function pourColis(object $colis): TarifResolu
    {
        $clientId = ((int) ($colis->client_id ?? 0)) ?: null;
        $villeId = $this->villeDuColis($colis);

        if (TypeTarif::depuis($colis->type_tarif_client ?? null)?->estManuel()) {
            return new TarifResolu(
                TypeTarif::Manuel,
                TarifResolu::montant($colis->frais_livraison_client ?? 0),
                null,
                null,
                null,
                $clientId,
                $villeId,
            );
        }

        return $this->clientFees($clientId, $villeId);
    }

    /**
     * Résolution pour un couple (client, ville). La ville accepte un id ou un libellé
     * (traduit par {@see VilleRef} : un libellé ambigu ou inconnu donne `missing`).
     */
    public function clientFees(?int $clientId, int|string|null $ville): TarifResolu
    {
        $villeId = is_int($ville) ? $ville : $this->villes->id($ville);

        if ($villeId === null) {
            return TarifResolu::manquant($clientId, null);
        }

        if ($clientId !== null) {
            $negocie = $this->clientRow($clientId, $villeId);

            if ($negocie !== null) {
                return new TarifResolu(
                    TypeTarif::Client,
                    TarifResolu::montant($negocie->frais_livraison),
                    TarifResolu::montant($negocie->frais_refus),
                    TarifResolu::montant($negocie->frais_retour),
                    (int) $negocie->getKey(),
                    $clientId,
                    $villeId,
                );
            }
        }

        $standard = $this->standardRow($villeId);

        if ($standard !== null) {
            return new TarifResolu(
                TypeTarif::Standard,
                TarifResolu::montant($standard->frais_livraison),
                TarifResolu::montant($standard->frais_refus),
                TarifResolu::montant($standard->frais_retour),
                (int) $standard->getKey(),
                $clientId,
                $villeId,
            );
        }

        return TarifResolu::manquant($clientId, $villeId);
    }

    /** Comme {@see clientFees()} mais lève si aucun barème n'existe (usage facturation). */
    public function exiger(?int $clientId, int|string|null $ville): TarifResolu
    {
        $tarif = $this->clientFees($clientId, $ville);

        if ($tarif->estManquant()) {
            throw new TarifIntrouvableException($clientId, $ville);
        }

        return $tarif;
    }

    /** Tarif standard seul d'une ville (null = aucun standard). */
    public function standardDe(int $villeId): ?StandardTarif
    {
        return $this->standardRow($villeId);
    }

    /**
     * Pré-charge en MASSE les barèmes d'une liste de couples [clientId, ville] :
     * 2 requêtes (négociés + standards) quelle que soit la taille du lot.
     *
     * @param  iterable<array{0: int|null, 1: int|string|null}>  $paires
     */
    public function warm(iterable $paires): void
    {
        $clientIds = [];
        $villeIds = [];
        $couples = [];

        foreach ($paires as [$clientId, $ville]) {
            $villeId = is_int($ville) ? $ville : $this->villes->id($ville);

            if ($villeId === null) {
                continue;
            }

            $villeIds[$villeId] = true;

            if ($clientId !== null) {
                $clientIds[(int) $clientId] = true;
                $couples[(int) $clientId.'|'.$villeId] = true;
            }
        }

        if ($villeIds === []) {
            return;
        }

        $villeIds = array_keys($villeIds);

        if ($clientIds !== []) {
            $trouves = [];
            foreach (ClientTarif::query()
                ->whereIn('client_id', array_keys($clientIds))
                ->whereIn('ville_id', $villeIds)
                ->get() as $ligne) {
                $trouves[$ligne->client_id.'|'.$ligne->ville_id] = $ligne;
            }

            foreach (array_keys($couples) as $cle) {
                if (! array_key_exists($cle, $this->clientRows)) {
                    $this->clientRows[$cle] = $trouves[$cle] ?? null;
                }
            }
        }

        $aCharger = array_values(array_filter($villeIds, fn (int $id) => ! array_key_exists($id, $this->standardRows)));

        if ($aCharger !== []) {
            $standards = StandardTarif::query()->whereIn('ville_id', $aCharger)->get()->keyBy('ville_id');

            foreach ($aCharger as $villeId) {
                $this->standardRows[$villeId] = $standards->get($villeId);
            }
        }
    }

    /* ══════════════════════ Commissions livreurs ══════════════════════ */

    /**
     * Commission due au livreur d'un couple (livreur, ville).
     *
     * Cascade :
     *   1. SPÉCIFIQUE — ligne ACTIVE `livreur_tarifs` (livreur, ville) ; prioritaire même à 0.00
     *      (= `driverFees()` de WinningExpress, seule étape qui y existe) ;
     *   2. STANDARD   — `livreur_standard_tarifs` de la ville (legacy `shippingfees` dlm = 0) ;
     *   3. MISSING    — aucune commission fiable : montants `null`, jamais 0.
     * Le court-circuit MANUEL est porté par {@see commissionPourColis()}.
     */
    public function driverFees(?int $livreurId, int|string|null $ville): CommissionResolue
    {
        $villeId = $ville === null || $ville === '' ? null : (is_int($ville) ? $ville : $this->villes->id($ville));

        if ($livreurId === null || $villeId === null) {
            return CommissionResolue::manquante($livreurId, $villeId);
        }

        $specifique = $this->livreurRow($livreurId, $villeId);
        if ($specifique !== null) {
            return new CommissionResolue(
                TypeCommission::Specifique,
                CommissionResolue::montant($specifique->commission_livraison),
                CommissionResolue::montant($specifique->commission_refus),
                (int) $specifique->getKey(),
                $livreurId,
                $villeId,
            );
        }

        $standard = $this->livreurStandardRow($villeId);
        if ($standard !== null) {
            return new CommissionResolue(
                TypeCommission::Standard,
                CommissionResolue::montant($standard->commission_livraison),
                CommissionResolue::montant($standard->commission_refus),
                (int) $standard->getKey(),
                $livreurId,
                $villeId,
            );
        }

        return CommissionResolue::manquante($livreurId, $villeId);
    }

    /** Commission d'un COLIS : une commission MANUELLE figée est rendue telle quelle. */
    public function commissionPourColis(object $colis): CommissionResolue
    {
        $livreurId = ((int) ($colis->livreur_id ?? 0)) ?: null;
        $villeId = $this->villeDuColis($colis);

        if (TypeCommission::depuis($colis->type_commission_livreur ?? null) === TypeCommission::Manuel) {
            return new CommissionResolue(
                TypeCommission::Manuel,
                CommissionResolue::montant($colis->frais_livraison_livreur ?? 0),
                null,
                null,
                $livreurId,
                $villeId,
            );
        }

        return $this->driverFees($livreurId, $villeId);
    }

    /** Barème standard livreur seul d'une ville (null = aucun). */
    public function standardLivreurDe(int $villeId): ?LivreurStandardTarif
    {
        return $this->livreurStandardRow($villeId);
    }

    /** Vide les caches : à appeler après toute écriture de barème dans la même requête. */
    public function oublier(): void
    {
        $this->clientRows = [];
        $this->standardRows = [];
        $this->livreurRows = [];
        $this->livreurStandardRows = [];
    }

    private function livreurRow(int $livreurId, int $villeId): ?LivreurTarif
    {
        $cle = $livreurId.'|'.$villeId;

        if (array_key_exists($cle, $this->livreurRows)) {
            return $this->livreurRows[$cle];
        }

        // SoftDeletes : une commission supprimée n'est jamais retenue (scope global).
        return $this->livreurRows[$cle] = LivreurTarif::query()->pour($livreurId, $villeId)->first();
    }

    private function livreurStandardRow(int $villeId): ?LivreurStandardTarif
    {
        if (array_key_exists($villeId, $this->livreurStandardRows)) {
            return $this->livreurStandardRows[$villeId];
        }

        return $this->livreurStandardRows[$villeId] = LivreurStandardTarif::query()->where('ville_id', $villeId)->first();
    }

    private function villeDuColis(object $colis): ?int
    {
        $villeId = (int) ($colis->destinataire_ville_id ?? 0);

        if ($villeId > 0) {
            return $villeId;
        }

        $libelle = (string) ($colis->destinataire_ville ?? '');

        return $libelle === '' ? null : $this->villes->id($libelle);
    }

    private function clientRow(int $clientId, int $villeId): ?ClientTarif
    {
        $cle = $clientId.'|'.$villeId;

        if (array_key_exists($cle, $this->clientRows)) {
            return $this->clientRows[$cle];
        }

        // SoftDeletes : une ligne supprimée n'est jamais retenue (scope global).
        return $this->clientRows[$cle] = ClientTarif::query()->pour($clientId, $villeId)->first();
    }

    private function standardRow(int $villeId): ?StandardTarif
    {
        if (array_key_exists($villeId, $this->standardRows)) {
            return $this->standardRows[$villeId];
        }

        return $this->standardRows[$villeId] = StandardTarif::query()->where('ville_id', $villeId)->first();
    }
}
