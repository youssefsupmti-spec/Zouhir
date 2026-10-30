<?php

declare(strict_types=1);

namespace App\Domain\Commande\Support;

use App\Models\User;

/**
 * Restrictions et périmètres de données pilotés par Permissions Spatie.
 * Indépendant du libellé exact du rôle, autorisant la personnalisation complète des rôles (Custom Roles).
 */
final class PerimetreColis
{
    public const PROPRE_COMPTE = 'ActAsClient:Command';
    public const COLIS_AFFECTES = 'ActAsDriver:Command';
    public const GESTION_AGENCE = 'ActAsAgency:Command';
    public const ADMINISTRATION = 'ActAsAdmin:Command';

    /**
     * L'utilisateur agit-il en tant que Marchand / Client (scopé à ses propres colis) ?
     */
    public static function compteVendeur(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            return $user->hasPermissionTo(self::PROPRE_COMPTE);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * L'utilisateur agit-il en tant que Livreur (scopé aux colis affectés à sa tournée) ?
     */
    public static function colisAffectes(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            return $user->hasPermissionTo(self::COLIS_AFFECTES);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * L'utilisateur agit-il en tant qu'Agence ?
     */
    public static function agence(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            return $user->hasPermissionTo(self::GESTION_AGENCE);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * L'utilisateur dispose-t-il des droits d'administration ?
     */
    public static function administration(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            return $user->hasPermissionTo(self::ADMINISTRATION);
        } catch (\Throwable) {
            return false;
        }
    }
}

