<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Commun\Enums;

/**
 * PROVENANCE du tarif client figé sur un colis (`commands.type_tarif_client`).
 *
 * Adapté de WinningExpress (App\Domain\Tarifs\Commun\Enums\TypeTarif) sans
 * dépendance Filament (HasColor / HasIcon / HasLabel retirés : les badges sont
 * rendus côté React). Les VALEURS stockées sont conservées à l'identique.
 *
 * Ordre de la cascade : manuel → promo → client → standard → missing.
 * `Promo` est conservé pour la compatibilité du contrat et des données
 * historiques ; Zouhir n'a pas de domaine Promotion à ce jour, aucun colis
 * n'est donc résolu en `promo`.
 */
enum TypeTarif: string
{
    /** Montant saisi à la main par l'administration. Jamais recalculé automatiquement. */
    case Manuel = 'manual';

    /** Tarif promotionnel (non implémenté dans Zouhir : réservé). */
    case Promo = 'promo';

    /** Barème négocié pour ce client et cette ville (`client_tarifs`). */
    case Client = 'client';

    /** Barème de la ville (`standard_tarifs`). */
    case Standard = 'standard';

    /** Aucun barème fiable lors de la résolution : anomalie à traiter. */
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Manuel => 'Manuel',
            self::Promo => 'Promo',
            self::Client => 'Négocié',
            self::Standard => 'Standard',
            self::Missing => 'Manquant',
        };
    }

    public function estManuel(): bool
    {
        return $this === self::Manuel;
    }

    /** Le montant associé provient-il d'un barème réellement trouvé ? */
    public function estResolu(): bool
    {
        return $this !== self::Missing;
    }

    /**
     * Lit un libellé de n'importe quel vocabulaire historique (repris de WinningExpress).
     *
     * Rend `null` sur un libellé inconnu : un marqueur inconnu n'est jamais
     * converti de force en type de tarif.
     */
    public static function depuis(self|string|null $valeur): ?self
    {
        if ($valeur instanceof self) {
            return $valeur;
        }

        if ($valeur === null || $valeur === '') {
            return null;
        }

        return match ($valeur) {
            'manual', 'manuel', 'custom' => self::Manuel,
            'promo', 'promo_tarifs' => self::Promo,
            'client', 'client_tarifs', 'clientfees', 'specific', 'App\Models\ClientTarif' => self::Client,
            'standard', 'standard_tarifs', 'gshippingfees', 'App\Models\StandardTarif' => self::Standard,
            'missing' => self::Missing,
            default => null,
        };
    }

    public static function libelle(?string $valeur): string
    {
        return self::depuis($valeur)?->label() ?? '—';
    }
}
