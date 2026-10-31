<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\Command;
use App\Models\User;

/**
 * Colis (commands). Les administrateurs passent par Gate::before.
 * Le périmètre des données est porté par Command::visibleTo / isVisibleTo (ActAs*).
 */
class CommandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::TRACK_COLIS->value)
            || $user->can(PermissionEnum::MANAGE_COLIS->value);
    }

    public function view(User $user, Command $command): bool
    {
        return $this->viewAny($user) && $command->isVisibleTo($user);
    }

    /** Création réservée au périmètre marchand (le client_id est forcé côté serveur). */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CREATE_COLIS->value) && $user->isClient();
    }

    public function update(User $user, Command $command): bool
    {
        return $user->can(PermissionEnum::MANAGE_COLIS->value)
            && $user->isClient()
            && (int) $command->client_id === (int) $user->id;
    }

    /** Changement de statut : administration (Gate::before) ou livreur affecté au colis. */
    public function updateState(User $user, Command $command): bool
    {
        return $user->isDriver() && (int) $command->livreur_id === (int) $user->id;
    }

    public function delete(User $user, Command $command): bool
    {
        return $this->update($user, $command);
    }
}
