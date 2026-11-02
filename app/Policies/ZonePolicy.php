<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Models\User;

/**
 * Zones logistiques : référentiel national, comme les villes. Administration système
 * (`access_admin`) ; le super-administrateur passe par Gate::before.
 */
class ZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ACCESS_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ACCESS_ADMIN->value);
    }

    public function update(User $user, mixed $zone = null): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, mixed $zone = null): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, mixed $zone = null): bool
    {
        return $this->create($user);
    }
}
