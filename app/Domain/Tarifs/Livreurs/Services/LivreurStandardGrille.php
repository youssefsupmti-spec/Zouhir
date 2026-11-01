<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Livreurs\Services;

use App\Domain\Tarifs\Commun\Services\BaremeParVille;
use App\Models\LivreurStandardTarif;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Barème STANDARD des commissions livreurs (`livreur_standard_tarifs`) : commission par
 * défaut d'une ville quand le livreur n'a pas de commission spécifique.
 *
 * Distinct de StandardTarifGrille (frais facturés au marchand) : autre table, autres colonnes.
 */
final class LivreurStandardGrille extends BaremeParVille
{
    protected function table(): string
    {
        return 'livreur_standard_tarifs';
    }

    protected function modele(): string
    {
        return LivreurStandardTarif::class;
    }

    protected function colonnes(): array
    {
        return ['livraison' => 'commission_livraison', 'refus' => 'commission_refus'];
    }

    /** Nombre de commissions spécifiques actives par ville (comparaison avec le standard). */
    protected function enrichir(QueryBuilder $requete): void
    {
        $requete->selectSub(
            fn ($q) => $q->from('livreur_tarifs')
                ->whereColumn('livreur_tarifs.ville_id', 'villes.id')
                ->whereNull('livreur_tarifs.deleted_at')
                ->selectRaw('COUNT(*)'),
            'nb_specifiques',
        );
    }

    protected function extra(object $ligne): array
    {
        return ['nb_specifiques' => (int) ($ligne->nb_specifiques ?? 0)];
    }
}
