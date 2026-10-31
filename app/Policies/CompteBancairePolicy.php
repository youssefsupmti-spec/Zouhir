<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\CompteBancaire;
use App\Models\User;

/**
 * Comptes bancaires (RIB) : gestion globale via manage_users,
 * sinon view_finances sur ses propres comptes (owner_id).
 */
class CompteBancairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MANAGE_USERS->value)
            || $user->can(PermissionEnum::VIEW_FINANCES->value);
    }

    public function view(User $user, CompteBancaire $compte): bool
    {
        return $this->owns($user, $compte);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CompteBancaire $compte): bool
    {
        return $this->owns($user, $compte);
    }

    public function delete(User $user, CompteBancaire $compte): bool
    {
        return $this->owns($user, $compte);
    }

    private function owns(User $user, CompteBancaire $compte): bool
    {
        return $user->can(PermissionEnum::MANAGE_USERS->value)
            || ($user->can(PermissionEnum::VIEW_FINANCES->value) && (int) $compte->owner_id === (int) $user->id);
    }
}
