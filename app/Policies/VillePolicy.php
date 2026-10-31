<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\User;
use App\Models\Ville;

/**
 * Référentiel national des villes : lecture pour tout utilisateur connecté,
 * écriture réservée à access_admin.
 */
class VillePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::ACCESS_ADMIN->value);
    }

    public function update(User $user, Ville $ville): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Ville $ville): bool
    {
        return $this->create($user);
    }
}
