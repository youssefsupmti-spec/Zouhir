<?php

namespace App\Policies;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MANAGE_USERS->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->can(PermissionEnum::MANAGE_USERS->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->can(PermissionEnum::MANAGE_USERS->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id && $user->can(PermissionEnum::MANAGE_USERS->value);
    }
}
