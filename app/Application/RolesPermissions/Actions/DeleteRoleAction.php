<?php

declare(strict_types=1);

namespace App\Application\RolesPermissions\Actions;

use App\Application\RolesPermissions\Support\RoleGuard;
use App\Domain\RolesPermissions\Support\PermissionCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Suppression « sans risque » : uniquement un rôle personnalisé sans aucun utilisateur.
 * Les rôles système ne sont jamais supprimés par une opération ordinaire.
 */
final class DeleteRoleAction
{
    public function execute(User $actor, Role $role): void
    {
        if (PermissionCatalog::isSystemRole($role->name)) {
            throw ValidationException::withMessages(['role' => 'Un rôle système ne peut pas être supprimé.']);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Ce rôle est encore attribué à des utilisateurs. Retirez-les avant de le supprimer.',
            ]);
        }

        RoleGuard::assertCanManage($actor, $role);

        // Role::delete() détache les permissions et invalide le cache Spatie ; RoleIds est purgé par l'événement deleted.
        DB::transaction(fn () => $role->delete());
    }
}
