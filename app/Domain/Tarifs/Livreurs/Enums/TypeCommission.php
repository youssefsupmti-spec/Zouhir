<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Livreurs\Enums;

/**
 * PROVENANCE de la commission livreur figée sur un colis (`commands.type_commission_livreur`).
 *
 * Ordre de la cascade : manuel → spécifique livreur → standard livreur → missing.
 * Vocabulaire DISTINCT de TypeTarif (frais client) : les deux domaines ne se mélangent pas.
 */
enum TypeCommission: string
{
    /** Saisie par l'administration. Jamais recalculée automatiquement. */
    case Manuel = 'manual';

    /** Commission propre au livreur pour la ville (`livreur_tarifs`). */
    case Specifique = 'specifique';

    /** Barème standard livreur de la ville (`livreur_standard_tarifs`). */
    case Standard = 'standard';

    /** Aucune commission fiable : anomalie à traiter (jamais assimilée à 0). */
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Manuel => 'Manuelle',
            self::Specifique => 'Spécifique livreur',
            self::Standard => 'Standard livreur',
            self::Missing => 'Manquante',
        };
    }

    public static function depuis(self|string|null $valeur): ?self
    {
        if ($valeur instanceof self) {
            return $valeur;
        }

        return $valeur === null || $valeur === '' ? null : self::tryFrom($valeur);
    }
}
