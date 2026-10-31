<?php

declare(strict_types=1);

namespace App\Application\RolesPermissions\Actions;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Duplique un rôle (permissions uniquement, jamais les utilisateurs) en rôle personnalisé.
 */
final class DuplicateRoleAction
{
    public function __construct(private readonly SaveRoleAction $save)
    {
    }

    public function execute(User $actor, Role $source): Role
    {
        $base = mb_substr($source->name, 0, 110).' (copie)';
        $name = $base;
        $i = 2;
        while (Role::where('name', $name)->where('guard_name', $source->guard_name)->exists()) {
            $name = $base.' '.$i++;
        }

        return $this->save->execute($actor, null, $name, $source->permissions()->pluck('name')->all());
    }
}
