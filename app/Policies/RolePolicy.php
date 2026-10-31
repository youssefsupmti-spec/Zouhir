<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\RolesPermissions\Support\PermissionCatalog;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Administration des rôles : permission existante `access_admin` (Administration système).
 * Le super-administrateur passe par Gate::before. Les règles fines (délégation, rôle
 * système, dernier administrateur) sont appliquées par les Actions (RoleGuard).
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ACCESS_ADMIN->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->viewAny($user) && ! PermissionCatalog::isSystemRole($role->name);
    }

    public function manageMembers(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }
}
