<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\RolesPermissions\Support\PermissionCatalog;
use App\Models\Command;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sprint 25 : administration des rôles et permissions (Spatie existant).
 */
class RolesPermissionsAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->admin->assignRole('admin');
        $this->client = User::factory()->create(['role' => UserRole::CLIENT]);
        $this->client->assignRole('client');
    }

    /* ─────────────── 1. Affichage, statistiques, catalogue ─────────────── */

    public function test_admin_lists_existing_roles_with_real_stats(): void
    {
        Role::create(['name' => 'marchand_vip', 'guard_name' => 'web']);

        $this->actingAs($this->admin)->get('/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Roles/Index')
                ->where('stats.total', 6)
                ->where('stats.system', 5)
                ->where('stats.custom', 1)
                ->where('stats.users_with_roles', 2)
                ->has('roles.data', 6)
                ->where('roles.data.0.name', 'admin')
                ->where('roles.data.0.is_system', true)
                ->where('roles.data.0.can.delete', false)
                ->has('catalog.permissions', count(PermissionEnum::cases()))
                ->where('canCreate', true)
            );
    }

    public function test_catalog_covers_every_declared_permission_with_a_known_module(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            $this->assertTrue(PermissionCatalog::isCatalogued($permission->value), $permission->value);
            $this->assertArrayHasKey(PermissionCatalog::moduleOf($permission->value), PermissionCatalog::MODULES);
        }
        $this->assertSame(PermissionCatalog::UNCATALOGUED_MODULE, PermissionCatalog::moduleOf('inconnue'));
        $this->assertFalse(PermissionCatalog::describe(PermissionEnum::EXPORT_EXCEL->value)['implemented']);
        $this->assertTrue(PermissionCatalog::describe(PermissionEnum::TRACK_COLIS->value)['implemented']);
    }

    /* ─────── 2-5. Création, permissions multiples, modification, révocation ─────── */

    public function test_created_role_really_works_in_policies_then_revocation_removes_access(): void
    {
        $this->actingAs($this->admin)->post('/roles', [
            'name' => 'Marchand Partenaire',
            'permissions' => [PermissionEnum::ACT_AS_CLIENT->value, PermissionEnum::TRACK_COLIS->value, PermissionEnum::VIEW_FINANCES->value],
            'guard_name' => 'api', // ignoré : guard imposé côté serveur
        ])->assertRedirect()->assertSessionHasNoErrors();

        $role = Role::findByName('Marchand Partenaire', 'web');
        $this->assertSame('web', $role->guard_name);
        $this->assertCount(3, $role->permissions);

        // Le rôle fonctionne réellement : périmètre marchand appliqué par CommandPolicy + Command::visibleTo.
        $member = User::factory()->create(['role' => null]);
        $this->actingAs($this->admin)->post("/roles/{$role->id}/users", ['email' => $member->email])->assertSessionHasNoErrors();
        $zone = Zone::create(['name' => 'Casablanca', 'code' => 'CAS']);
        $ville = Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $zone->id]);
        foreach ([[$member, 'OUA-MINE0001'], [$this->client, 'OUA-OTHR0001']] as [$owner, $code]) {
            Command::create(['code' => $code, 'client_id' => $owner->id, 'destinataire_nom' => 'X', 'destinataire_telephone' => '0600000000',
                'destinataire_ville' => 'Casablanca', 'destinataire_ville_id' => $ville->id, 'prix_brut' => 10, 'state' => 'Nouveau', 'state_group' => 'nouveau']);
        }

        $this->actingAs($member->fresh())->get('/colis')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('colis.data', 1)->where('colis.data.0.code', 'OUA-MINE0001'));
        $this->actingAs($member->fresh())->get('/banks')->assertOk();

        // Modification : renommage + révocation de track_colis et view_finances.
        $this->actingAs($this->admin)->put("/roles/{$role->id}", [
            'name' => 'Marchand Partenaire Restreint',
            'permissions' => [PermissionEnum::ACT_AS_CLIENT->value],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Marchand Partenaire Restreint', $role->fresh()->name);
        $this->actingAs($member->fresh())->get('/colis')->assertForbidden();
        $this->actingAs($member->fresh())->get('/banks')->assertForbidden();
    }

    public function test_role_validation_rejects_duplicates_and_unknown_permissions(): void
    {
        $this->actingAs($this->admin)->post('/roles', ['name' => 'client', 'permissions' => []])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post('/roles', ['name' => 'Nouveau', 'permissions' => ['permission_inventee']])
            ->assertSessionHasErrors('permissions.0');

        Permission::create(['name' => 'api_only', 'guard_name' => 'api']);
        $this->actingAs($this->admin)->post('/roles', ['name' => 'Nouveau', 'permissions' => ['api_only']])
            ->assertSessionHasErrors('permissions.0');
        $this->assertNull(Role::where('name', 'Nouveau')->first());
    }

    /* ─────────────────── 6-7. Détails et affectation ─────────────────── */

    public function test_role_details_and_member_assignment(): void
    {
        $role = Role::findByName('client');

        $this->actingAs($this->admin)->getJson("/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('name', 'client')
            ->assertJsonPath('is_system', true)
            ->assertJsonPath('users_count', 1)
            ->assertJsonPath('members.0.email', $this->client->email)
            ->assertJsonMissingPath('members.0.password')
            ->assertJsonMissingPath('members.0.telephone');

        $user = User::factory()->create(['role' => null]);
        $this->actingAs($this->admin)->post("/roles/{$role->id}/users", ['email' => $user->email])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasRole('client'));
        $this->assertTrue($user->fresh()->can('track_colis'));

        $this->actingAs($this->admin)->post("/roles/{$role->id}/users", ['email' => $user->email])->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post("/roles/{$role->id}/users", ['email' => 'absent@test.ma'])->assertSessionHasErrors('email');

        $this->actingAs($this->admin)->delete("/roles/{$role->id}/users/{$user->id}")->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasRole('client'));
        $this->assertFalse($user->fresh()->can('track_colis'), 'Cache Spatie invalidé après retrait');
    }

    /* ─────────────────── 9. Protection des rôles critiques ─────────────────── */

    public function test_system_and_critical_roles_are_protected(): void
    {
        $adminRole = Role::findByName('admin');

        // Le super-admin passe Gate::before : la protection est alors portée par DeleteRoleAction.
        $this->actingAs($this->admin)->delete("/roles/{$adminRole->id}")->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->delete('/roles/'.Role::findByName('client')->id)->assertSessionHasErrors('role');
        $this->assertNotNull(Role::where('name', 'admin')->first());

        // Un administrateur délégué (access_admin, sans Gate::before) est refusé dès la Policy.
        $delegated = Role::create(['name' => 'securite', 'guard_name' => 'web']);
        $delegated->givePermissionTo(PermissionEnum::ACCESS_ADMIN->value);
        $officer = User::factory()->create(['role' => null]);
        $officer->assignRole($delegated);
        $this->actingAs($officer)->delete('/roles/'.Role::findByName('livreur')->id)->assertForbidden();

        $this->actingAs($this->admin)->put("/roles/{$adminRole->id}", ['name' => 'administrateur', 'permissions' => $adminRole->permissions->pluck('name')->all()])
            ->assertSessionHasErrors('name');

        $withoutCritical = array_values(array_diff($adminRole->permissions->pluck('name')->all(), [PermissionEnum::ACT_AS_ADMIN->value]));
        $this->actingAs($this->admin)->put("/roles/{$adminRole->id}", ['name' => 'admin', 'permissions' => $withoutCritical])
            ->assertSessionHasErrors('permissions');
        $this->assertTrue($adminRole->fresh()->hasPermissionTo(PermissionEnum::ACT_AS_ADMIN->value));

        // Dernier administrateur et auto-retrait.
        $this->actingAs($this->admin)->delete("/roles/{$adminRole->id}/users/{$this->admin->id}")->assertSessionHasErrors('member');
        $second = User::factory()->create(['role' => UserRole::ADMIN]);
        $second->assignRole('admin');
        $this->actingAs($second)->delete("/roles/{$adminRole->id}/users/{$this->admin->id}")->assertSessionHasNoErrors();
        $this->actingAs($second)->delete("/roles/{$adminRole->id}/users/{$second->id}")->assertSessionHasErrors('member');
        $this->assertTrue($second->fresh()->hasRole('admin'));
    }

    public function test_custom_role_deletion_only_when_unused(): void
    {
        $role = Role::create(['name' => 'temporaire', 'guard_name' => 'web']);
        $role->givePermissionTo(PermissionEnum::TRACK_COLIS->value);
        $this->client->assignRole($role);

        $this->actingAs($this->admin)->delete("/roles/{$role->id}")->assertSessionHasErrors('role');
        $this->assertNotNull($role->fresh());

        $this->client->removeRole($role);
        $this->actingAs($this->admin)->delete("/roles/{$role->id}")->assertSessionHasNoErrors();
        $this->assertNull(Role::where('name', 'temporaire')->first());
        $this->assertTrue(Permission::where('name', PermissionEnum::TRACK_COLIS->value)->exists(), 'Permission conservée');
    }

    public function test_duplicate_copies_permissions_but_not_members(): void
    {
        $source = Role::findByName('client');

        $this->actingAs($this->admin)->post("/roles/{$source->id}/duplicate")->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/roles/{$source->id}/duplicate")->assertSessionHasNoErrors();

        $copy = Role::findByName('client (copie)');
        $this->assertNotNull(Role::where('name', 'client (copie) 2')->first());
        $this->assertEqualsCanonicalizing($source->permissions->pluck('name')->all(), $copy->permissions->pluck('name')->all());
        $this->assertSame(0, $copy->users()->count());
    }

    /* ─────────────── Délégation : pas d'auto-escalade ─────────────── */

    public function test_delegated_administrator_cannot_grant_permissions_he_lacks(): void
    {
        $delegateRole = Role::create(['name' => 'admin_delegue', 'guard_name' => 'web']);
        $delegateRole->givePermissionTo([PermissionEnum::ACCESS_ADMIN->value, PermissionEnum::TRACK_COLIS->value]);
        $delegate = User::factory()->create(['role' => null]);
        $delegate->assignRole($delegateRole);
        $this->assertFalse($delegate->isAdmin());

        $this->actingAs($delegate)->get('/roles')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('catalog.permissions', fn ($perms) => collect($perms)
                ->firstWhere('name', PermissionEnum::MANAGE_USERS->value)['grantable'] === false));

        $this->actingAs($delegate)->post('/roles', ['name' => 'Suivi seul', 'permissions' => [PermissionEnum::TRACK_COLIS->value]])
            ->assertSessionHasNoErrors();
        $this->actingAs($delegate)->post('/roles', ['name' => 'Escalade', 'permissions' => [PermissionEnum::MANAGE_USERS->value]])
            ->assertSessionHasErrors('permissions');
        $this->assertNull(Role::where('name', 'Escalade')->first());

        // Ne peut ni s'ajouter des droits sur son propre rôle, ni s'attribuer le rôle admin, ni modifier un rôle plus puissant.
        $this->actingAs($delegate)->put("/roles/{$delegateRole->id}", ['name' => 'admin_delegue',
            'permissions' => [PermissionEnum::ACCESS_ADMIN->value, PermissionEnum::TRACK_COLIS->value, PermissionEnum::ACT_AS_ADMIN->value]])
            ->assertSessionHasErrors('permissions');
        $this->actingAs($delegate)->post('/roles/'.Role::findByName('admin')->id.'/users', ['email' => $delegate->email])
            ->assertSessionHasErrors('email');
        $this->actingAs($delegate)->put('/roles/'.Role::findByName('client')->id, ['name' => 'client', 'permissions' => []])
            ->assertSessionHasErrors('role');

        $this->assertFalse($delegate->fresh()->hasRole('admin'));
        $this->assertFalse($delegate->fresh()->can(PermissionEnum::MANAGE_USERS->value));
    }

    /* ─────────────── 10 & 14. Refus et accès direct aux routes ─────────────── */

    public function test_unauthorized_users_are_refused_on_every_route(): void
    {
        $role = Role::findByName('client');
        $permission = Permission::first();

        foreach ([$this->client, $this->driver()] as $user) {
            $this->actingAs($user)->get('/roles')->assertForbidden();
            $this->actingAs($user)->getJson("/roles/{$role->id}")->assertForbidden();
            $this->actingAs($user)->post('/roles', ['name' => 'pirate', 'permissions' => []])->assertForbidden();
            $this->actingAs($user)->put("/roles/{$role->id}", ['name' => 'client', 'permissions' => []])->assertForbidden();
            $this->actingAs($user)->delete("/roles/{$role->id}")->assertForbidden();
            $this->actingAs($user)->post("/roles/{$role->id}/duplicate")->assertForbidden();
            $this->actingAs($user)->post("/roles/{$role->id}/users", ['email' => $user->email])->assertForbidden();
            $this->actingAs($user)->delete("/roles/{$role->id}/users/{$this->client->id}")->assertForbidden();
            $this->actingAs($user)->get('/permissions')->assertForbidden();
            $this->actingAs($user)->post('/permissions/sync')->assertForbidden();
            $this->actingAs($user)->delete("/permissions/{$permission->id}")->assertForbidden();
        }

        $this->assertNull(Role::where('name', 'pirate')->first());
        $this->assertSame(7, $role->fresh()->permissions()->count());

        auth()->logout();
        $this->get('/roles')->assertRedirect('/login');
        $this->get('/permissions')->assertRedirect('/login');
    }

    public function test_navigation_capabilities_are_shared_by_policies(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.roles', true)->where('auth.can.permissions', true));
        $this->actingAs($this->client)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.roles', false)->where('auth.can.permissions', false));
    }

    /* ─────────────── 11-12. Guards, pagination, recherche, filtres ─────────────── */

    public function test_pagination_search_and_filters(): void
    {
        foreach (range(1, 18) as $i) {
            Role::create(['name' => sprintf('equipe_%02d', $i), 'guard_name' => 'web']);
        }
        Role::findByName('equipe_01')->givePermissionTo(PermissionEnum::TRACK_COLIS->value);
        Role::create(['name' => 'api_role', 'guard_name' => 'api']);

        $this->actingAs($this->admin)->get('/roles')
            ->assertInertia(fn (Assert $page) => $page->has('roles.data', 15)->where('roles.last_page', 2)->where('roles.total', 24)
                ->where('guards', ['api', 'web']));

        $this->actingAs($this->admin)->get('/roles?search=equipe_1')
            ->assertInertia(fn (Assert $page) => $page->where('roles.total', 9));
        $this->actingAs($this->admin)->get('/roles?type=system')
            ->assertInertia(fn (Assert $page) => $page->where('roles.total', 5));
        $this->actingAs($this->admin)->get('/roles?type=custom&permissions=some')
            ->assertInertia(fn (Assert $page) => $page->where('roles.total', 1)->where('roles.data.0.name', 'equipe_01'));
        $this->actingAs($this->admin)->get('/roles?users=some')
            ->assertInertia(fn (Assert $page) => $page->where('roles.total', 2));
        $this->actingAs($this->admin)->get('/roles?guard=api')
            ->assertInertia(fn (Assert $page) => $page->where('roles.total', 1)->where('roles.data.0.guard_name', 'api'));
        $this->actingAs($this->admin)->get('/roles?sort=users_count&direction=desc&per_page=500')
            ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 100));
    }

    /* ─────────────────────── Page Permissions ─────────────────────── */

    public function test_permissions_catalog_stats_groups_and_filters(): void
    {
        Permission::create(['name' => 'ancienne_permission', 'guard_name' => 'web']);
        $total = count(PermissionEnum::cases()) + 1;

        $this->actingAs($this->admin)->get('/permissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Permissions/Index')
                ->where('stats.total', $total)
                ->where('stats.unassigned', 1)
                ->where('stats.assigned', $total - 1)
                // 7 modules depuis le Sprint 28 (ajout du module « tarifs »).
                ->where('stats.modules', count(PermissionCatalog::MODULES))
                ->where('missingFromDatabase', [])
                ->where('groups.'.(count(PermissionCatalog::MODULES) - 1).'.key', PermissionCatalog::UNCATALOGUED_MODULE)
                ->where('groups.'.(count(PermissionCatalog::MODULES) - 1).'.permissions.0.can_delete', true)
                ->where('groups.'.(count(PermissionCatalog::MODULES) - 1).'.permissions.0.catalogued', false)
            );

        $this->actingAs($this->admin)->get('/permissions?module=colis')
            ->assertInertia(fn (Assert $page) => $page->has('groups', 1)->has('groups.0.permissions', 3));
        $this->actingAs($this->admin)->get('/permissions?status=unassigned')
            ->assertInertia(fn (Assert $page) => $page->has('groups', 1)->where('groups.0.permissions.0.name', 'ancienne_permission'));
        $this->actingAs($this->admin)->get('/permissions?key=ActAs')
            ->assertInertia(fn (Assert $page) => $page->has('groups.0.permissions', 4));
    }

    public function test_permission_catalog_sync_and_safe_deletion(): void
    {
        Permission::where('name', PermissionEnum::EXPORT_EXCEL->value)->delete();
        $this->actingAs($this->admin)->get('/permissions')
            ->assertInertia(fn (Assert $page) => $page->where('missingFromDatabase', [PermissionEnum::EXPORT_EXCEL->value]));

        $this->actingAs($this->admin)->post('/permissions/sync')->assertSessionHasNoErrors();
        $this->assertTrue(Permission::where('name', PermissionEnum::EXPORT_EXCEL->value)->exists());

        $catalogued = Permission::findByName(PermissionEnum::TRACK_COLIS->value);
        $this->actingAs($this->admin)->delete("/permissions/{$catalogued->id}")->assertSessionHasErrors('permission');
        $this->assertNotNull(Permission::where('name', PermissionEnum::TRACK_COLIS->value)->first());

        $orphan = Permission::create(['name' => 'orpheline', 'guard_name' => 'web']);
        $used = Permission::create(['name' => 'orpheline_utilisee', 'guard_name' => 'web']);
        Role::findByName('client')->givePermissionTo($used);

        $this->actingAs($this->admin)->delete("/permissions/{$used->id}")->assertSessionHasErrors('permission');
        $this->actingAs($this->admin)->delete("/permissions/{$orphan->id}")->assertSessionHasNoErrors();
        $this->assertNull(Permission::where('name', 'orpheline')->first());
        $this->assertNotNull(Permission::where('name', 'orpheline_utilisee')->first());
    }

    private function driver(): User
    {
        $driver = User::factory()->create(['role' => UserRole::LIVREUR]);
        $driver->assignRole('livreur');

        return $driver;
    }
}
