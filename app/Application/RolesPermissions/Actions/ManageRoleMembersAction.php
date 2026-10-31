<?php

declare(strict_types=1);

namespace App\Application\RolesPermissions\Actions;

use App\Application\RolesPermissions\Support\RoleGuard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Affectation / retrait d'un utilisateur à un rôle (assignRole / removeRole Spatie).
 */
final class ManageRoleMembersAction
{
    public function assign(User $actor, Role $role, User $member): void
    {
        // Affecter un rôle revient à déléguer toutes ses permissions.
        RoleGuard::assertCanManage($actor, $role, 'email');

        if ($member->hasRole($role)) {
            throw ValidationException::withMessages(['email' => 'Cet utilisateur possède déjà ce rôle.']);
        }

        DB::transaction(fn () => $member->assignRole($role));
    }

    public function remove(User $actor, Role $role, User $member): void
    {
        RoleGuard::assertCanManage($actor, $role, 'member');

        if ($member->is($actor)) {
            throw ValidationException::withMessages([
                'member' => 'Vous ne pouvez pas vous retirer vous-même un rôle (risque de perte d\'accès).',
            ]);
        }

        if (RoleGuard::isAdminRole($role) && $role->users()->count() <= 1) {
            throw ValidationException::withMessages([
                'member' => 'Impossible de retirer le dernier administrateur de la plateforme.',
            ]);
        }

        DB::transaction(fn () => $member->removeRole($role));
    }
}
