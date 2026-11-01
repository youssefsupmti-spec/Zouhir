<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/** Barème standard des commissions livreurs : mêmes droits que les commissions spécifiques. */
class LivreurStandardTarifPolicy
{
    public function __construct(private readonly LivreurTarifPolicy $livreurs) {}

    public function viewAny(User $user): bool
    {
        return $this->livreurs->viewAny($user);
    }

    public function update(User $user, mixed $tarif = null): bool
    {
        return $this->livreurs->update($user);
    }
}
