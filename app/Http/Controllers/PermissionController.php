<?php

namespace App\Http\Controllers;

use App\Application\RolesPermissions\Actions\ManagePermissionCatalogAction;
use App\Domain\RolesPermissions\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

/**
 * Catalogue central des permissions (consultation, synchronisation, nettoyage des orphelines).
 */
class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Permission::class);
        $actor = $request->user();

        $search = trim((string) $request->input('search', ''));
        $key = trim((string) $request->input('key', ''));
        $module = (string) $request->input('module', 'all');
        $guard = (string) $request->input('guard', 'all');
        $status = in_array($request->input('status'), ['assigned', 'unassigned'], true) ? $request->input('status') : 'all';

        $all = Permission::query()
            ->withCount(['roles', 'users'])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'guard_name' => $p->guard_name,
                'roles_count' => $p->roles_count,
                'direct_users_count' => $p->users_count,
                'roles' => $p->roles->pluck('name')->values(),
                'created_at' => $p->created_at?->toIso8601String(),
                'can_delete' => $actor->can('delete', $p) && $p->roles_count === 0 && $p->users_count === 0,
            ] + PermissionCatalog::describe($p->name));

        // Statistiques sur l'ensemble du catalogue (indépendantes des filtres).
        $stats = [
            'total' => $all->count(),
            'modules' => $all->pluck('module')->unique()->count(),
            'assigned' => $all->where('roles_count', '>', 0)->count(),
            'unassigned' => $all->where('roles_count', 0)->count(),
        ];

        $filtered = $all
            ->when($search !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p['label'].' '.($p['description'] ?? '')), mb_strtolower($search))))
            ->when($key !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p['name']), mb_strtolower($key))))
            ->when($module !== 'all', fn ($c) => $c->where('module', $module))
            ->when($guard !== 'all', fn ($c) => $c->where('guard_name', $guard))
            ->when($status === 'assigned', fn ($c) => $c->where('roles_count', '>', 0))
            ->when($status === 'unassigned', fn ($c) => $c->where('roles_count', 0));

        $groups = collect(PermissionCatalog::MODULES)
            ->map(function (array $meta, string $moduleKey) use ($filtered) {
                $items = $filtered->where('module', $moduleKey)->values();

                return [
                    'key' => $moduleKey,
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'permissions' => $items,
                    'roles_using' => $items->pluck('roles')->flatten()->unique()->count(),
                ];
            })
            ->filter(fn (array $g) => $g['permissions']->isNotEmpty())
            ->values();

        $existing = $all->pluck('name')->all();
        $missing = array_values(array_diff(array_keys(PermissionCatalog::definitions()), $existing));

        return Inertia::render('Permissions/Index', [
            'groups' => $groups,
            'stats' => $stats,
            'modules' => collect(PermissionCatalog::MODULES)
                ->map(fn (array $m, string $k) => ['key' => $k, 'label' => $m['label']])
                ->filter(fn (array $m) => $all->contains('module', $m['key']))
                ->values(),
            'guards' => $all->pluck('guard_name')->unique()->values(),
            'missingFromDatabase' => $missing,
            'canSynchronize' => $actor->can('synchronize', Permission::class),
            'filters' => compact('search', 'key', 'module', 'guard', 'status'),
        ]);
    }

    public function synchronize(ManagePermissionCatalogAction $action): RedirectResponse
    {
        Gate::authorize('synchronize', Permission::class);
        $created = $action->synchronize();

        return back()->with('success', $created === []
            ? 'Le catalogue est déjà synchronisé.'
            : 'Permissions enregistrées : '.implode(', ', $created).'.');
    }

    public function destroy(Permission $permission, ManagePermissionCatalogAction $action): RedirectResponse
    {
        Gate::authorize('delete', $permission);
        $name = $permission->name;
        $action->deleteOrphan($permission);

        return back()->with('success', "Permission « {$name} » supprimée.");
    }
}
