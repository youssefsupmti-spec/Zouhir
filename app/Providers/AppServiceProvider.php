<?php

namespace App\Providers;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\RolesPermissions\Support\RoleIds;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // RoleIds en scoped : l'instance et son mémo vivent le temps de la requête
        $this->app->scoped(RoleIds::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Invalidation du cache rôle nom -> id
        $forgetRoleId = function (Role $role): void {
            RoleIds::forget($role->name, $role->guard_name);

            $previous = $role->getOriginal('name');
            if ($previous && $previous !== $role->name) {
                RoleIds::forget($previous, $role->getOriginal('guard_name') ?: $role->guard_name);
            }
        };

        Role::saved($forgetRoleId);
        Role::deleted($forgetRoleId);

        // Super-admin bypass via Gate::before
        Gate::before(function (User $user, string $ability) {
            return $user->isAdmin() ? true : null;
        });

        Gate::policy(User::class, UserPolicy::class);
    }
}
