<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\User;

class DashboardPolicy
{
    /**
     * Determine whether the user can view the dashboard.
     */
    public function view(User $user): bool
    {
        return $user->is_active && ($user->can(PermissionEnum::VIEW_DASHBOARD->value) || $user->isClient() || $user->isAdmin());
    }
}
