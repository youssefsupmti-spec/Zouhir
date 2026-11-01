<?php

declare(strict_types=1);

namespace App\Application\Tarifs\Actions;

use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Domain\Tarifs\Livreurs\Enums\TypeCommission;
use App\Domain\Tarifs\Livreurs\Support\CommissionResolue;
use App\Models\Command;
use Illuminate\Validation\ValidationException;

/**
 * SNAPSHOT de la commission livreur d'un colis : `frais_livraison_livreur` + `type_commission_livreur`.
 *
 * Point métier UNIQUE (création, modification, changement de statut / livreur dans
 * ColisController). Pendant de {@see FigerTarifClientColis} pour l'autre côté de la marge :
 * les deux snapshots sont posés indépendamment, chacun par sa propre cascade.
 *
 * Règles (adaptées de WinningExpress CommissionLivreurSync::pourChangement) :
 *  - la commission est (re)résolue quand le LIVREUR ou la VILLE change ;
 *  - jamais sur un colis finalisé (livré, retour, annulé) : Zouhir n'a pas encore de
 *    règlement livreur, l'état finalisé est donc la protection d'une commission due
 *    (une saisie manuelle différente y est refusée) ;
 *  - jamais sur une commission MANUELLE ;
 *  - sans livreur : 0.00 et provenance NULL (rien n'est dû) ;
 *  - sans barème : 0.00 + provenance `missing` (anomalie explicite, jamais un montant inventé).
 *
 * Aucun recalcul rétroactif : modifier une grille ne touche aucun colis existant.
 */
final class FigerCommissionLivreurColis
{
    public function __construct(private readonly FeeResolutionService $fees) {}

    /** À la création (avant save). `$montantManuel` non null = saisie de l'administration. */
    public function aLaCreation(Command $colis, ?string $montantManuel = null): ?CommissionResolue
    {
        if ($montantManuel !== null) {
            return $this->poserManuel($colis, $montantManuel);
        }

        return $this->poserResolu($colis);
    }

    /**
     * À la modification ou au changement de statut (avant save, attributs déjà remplis).
     * Retourne null si le snapshot est laissé intact.
     */
    public function aLaModification(Command $colis, ?string $montantManuel = null): ?CommissionResolue
    {
        $finalise = ! in_array((string) $colis->getOriginal('state_group'), FigerTarifClientColis::ETATS_MODIFIABLES, true);
        $actuel = CommissionResolue::montant($colis->getOriginal('frais_livraison_livreur') ?? 0);
        $saisieDifferente = $montantManuel !== null && CommissionResolue::montant($montantManuel) !== $actuel;

        if ($finalise) {
            if ($saisieDifferente) {
                throw ValidationException::withMessages([
                    'frais_livraison_livreur' => 'Colis finalisé : sa commission livreur ne peut plus être modifiée sans procédure de correction.',
                ]);
            }

            // Snapshot figé : un changement de livreur ou de ville ne le recalcule jamais.
            return null;
        }

        if ($saisieDifferente) {
            return $this->poserManuel($colis, $montantManuel);
        }

        $manuel = TypeCommission::depuis($colis->getOriginal('type_commission_livreur')) === TypeCommission::Manuel;

        if (! $colis->isDirty(['livreur_id', 'destinataire_ville_id']) || $manuel) {
            return null;
        }

        return $this->poserResolu($colis);
    }

    private function poserManuel(Command $colis, string $montant): CommissionResolue
    {
        $colis->frais_livraison_livreur = CommissionResolue::montant($montant);
        $colis->type_commission_livreur = TypeCommission::Manuel->value;

        return new CommissionResolue(TypeCommission::Manuel, CommissionResolue::montant($montant), null, null,
            ((int) $colis->livreur_id) ?: null, ((int) $colis->destinataire_ville_id) ?: null);
    }

    private function poserResolu(Command $colis): ?CommissionResolue
    {
        $livreurId = ((int) $colis->livreur_id) ?: null;

        if ($livreurId === null) {
            $colis->frais_livraison_livreur = '0.00';
            $colis->type_commission_livreur = null;

            return null;
        }

        $commission = $this->fees->driverFees(
            $livreurId,
            ((int) $colis->destinataire_ville_id) ?: ($colis->destinataire_ville ?: null),
        );

        // Colonne NOT NULL : 0.00 en base, mais la provenance `missing` dit qu'aucun barème n'existe.
        $colis->frais_livraison_livreur = $commission->livraison ?? '0.00';
        $colis->type_commission_livreur = $commission->type->value;

        return $commission;
    }
}
