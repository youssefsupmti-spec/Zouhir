<?php

declare(strict_types=1);

namespace App\Domain\RolesPermissions\Support;

use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

/**
 * Résolution CACHÉE d'un rôle Spatie (nom -> id).
 *
 * Évite les requêtes redondantes provoquées par Role::findByName() à chaque appel.
 * Mémorisation en mémoire par requête + Cache Laravel (TTL 24h).
 */
class RoleIds
{
    private const CACHE_PREFIX = 'role_id:';
    private const CACHE_TTL = 86400; // 24h
    private const NONE = 0;

    /** @var array<string, int|null> Mémo par requête */
    private array $memo = [];

    /**
     * Id du rôle, ou null s'il n'existe pas (jamais d'exception).
     */
    public function id(string $name, string $guard = 'web'): ?int
    {
        $key = $name . '|' . $guard;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $id = (int) Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL,
            fn (): int => (int) (Role::query()
                ->where('name', $name)
                ->where('guard_name', $guard)
                ->value('id') ?? self::NONE),
        );

        return $this->memo[$key] = ($id === self::NONE ? null : $id);
    }

    /**
     * Purge l'entrée d'un rôle du cache.
     */
    public static function forget(string $name, string $guard = 'web'): void
    {
        Cache::forget(self::CACHE_PREFIX . $name . '|' . $guard);
    }
}

