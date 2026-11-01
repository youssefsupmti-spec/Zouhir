<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Standard\Services;

use App\Domain\Tarifs\Commun\Services\BaremeParVille;
use App\Models\StandardTarif;

/**
 * Grille « Tarifs de livraison » : barème standard FACTURÉ AU MARCHAND (`standard_tarifs`),
 * appliqué quand aucun tarif négocié client n'existe ({@see \App\Domain\Tarifs\Commun\Services\FeeResolutionService::clientFees()}).
 *
 * Source de vérité : `standard_tarifs.ville_id` UNIQUE (choix Zouhir du Sprint 28 ;
 * la relation inversée `villes.standard_tarif_id` de WinningExpress n'est pas réintroduite).
 */
final class StandardTarifGrille extends BaremeParVille
{
    protected function table(): string
    {
        return 'standard_tarifs';
    }

    protected function modele(): string
    {
        return StandardTarif::class;
    }

    protected function colonnes(): array
    {
        return ['livraison' => 'frais_livraison', 'refus' => 'frais_refus', 'retour' => 'frais_retour'];
    }

    protected function colonneDelai(): ?string
    {
        return 'delai';
    }
}
