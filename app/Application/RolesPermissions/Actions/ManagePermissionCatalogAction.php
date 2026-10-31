<?php

declare(strict_types=1);

namespace App\Application\RolesPermissions\Actions;

use App\Domain\RolesPermissions\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Administration du catalogue selon le SEUL mécanisme supporté par le code :
 * une permission n'a d'effet que si elle est déclarée (Enum) et reliée à une règle.
 * - Synchronisation : enregistre en base les permissions déclarées manquantes.
 * - Suppression : uniquement une permission HORS catalogue, attribuée à aucun rôle ni utilisateur.
 * Aucune création arbitraire, aucune modification de guard_name.
 */
final class ManagePermissionCatalogAction
{
    /** @return list<string> permissions créées */
    public function synchronize(): array
    {
        $created = [];

        DB::transaction(function () use (&$created) {
            foreach (array_keys(PermissionCatalog::definitions()) as $name) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                if ($permission->wasRecentlyCreated) {
                    $created[] = $name;
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }

    public function deleteOrphan(Permission $permission): void
    {
        if (PermissionCatalog::isCatalogued($permission->name)) {
            throw ValidationException::withMessages([
                'permission' => 'Une permission du catalogue ne peut pas être supprimée depuis l\'interface.',
            ]);
        }

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            throw ValidationException::withMessages([
                'permission' => 'Cette permission est encore attribuée à des rôles ou utilisateurs.',
            ]);
        }

        DB::transaction(fn () => $permission->delete());
    }
}
