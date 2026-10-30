<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\Commande\Support\PerimetreColis;
use App\Domain\RolesPermissions\Support\RoleIds;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les permissions requises
        Permission::firstOrCreate(['name' => PerimetreColis::PROPRE_COMPTE, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => PerimetreColis::COLIS_AFFECTES, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => PerimetreColis::ADMINISTRATION, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => PerimetreColis::GESTION_AGENCE, 'guard_name' => 'web']);

        // Créer les rôles de base
        $clientRole = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $clientRole->givePermissionTo(PerimetreColis::PROPRE_COMPTE);

        $driverRole = Role::firstOrCreate(['name' => 'livreur', 'guard_name' => 'web']);
        $driverRole->givePermissionTo(PerimetreColis::COLIS_AFFECTES);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(PerimetreColis::ADMINISTRATION);
    }

    public function test_role_ids_cache_resolves_and_caches_id(): void
    {
        $roleIds = app(RoleIds::class);

        $clientId = $roleIds->id('client');
        $this->assertNotNull($clientId);
        $this->assertIsInt($clientId);

        // Deuxième appel résolu depuis le mémo/cache
        $this->assertEquals($clientId, $roleIds->id('client'));

        // Rôle inexistant donne null sans exception
        $this->assertNull($roleIds->id('role_inexistant_xyz'));
    }

    public function test_standard_client_is_detected_and_scoped(): void
    {
        $client = User::factory()->create([
            'email' => 'client.test@ouaissexpress.com',
            'role' => UserRole::CLIENT,
        ]);
        $client->assignRole('client');

        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isDriver());
        $this->assertFalse($client->isAdmin());
        $this->assertFalse($client->isStaff());

        $scoped = User::clients()->where('email', 'client.test@ouaissexpress.com')->first();
        $this->assertNotNull($scoped);
    }

    public function test_custom_role_with_act_as_client_is_detected_as_client(): void
    {
        // Création d'un rôle personnalisé pour un marchand VIP
        $vipRole = Role::firstOrCreate(['name' => 'marchand_vip', 'guard_name' => 'web']);
        $vipRole->givePermissionTo(PerimetreColis::PROPRE_COMPTE);

        $vipUser = User::factory()->create([
            'email' => 'vip@boutique.ma',
            'role' => null, // Aucun rôle legacy
        ]);
        $vipUser->assignRole('marchand_vip');

        // Vérification par périmètre comportemental
        $this->assertTrue($vipUser->isClient(), 'Le compte avec rôle personnalisé doit être reconnu comme client');
        $this->assertFalse($vipUser->isDriver());
        $this->assertFalse($vipUser->isAdmin());

        // Scope Eloquent doit également le trouver
        $found = User::clients()->where('email', 'vip@boutique.ma')->first();
        $this->assertNotNull($found, 'Le scope User::clients() doit inclure les rôles personnalisés dotés de ActAsClient:Command');
    }

    public function test_custom_role_with_act_as_driver_is_detected_as_driver(): void
    {
        // Création d'un rôle personnalisé pour un coursier express
        $motoRole = Role::firstOrCreate(['name' => 'coursier_express', 'guard_name' => 'web']);
        $motoRole->givePermissionTo(PerimetreColis::COLIS_AFFECTES);

        $driver = User::factory()->create([
            'email' => 'coursier@ouaissexpress.com',
            'role' => null,
        ]);
        $driver->assignRole('coursier_express');

        $this->assertTrue($driver->isDriver(), 'Le rôle personnalisé avec ActAsDriver:Command doit être reconnu comme driver');
        $this->assertFalse($driver->isClient());
        $this->assertFalse($driver->isAdmin());

        $found = User::drivers()->where('email', 'coursier@ouaissexpress.com')->first();
        $this->assertNotNull($found, 'Le scope User::drivers() doit inclure les rôles personnalisés dotés de ActAsDriver:Command');
    }

    public function test_admin_is_not_falsely_scoped_as_client_or_driver(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@ouaissexpress.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->assignRole('admin');

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isClient(), 'Un admin sans permission marchand ne doit pas être un client');
        $this->assertFalse($admin->isDriver(), 'Un admin sans permission livreur ne doit pas être un livreur');
        $this->assertTrue($admin->isStaff());

        $this->assertNull(User::clients()->where('email', 'admin@ouaissexpress.com')->first());
        $this->assertNull(User::drivers()->where('email', 'admin@ouaissexpress.com')->first());
    }
}
