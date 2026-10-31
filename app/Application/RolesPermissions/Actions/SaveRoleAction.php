<?php

declare(strict_types=1);

namespace App\Application\RolesPermissions\Actions;

use App\Application\RolesPermissions\Support\RoleGuard;
use App\Domain\RolesPermissions\Support\PermissionCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Création / modification d'un rôle via les API officielles Spatie
 * (Role::create, syncPermissions) dans une transaction.
 */
final class SaveRoleAction
{
    /** @param list<string> $permissions */
    public function execute(User $actor, ?Role $role, string $name, array $permissions): Role
    {
        $permissions = array_values(array_unique($permissions));

        return DB::transaction(function () use ($actor, $role, $name, $permissions) {
            if ($role === null) {
                RoleGuard::assertCanDelegate($actor, $permissions);

                $role = Role::create(['name' => $name, 'guard_name' => 'web']);
                $role->syncPermissions($permissions);

                return $role;
            }

            RoleGuard::assertCanManage($actor, $role);

            $current = $role->permissions()->pluck('name')->all();
            $added = array_values(array_diff($permissions, $current));
            $removed = array_values(array_diff($current, $permissions));
            RoleGuard::assertCanDelegate($actor, array_merge($added, $removed));

            if (PermissionCatalog::isSystemRole($role->name) && $name !== $role->name) {
                throw ValidationException::withMessages([
                    'name' => 'Un rôle système ne peut pas être renommé.',
                ]);
            }

            if (RoleGuard::isAdminRole($role)) {
                $lostCritical = array_values(array_intersect($removed, PermissionCatalog::critical()));
                if ($lostCritical !== []) {
                    throw ValidationException::withMessages([
                        'permissions' => 'Le rôle administrateur doit conserver ses permissions critiques : '.implode(', ', $lostCritical).'.',
                    ]);
                }
            }

            $role->name = $name;
            $role->save();
            $role->syncPermissions($permissions);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }
}
