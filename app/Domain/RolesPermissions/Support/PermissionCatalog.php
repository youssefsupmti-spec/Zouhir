<?php

declare(strict_types=1);

namespace App\Domain\RolesPermissions\Support;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;

/**
 * Catalogue central des permissions de la plateforme (source unique d'affichage).
 *
 * - Les permissions « cataloguées » sont celles déclarées dans l'Enum Permission :
 *   c'est le seul mécanisme de déclaration supporté par le code.
 * - Les modules sont déclarés explicitement (jamais déduits des chaînes).
 * - `enforced_by` décrit les règles d'autorisation RÉELLEMENT présentes dans le code
 *   (vérifié le 31/10/2026). Une liste vide = permission déclarée mais reliée à aucune règle.
 */
final class PermissionCatalog
{
    public const UNCATALOGUED_MODULE = 'hors_catalogue';

    /** @var array<string, array{label: string, description: string}> */
    public const MODULES = [
        'colis' => [
            'label' => 'Colis & Expéditions',
            'description' => 'Consultation, création et gestion des expéditions.',
        ],
        'finances' => [
            'label' => 'Finances & CRBT',
            'description' => 'Coordonnées bancaires et données financières.',
        ],
        'pilotage' => [
            'label' => 'Pilotage & Exports',
            'description' => 'Tableau de bord et exports de données.',
        ],
        'administration' => [
            'label' => 'Administration & Comptes',
            'description' => 'Comptes utilisateurs, référentiels et sécurité.',
        ],
        'perimetres' => [
            'label' => 'Périmètres de données',
            'description' => 'Déterminent QUELLES données un compte voit (isolation).',
        ],
        self::UNCATALOGUED_MODULE => [
            'label' => 'Hors catalogue',
            'description' => 'Présentes en base mais non déclarées dans le code.',
        ],
    ];

    /**
     * @return array<string, array{module: string, description: string, enforced_by: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            PermissionEnum::TRACK_COLIS->value => [
                'module' => 'colis',
                'description' => 'Ouvre la liste des colis (limitée au périmètre de données).',
                'enforced_by' => ['CommandPolicy::viewAny'],
            ],
            PermissionEnum::CREATE_COLIS->value => [
                'module' => 'colis',
                'description' => 'Créer un colis pour son propre compte marchand.',
                'enforced_by' => ['CommandPolicy::create'],
            ],
            PermissionEnum::MANAGE_COLIS->value => [
                'module' => 'colis',
                'description' => 'Modifier ou archiver ses propres colis ; ouvre aussi la liste.',
                'enforced_by' => ['CommandPolicy::viewAny', 'CommandPolicy::update', 'CommandPolicy::delete'],
            ],
            PermissionEnum::VIEW_FINANCES->value => [
                'module' => 'finances',
                'description' => 'Consulter et gérer ses propres coordonnées bancaires (RIB).',
                'enforced_by' => ['CompteBancairePolicy', 'CompteBancaire::visibleTo'],
            ],
            PermissionEnum::VIEW_DASHBOARD->value => [
                'module' => 'pilotage',
                'description' => 'Accès au tableau de bord (DashboardPolicy existante non branchée sur la route).',
                'enforced_by' => [],
            ],
            PermissionEnum::EXPORT_EXCEL->value => [
                'module' => 'pilotage',
                'description' => 'Export Excel (fonctionnalité non implémentée à ce jour).',
                'enforced_by' => [],
            ],
            PermissionEnum::MANAGE_USERS->value => [
                'module' => 'administration',
                'description' => 'Gérer les comptes clients et livreurs, toutes les enseignes et tous les RIB.',
                'enforced_by' => ['UserPolicy', 'BusinessPolicy', 'CompteBancairePolicy'],
            ],
            PermissionEnum::ACCESS_ADMIN->value => [
                'module' => 'administration',
                'description' => 'Administration système : référentiels nationaux, rôles et permissions.',
                'enforced_by' => ['VillePolicy', 'RolePolicy', 'PermissionPolicy'],
            ],
            PermissionEnum::ACT_AS_ADMIN->value => [
                'module' => 'perimetres',
                'description' => 'Super-administration : contourne toutes les règles (Gate::before).',
                'enforced_by' => ['Gate::before', 'User::isAdmin'],
            ],
            PermissionEnum::ACT_AS_CLIENT->value => [
                'module' => 'perimetres',
                'description' => 'Périmètre marchand : uniquement ses propres colis, enseignes et comptes.',
                'enforced_by' => ['PerimetreColis', 'Command::visibleTo', 'User::isClient'],
            ],
            PermissionEnum::ACT_AS_DRIVER->value => [
                'module' => 'perimetres',
                'description' => 'Périmètre livreur : colis affectés et changement de leur statut.',
                'enforced_by' => ['PerimetreColis', 'Command::visibleTo', 'CommandPolicy::updateState'],
            ],
            PermissionEnum::ACT_AS_AGENCY->value => [
                'module' => 'perimetres',
                'description' => 'Périmètre agence : aucune clé de rattachement agence ↔ données à ce jour.',
                'enforced_by' => [],
            ],
        ];
    }

    /** Permissions qui ne peuvent jamais être supprimées ni retirées du rôle administrateur système. */
    public static function critical(): array
    {
        return [PermissionEnum::ACT_AS_ADMIN->value, PermissionEnum::ACCESS_ADMIN->value];
    }

    public static function isCatalogued(string $permission): bool
    {
        return array_key_exists($permission, self::definitions());
    }

    public static function isCritical(string $permission): bool
    {
        return in_array($permission, self::critical(), true);
    }

    public static function moduleOf(string $permission): string
    {
        return self::definitions()[$permission]['module'] ?? self::UNCATALOGUED_MODULE;
    }

    /**
     * Métadonnées d'affichage d'une permission.
     *
     * @return array{label: string, description: ?string, module: string, catalogued: bool, critical: bool, enforced_by: list<string>, implemented: bool}
     */
    public static function describe(string $permission): array
    {
        $definition = self::definitions()[$permission] ?? null;
        $enum = PermissionEnum::tryFrom($permission);

        return [
            'label' => $enum?->label() ?? $permission,
            'description' => $definition['description'] ?? null,
            'module' => $definition['module'] ?? self::UNCATALOGUED_MODULE,
            'catalogued' => $definition !== null,
            'critical' => self::isCritical($permission),
            'enforced_by' => $definition['enforced_by'] ?? [],
            'implemented' => ($definition['enforced_by'] ?? []) !== [],
        ];
    }

    /** Rôles système = rôles de base déclarés dans l'Enum UserRole (jamais les rôles personnalisés). */
    public static function systemRoles(): array
    {
        return array_map(fn (UserRole $role) => $role->value, UserRole::cases());
    }

    public static function isSystemRole(string $name): bool
    {
        return in_array($name, self::systemRoles(), true);
    }

    public static function adminRole(): string
    {
        return UserRole::ADMIN->value;
    }
}
