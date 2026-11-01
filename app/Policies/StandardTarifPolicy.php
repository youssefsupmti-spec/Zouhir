<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Models\User;

/**
 * Barème standard de livraison facturé aux marchands (`standard_tarifs`).
 * Permissions Spatie typées `tarifs_standard:*` ; aucun nom de rôle.
 * L'administration système passe par Gate::before (AppServiceProvider).
 * La suppression n'est pas proposée : une ville sans standard ferait passer
 * ses futurs colis en anomalie « missing ».
 */
class StandardTarifPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TARIFS_STANDARD_VOIR->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TARIFS_STANDARD_GERER->value);
    }

    public function update(User $user, mixed $tarif = null): bool
    {
        return $user->can(Permission::TARIFS_STANDARD_GERER->value);
    }
}
