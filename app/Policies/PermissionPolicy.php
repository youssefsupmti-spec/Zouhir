<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\RolesPermissions\Support\PermissionCatalog;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Catalogue des permissions : consultation et synchronisation via `access_admin`.
 * Aucune création arbitraire ; suppression limitée aux permissions hors catalogue.
 */
class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ACCESS_ADMIN->value);
    }

    public function synchronize(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->viewAny($user) && ! PermissionCatalog::isCatalogued($permission->name);
    }
}
