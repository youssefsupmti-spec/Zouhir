<?php

namespace App\Http\Controllers;

use App\Application\RolesPermissions\Actions\DeleteRoleAction;
use App\Application\RolesPermissions\Actions\DuplicateRoleAction;
use App\Application\RolesPermissions\Actions\ManageRoleMembersAction;
use App\Application\RolesPermissions\Actions\SaveRoleAction;
use App\Domain\RolesPermissions\Support\PermissionCatalog;
use App\Http\Requests\RolesPermissions\RoleMemberRequest;
use App\Http\Requests\RolesPermissions\SaveRoleRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Administration des rôles (Spatie Laravel Permission existant, aucun RBAC parallèle).
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);
        $actor = $request->user();

        $search = trim((string) $request->input('search', ''));
        $type = in_array($request->input('type'), ['system', 'custom'], true) ? $request->input('type') : 'all';
        $perms = in_array($request->input('permissions'), ['none', 'some'], true) ? $request->input('permissions') : 'all';
        $users = in_array($request->input('users'), ['none', 'some'], true) ? $request->input('users') : 'all';
        $guard = (string) $request->input('guard', 'all');
        $sort = in_array($request->input('sort'), ['name', 'permissions_count', 'users_count', 'created_at'], true)
            ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $system = PermissionCatalog::systemRoles();

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($search !== '', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
            ->when($type === 'system', fn (Builder $q) => $q->whereIn('name', $system))
            ->when($type === 'custom', fn (Builder $q) => $q->whereNotIn('name', $system))
            ->when($perms === 'none', fn (Builder $q) => $q->whereDoesntHave('permissions'))
            ->when($perms === 'some', fn (Builder $q) => $q->whereHas('permissions'))
            ->when($users === 'none', fn (Builder $q) => $q->whereDoesntHave('users'))
            ->when($users === 'some', fn (Builder $q) => $q->whereHas('users'))
            ->when($guard !== 'all', fn (Builder $q) => $q->where('guard_name', $guard))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'is_system' => PermissionCatalog::isSystemRole($role->name),
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'created_at' => $role->created_at?->toIso8601String(),
                'can' => [
                    'update' => $actor->can('update', $role),
                    'delete' => $actor->can('delete', $role) && $role->users_count === 0,
                    'duplicate' => $actor->can('create', Role::class),
                ],
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'stats' => $this->stats(),
            'catalog' => $this->catalog($actor),
            'guards' => Role::query()->distinct()->orderBy('guard_name')->pluck('guard_name'),
            'canCreate' => $actor->can('create', Role::class),
            'filters' => [
                'search' => $search, 'type' => $type, 'permissions' => $perms, 'users' => $users,
                'guard' => $guard, 'sort' => $sort, 'direction' => $direction, 'per_page' => $perPage,
            ],
        ]);
    }

    /** Détails d'un rôle (JSON) pour la fiche latérale. */
    public function show(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('view', $role);

        $members = $role->users()
            ->select(['users.id', 'users.nom_complet', 'users.email'])
            ->orderBy('users.nom_complet')
            ->limit(100)
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'is_system' => PermissionCatalog::isSystemRole($role->name),
            'created_at' => $role->created_at?->toIso8601String(),
            'permissions' => $role->permissions()->orderBy('name')->pluck('name'),
            'users_count' => $role->users()->count(),
            'members' => $members,
            'can_manage_members' => $request->user()->can('manageMembers', $role),
            'locked_permissions' => \App\Application\RolesPermissions\Support\RoleGuard::isAdminRole($role)
                ? PermissionCatalog::critical()
                : [],
        ]);
    }

    public function store(SaveRoleRequest $request, SaveRoleAction $action): RedirectResponse
    {
        $role = $action->execute($request->user(), null, $request->validated('name'), $request->validated('permissions'));

        return back()->with('success', "Rôle « {$role->name} » créé.");
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRoleAction $action): RedirectResponse
    {
        $action->execute($request->user(), $role, $request->validated('name'), $request->validated('permissions'));

        return back()->with('success', "Rôle « {$role->name} » mis à jour.");
    }

    public function duplicate(Request $request, Role $role, DuplicateRoleAction $action): RedirectResponse
    {
        Gate::authorize('create', Role::class);
        $copy = $action->execute($request->user(), $role);

        return back()->with('success', "Rôle dupliqué : « {$copy->name} ».");
    }

    public function destroy(Request $request, Role $role, DeleteRoleAction $action): RedirectResponse
    {
        Gate::authorize('delete', $role);
        $name = $role->name;
        $action->execute($request->user(), $role);

        return back()->with('success', "Rôle « {$name} » supprimé.");
    }

    public function addMember(RoleMemberRequest $request, Role $role, ManageRoleMembersAction $action): RedirectResponse
    {
        $member = User::where('email', $request->validated('email'))->firstOrFail();
        $action->assign($request->user(), $role, $member);

        return back()->with('success', "{$member->name} a reçu le rôle « {$role->name} ».");
    }

    public function removeMember(Request $request, Role $role, User $user, ManageRoleMembersAction $action): RedirectResponse
    {
        Gate::authorize('manageMembers', $role);
        $action->remove($request->user(), $role, $user);

        return back()->with('success', "Rôle « {$role->name} » retiré à {$user->name}.");
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        $system = PermissionCatalog::systemRoles();
        $tables = config('permission.table_names');

        return [
            'total' => Role::count(),
            'system' => Role::whereIn('name', $system)->count(),
            'custom' => Role::whereNotIn('name', $system)->count(),
            'users_with_roles' => DB::table($tables['model_has_roles'])
                ->where('model_type', (new User)->getMorphClass())
                ->distinct()
                ->count(config('permission.column_names.model_morph_key')),
        ];
    }

    /**
     * Catalogue pour la Permission Matrix : permissions RÉELLEMENT en base (guard web),
     * enrichies par le catalogue centralisé, avec la possibilité de délégation de l'acteur.
     */
    private function catalog(User $actor): array
    {
        $permissions = Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')
            ->map(fn (string $name) => ['name' => $name] + PermissionCatalog::describe($name) + [
                'grantable' => $actor->can($name),
            ])
            ->values();

        $modules = collect(PermissionCatalog::MODULES)
            ->map(fn (array $m, string $key) => ['key' => $key] + $m)
            ->filter(fn (array $m) => $permissions->contains('module', $m['key']))
            ->values();

        return ['modules' => $modules, 'permissions' => $permissions];
    }
}
