<?php

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sprint 28 — Permissions du module Tarifs Clients (additif et idempotent).
 *
 * - Crée les 4 permissions `tarifs_client:*` si elles manquent.
 * - Les accorde UNIQUEMENT au rôle système administrateur s'il existe déjà
 *   (aucun autre rôle ne les reçoit : la délégation passe par l'écran Rôles).
 * - Ne crée aucun rôle, ne retire aucune permission (givePermissionTo, jamais sync).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = array_map(fn (PermissionEnum $p) => $p->value, PermissionEnum::tarifsClient());

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::query()
            ->where('name', UserRole::ADMIN->value)
            ->where('guard_name', 'web')
            ->first();

        $admin?->givePermissionTo($names);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non destructif : les permissions peuvent déjà être attribuées à des rôles délégués.
    }
};
