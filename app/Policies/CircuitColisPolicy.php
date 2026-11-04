<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Models\User;

/**
 * Circuit colis (bons, statuts, agences, partenaires) et reprise des données : administration
 * (`access_admin`). Les espaces marchand / livreur viendront avec leurs propres règles.
 */
class CircuitColisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ACCESS_ADMIN->value);
    }

    public function view(User $user, mixed $modele = null): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, mixed $modele = null): bool
    {
        return $this->viewAny($user);
    }
}
