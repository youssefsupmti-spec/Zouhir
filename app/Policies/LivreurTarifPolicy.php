<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Models\User;

/**
 * Commissions spécifiques des livreurs (`livreur_tarifs`).
 * Adapté de WinningExpress LivreurTarifPolicy vers les permissions `tarifs_livreur:*`.
 * Un livreur ne modifie jamais une commission : il ne détient aucune de ces permissions
 * par défaut, et `create/update/delete` refusent explicitement un compte livreur
 * même si une permission lui était déléguée par erreur.
 */
class LivreurTarifPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TARIFS_LIVREUR_VOIR->value);
    }

    public function create(User $user): bool
    {
        return $this->gestionnaire($user) && $user->can(Permission::TARIFS_LIVREUR_CREER->value);
    }

    public function update(User $user, mixed $tarif = null): bool
    {
        return $this->gestionnaire($user) && $user->can(Permission::TARIFS_LIVREUR_MODIFIER->value);
    }

    public function delete(User $user, mixed $tarif = null): bool
    {
        return $this->gestionnaire($user) && $user->can(Permission::TARIFS_LIVREUR_SUPPRIMER->value);
    }

    public function restore(User $user, mixed $tarif = null): bool
    {
        return $this->delete($user, $tarif);
    }

    /** Un compte livreur ne gère jamais les commissions (y compris les siennes). */
    private function gestionnaire(User $user): bool
    {
        return ! $user->isDriver();
    }
}
