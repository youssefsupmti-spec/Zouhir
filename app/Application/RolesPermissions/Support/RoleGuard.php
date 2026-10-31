<?php

declare(strict_types=1);

namespace App\Application\RolesPermissions\Support;

use App\Domain\RolesPermissions\Support\PermissionCatalog;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Règles de sécurité métier de l'administration des rôles (s'ajoutent aux Policies).
 *
 * Délégation : un acteur ne peut accorder, retirer ou transmettre (via affectation d'un
 * utilisateur) QUE des permissions qu'il détient lui-même (`$user->can()`, donc Spatie +
 * Gate::before pour le super-administrateur). Empêche toute auto-escalade de privilèges.
 */
final class RoleGuard
{
    /** @param list<string> $permissions */
    public static function assertCanDelegate(User $actor, array $permissions, string $field = 'permissions'): void
    {
        $refused = array_values(array_filter($permissions, fn (string $p) => ! $actor->can($p)));

        if ($refused !== []) {
            throw ValidationException::withMessages([
                $field => 'Vous ne pouvez pas déléguer des permissions que vous ne détenez pas : '.implode(', ', $refused).'.',
            ]);
        }
    }

    /** L'acteur doit détenir toutes les permissions du rôle pour le modifier, le supprimer ou gérer ses membres. */
    public static function assertCanManage(User $actor, Role $role, string $field = 'role'): void
    {
        self::assertCanDelegate($actor, $role->permissions()->pluck('name')->all(), $field);
    }

    public static function isAdminRole(Role $role): bool
    {
        return $role->name === PermissionCatalog::adminRole() && $role->guard_name === 'web';
    }
}
