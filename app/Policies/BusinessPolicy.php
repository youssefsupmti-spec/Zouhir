<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\Business;
use App\Models\User;

/**
 * Enseignes : gestion globale via manage_users, sinon le marchand gère les siennes (user_id).
 */
class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MANAGE_USERS->value) || $user->isClient();
    }

    public function view(User $user, Business $business): bool
    {
        return $this->owns($user, $business);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Business $business): bool
    {
        return $this->owns($user, $business);
    }

    public function delete(User $user, Business $business): bool
    {
        return $this->owns($user, $business);
    }

    private function owns(User $user, Business $business): bool
    {
        return $user->can(PermissionEnum::MANAGE_USERS->value)
            || ($user->isClient() && (int) $business->user_id === (int) $user->id);
    }
}
