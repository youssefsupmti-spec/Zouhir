<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $roles = [];
        $permissions = [];
        if ($user) {
            try {
                if (method_exists($user, 'getRoleNames')) {
                    $roles = $user->getRoleNames()->toArray();
                }
            } catch (\Throwable) {
                $roles = [$user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role];
            }

            try {
                if (method_exists($user, 'getAllPermissions')) {
                    $permissions = $user->getAllPermissions()->pluck('name')->toArray();
                }
            } catch (\Throwable) {
                $permissions = [];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'is_admin' => method_exists($user, 'isAdmin') ? $user->isAdmin() : false,
                    'is_client' => method_exists($user, 'isClient') ? $user->isClient() : true,
                    'store_name' => $user->store_name,
                    'city' => $user->city,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
