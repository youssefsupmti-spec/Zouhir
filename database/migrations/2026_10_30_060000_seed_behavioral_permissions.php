<?php

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration d'initialisation des permissions comportementales Spatie.
 * Permet le support complet des rôles personnalisés (Custom Roles).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Réinitialiser le cache Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Créer les permissions comportementales
        $permissions = [
            PermissionEnum::ACT_AS_CLIENT->value,
            PermissionEnum::ACT_AS_DRIVER->value,
            PermissionEnum::ACT_AS_AGENCY->value,
            PermissionEnum::ACT_AS_ADMIN->value,
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 2. Assigner aux rôles de base
        $clientRole = Role::firstOrCreate(['name' => UserRole::CLIENT->value, 'guard_name' => 'web']);
        $clientRole->givePermissionTo(PermissionEnum::ACT_AS_CLIENT->value);

        $driverRole = Role::firstOrCreate(['name' => UserRole::LIVREUR->value, 'guard_name' => 'web']);
        $driverRole->givePermissionTo(PermissionEnum::ACT_AS_DRIVER->value);

        $agencyRole = Role::firstOrCreate(['name' => UserRole::AGENCE->value, 'guard_name' => 'web']);
        $agencyRole->givePermissionTo(PermissionEnum::ACT_AS_AGENCY->value);

        $adminRole = Role::firstOrCreate(['name' => UserRole::ADMIN->value, 'guard_name' => 'web']);
        $adminRole->givePermissionTo(PermissionEnum::ACT_AS_ADMIN->value);
    }

    public function down(): void
    {
        $permissions = [
            PermissionEnum::ACT_AS_CLIENT->value,
            PermissionEnum::ACT_AS_DRIVER->value,
            PermissionEnum::ACT_AS_AGENCY->value,
            PermissionEnum::ACT_AS_ADMIN->value,
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
