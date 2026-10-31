<?php

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sprint 24 : prérequis des Policies (RBAC existant, aucune suppression).
 *
 * 100 % ADDITIVE et IDEMPOTENTE, sûre en production (contrairement au seeder,
 * qui fait syncPermissions et écraserait les permissions ajoutées par un admin) :
 * 1. Crée les permissions de l'Enum manquantes (firstOrCreate).
 * 2. AJOUTE aux rôles de base EXISTANTS les permissions de la matrice officielle
 *    (RolesAndPermissionsSeeder) avec givePermissionTo : rien n'est retiré,
 *    aucun rôle n'est créé, les rôles personnalisés ne sont pas touchés.
 * 3. Attribue un rôle Spatie aux comptes qui n'en ont AUCUN, d'après leur colonne
 *    legacy users.role (déjà utilisée en repli par isAdmin/isClient/isDriver :
 *    aucun privilège supplémentaire n'est accordé).
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }

        $matrix = [
            UserRole::ADMIN->value => array_values(array_filter(
                PermissionEnum::cases(),
                fn (PermissionEnum $p) => ! in_array($p, [PermissionEnum::ACT_AS_CLIENT, PermissionEnum::ACT_AS_DRIVER], true)
            )),
            UserRole::CLIENT->value => [
                PermissionEnum::VIEW_DASHBOARD, PermissionEnum::CREATE_COLIS, PermissionEnum::MANAGE_COLIS,
                PermissionEnum::TRACK_COLIS, PermissionEnum::VIEW_FINANCES, PermissionEnum::EXPORT_EXCEL,
                PermissionEnum::ACT_AS_CLIENT,
            ],
            UserRole::LIVREUR->value => [
                PermissionEnum::VIEW_DASHBOARD, PermissionEnum::TRACK_COLIS, PermissionEnum::ACT_AS_DRIVER,
            ],
            UserRole::AGENCE->value => [
                PermissionEnum::VIEW_DASHBOARD, PermissionEnum::MANAGE_COLIS, PermissionEnum::TRACK_COLIS,
                PermissionEnum::ACT_AS_AGENCY,
            ],
            UserRole::OPERATEUR->value => [
                PermissionEnum::VIEW_DASHBOARD, PermissionEnum::MANAGE_COLIS, PermissionEnum::TRACK_COLIS,
                PermissionEnum::EXPORT_EXCEL,
            ],
        ];

        foreach ($matrix as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue; // Ne jamais créer de rôle en production.
            }

            $missing = array_values(array_diff(
                array_map(fn (PermissionEnum $p) => $p->value, $permissions),
                $role->permissions()->pluck('name')->all()
            ));

            if ($missing !== []) {
                $role->givePermissionTo($missing);
            }
        }

        // Comptes sans aucun rôle Spatie : rôle déduit de la colonne legacy users.role.
        $existingRoles = Role::where('guard_name', 'web')->pluck('name')->all();

        User::withTrashed()
            ->whereDoesntHave('roles')
            ->whereNotNull('role')
            ->select(['id', 'role'])
            ->chunkById(500, function ($users) use ($existingRoles) {
                foreach ($users as $user) {
                    $name = $user->role instanceof BackedEnum ? $user->role->value : (string) $user->role;
                    if (in_array($name, $existingRoles, true)) {
                        $user->assignRole($name);
                    }
                }
            });

        $registrar->forgetCachedPermissions();
    }

    /**
     * Volontairement sans effet : retirer ces permissions pourrait supprimer
     * des droits accordés ensuite par un administrateur (non destructif).
     */
    public function down(): void
    {
        //
    }
};
