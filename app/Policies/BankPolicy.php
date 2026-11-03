<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Models\User;

/**
 * Référentiel des banques : administration système (`access_admin`), comme les villes et
 * les zones. Les titulaires de RIB n'y accèdent pas : ils choisissent leur banque depuis
 * la page « Comptes bancaires ». Le super-administrateur passe par Gate::before.
 */
class BankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ACCESS_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ACCESS_ADMIN->value);
    }

    public function update(User $user, mixed $bank = null): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, mixed $bank = null): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, mixed $bank = null): bool
    {
        return $this->create($user);
    }
}
