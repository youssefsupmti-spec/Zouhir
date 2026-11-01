<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Livreurs\Enums;

/**
 * PROVENANCE de la commission livreur figée sur un colis (`commands.type_commission_livreur`).
 *
 * Ordre de la cascade : manuel → spécifique livreur → missing.
 * Il n'existe PAS de barème standard des commissions (décision du propriétaire, 01/11/2026).
 * Vocabulaire DISTINCT de TypeTarif (frais client) : les deux domaines ne se mélangent pas.
 */
enum TypeCommission: string
{
    /** Saisie par l'administration. Jamais recalculée automatiquement. */
    case Manuel = 'manual';

    /** Commission propre au livreur pour la ville (`livreur_tarifs`). */
    case Specifique = 'specifique';

    /**
     * Valeur HISTORIQUE uniquement : posée par la première version du Sprint 29 (barème standard
     * livreur retiré le 01/11/2026). Plus jamais produite ; conservée pour relire les colis concernés.
     */
    case Standard = 'standard';

    /** Aucune commission fiable : anomalie à traiter (jamais assimilée à 0). */
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Manuel => 'Manuelle',
            self::Specifique => 'Spécifique livreur',
            self::Standard => 'Standard livreur (historique)',
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
