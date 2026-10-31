<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Models\ClientTarif;
use App\Models\User;

/**
 * Autorisations du module Tarifs Clients (Sprint 28).
 *
 * Adapté de WinningExpress (ClientTarifPolicy, permissions `*:ClientTarif`) vers les
 * permissions Spatie typées de Zouhir (`tarifs_client:*`). Aucun nom de rôle.
 * L'administration système passe par Gate::before (AppServiceProvider).
 *
 * Les grilles sont des données commerciales de l'agence : un compte marchand n'y a
 * pas accès (il ne détient aucune de ces permissions par défaut). Si une permission
 * lui était déléguée par erreur, `view` le limite tout de même à SES propres lignes.
 */
class ClientTarifPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TARIFS_CLIENT_VOIR->value);
    }

    public function view(User $user, ClientTarif $tarif): bool
    {
        return $this->viewAny($user) && $this->dansPerimetre($user, (int) $tarif->client_id);
    }

    /** Consulter la grille d'un client donné. */
    public function viewClient(User $user, User $client): bool
    {
        return $this->viewAny($user) && $this->dansPerimetre($user, (int) $client->id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TARIFS_CLIENT_CREER->value);
    }

    public function update(User $user, ?ClientTarif $tarif = null): bool
    {
        return $user->can(Permission::TARIFS_CLIENT_MODIFIER->value);
    }

    public function delete(User $user, ?ClientTarif $tarif = null): bool
    {
        return $user->can(Permission::TARIFS_CLIENT_SUPPRIMER->value);
    }

    /** Un compte marchand ne voit jamais la grille d'un autre client. */
    private function dansPerimetre(User $user, int $clientId): bool
    {
        return ! $user->isClient() || (int) $user->id === $clientId;
    }
}
