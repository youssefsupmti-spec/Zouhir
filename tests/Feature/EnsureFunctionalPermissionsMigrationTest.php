<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Migration 2026_10_31_100000 : additive, idempotente, sans création de rôle.
 */
class EnsureFunctionalPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_31_100000_ensure_functional_permissions_on_base_roles.php');
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_reproduces_production_state_and_repairs_it_additively(): void
    {
        // État « production » : rôles de base avec uniquement ActAs*, permissions fonctionnelles absentes.
        $client = Role::findByName('client');
        $client->syncPermissions(['ActAsClient:Command']);
        Permission::whereIn('name', ['track_colis', 'view_finances'])->delete();

        // Permission ajoutée manuellement par un admin : ne doit JAMAIS être retirée.
        $client->givePermissionTo('export_excel');
        $custom = Role::create(['name' => 'marchand_vip', 'guard_name' => 'web']);
        $custom->givePermissionTo('ActAsClient:Command');

        // Compte legacy sans rôle Spatie.
        $legacy = User::factory()->create(['role' => UserRole::CLIENT]);
        $legacy->roles()->detach();

        $this->runMigration();
        $this->runMigration(); // idempotence

        $client->refresh();
        foreach (['track_colis', 'create_colis', 'manage_colis', 'view_finances', 'export_excel', 'ActAsClient:Command'] as $p) {
            $this->assertTrue($client->hasPermissionTo($p), "client doit avoir {$p}");
        }
        $this->assertSame(['ActAsClient:Command'], $custom->refresh()->permissions->pluck('name')->all(), 'Rôle personnalisé inchangé');
        $this->assertTrue($legacy->fresh()->hasRole('client'));
        $this->assertTrue($legacy->fresh()->can('track_colis'));
        $this->assertSame(1, $legacy->fresh()->roles()->count());
    }

    public function test_never_creates_missing_roles(): void
    {
        Role::where('name', 'operateur')->delete();

        $this->runMigration();

        $this->assertNull(Role::where('name', 'operateur')->first());
    }
}
