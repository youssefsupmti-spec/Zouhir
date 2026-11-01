<?php

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sprint 29 — Permissions `tarifs_standard:*` et `tarifs_livreur:*` (additif, idempotent).
 *
 * Accordées UNIQUEMENT au rôle système administrateur s'il existe. Aucun autre rôle
 * (livreur compris) ne les reçoit : la délégation passe par l'écran Rôles.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = array_map(fn (PermissionEnum $p) => $p->value, PermissionEnum::tarifsSprint29());

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::query()
            ->where('name', UserRole::ADMIN->value)
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($names);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non destructif : ces permissions peuvent déjà être déléguées à des rôles personnalisés.
    }
};
