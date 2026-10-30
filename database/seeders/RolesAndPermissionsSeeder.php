<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create all permissions
        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }

        // 2. Create Roles and assign permissions
        $rolesPermissions = [
            UserRole::ADMIN->value => PermissionEnum::cases(),
            UserRole::CLIENT->value => [
                PermissionEnum::VIEW_DASHBOARD,
                PermissionEnum::CREATE_COLIS,
                PermissionEnum::MANAGE_COLIS,
                PermissionEnum::TRACK_COLIS,
                PermissionEnum::VIEW_FINANCES,
                PermissionEnum::EXPORT_EXCEL,
                PermissionEnum::ACT_AS_CLIENT,
            ],
            UserRole::LIVREUR->value => [
                PermissionEnum::VIEW_DASHBOARD,
                PermissionEnum::TRACK_COLIS,
                PermissionEnum::ACT_AS_DRIVER,
            ],
            UserRole::AGENCE->value => [
                PermissionEnum::VIEW_DASHBOARD,
                PermissionEnum::MANAGE_COLIS,
                PermissionEnum::TRACK_COLIS,
                PermissionEnum::ACT_AS_AGENCY,
            ],
            UserRole::OPERATEUR->value => [
                PermissionEnum::VIEW_DASHBOARD,
                PermissionEnum::MANAGE_COLIS,
                PermissionEnum::TRACK_COLIS,
                PermissionEnum::EXPORT_EXCEL,
            ],
        ];

        foreach ($rolesPermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $permNames = array_map(fn ($p) => $p instanceof PermissionEnum ? $p->value : $p, $perms);
            $role->syncPermissions($permNames);
        }
    }
}
