<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\Commande\Support\PerimetreColis;
use App\Models\Bank;
use App\Models\Business;
use App\Models\Command;
use App\Models\CompteBancaire;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sprint 24 : Data Isolation & autorisations fondées sur les permissions Spatie existantes.
 * Utilise uniquement le seeder officiel des rôles/permissions et des données de test isolées.
 */
class DataIsolationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $clientA;
    private User $clientB;
    private User $driver;
    private Ville $ville;
    private Command $colisA;
    private Command $colisB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = $this->userWithRole('admin', UserRole::ADMIN);
        $this->clientA = $this->userWithRole('client', UserRole::CLIENT);
        $this->clientB = $this->userWithRole('client', UserRole::CLIENT);
        $this->driver = $this->userWithRole('livreur', UserRole::LIVREUR);

        $zone = Zone::create(['name' => 'Casablanca', 'code' => 'CAS']);
        $this->ville = Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $zone->id]);

        $this->colisA = $this->colis($this->clientA, 'OUA-AAAA0001', $this->driver);
        $this->colisB = $this->colis($this->clientB, 'OUA-BBBB0001');
    }

    /* ─────────────────────────── Colis ─────────────────────────── */

    public function test_client_lists_only_own_colis_and_scoped_stats(): void
    {
        $this->actingAs($this->clientA)->get('/colis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Colis/Index')
                ->has('colis.data', 1)
                ->where('colis.data.0.code', 'OUA-AAAA0001')
                ->where('stats.total', 1)
                ->where('stats.total_crbt', 0)
                ->has('clients', 1)
                ->where('clients.0.id', $this->clientA->id)
                ->has('livreurs', 0)
            );
    }

    public function test_client_cannot_filter_into_another_client_colis(): void
    {
        $this->actingAs($this->clientA)->get('/colis?client_id='.$this->clientB->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('colis.data', 0));
    }

    public function test_client_cannot_mutate_another_client_colis(): void
    {
        $payload = $this->colisPayload();

        $this->actingAs($this->clientA)->put("/colis/{$this->colisB->id}", $payload)->assertForbidden();
        $this->actingAs($this->clientA)->patch("/colis/{$this->colisB->id}/state", ['state_group' => 'livre'])->assertForbidden();
        $this->actingAs($this->clientA)->delete("/colis/{$this->colisB->id}")->assertForbidden();

        $this->assertDatabaseHas('commands', ['id' => $this->colisB->id, 'state_group' => 'nouveau', 'deleted_at' => null]);
    }

    public function test_client_can_update_own_colis_but_not_reassign_driver_or_change_state(): void
    {
        $other = $this->userWithRole('livreur', UserRole::LIVREUR);

        $this->actingAs($this->clientA)
            ->put("/colis/{$this->colisA->id}", $this->colisPayload(['destinataire_nom' => 'Nouveau Nom', 'livreur_id' => $other->id]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->colisA->refresh();
        $this->assertSame('Nouveau Nom', $this->colisA->destinataire_nom);
        $this->assertSame($this->driver->id, (int) $this->colisA->livreur_id, 'Un client ne réaffecte pas le livreur');

        $this->actingAs($this->clientA)
            ->patch("/colis/{$this->colisA->id}/state", ['state_group' => 'livre'])
            ->assertForbidden();
    }

    public function test_client_colis_creation_forces_own_client_id(): void
    {
        $this->actingAs($this->clientA)
            ->post('/colis', $this->colisPayload(['client_id' => $this->clientB->id]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $created = Command::latest('id')->first();
        $this->assertSame($this->clientA->id, (int) $created->client_id);
        $this->assertNull($created->livreur_id);
    }

    public function test_client_cannot_attach_another_merchant_business_to_colis(): void
    {
        $businessB = Business::create(['user_id' => $this->clientB->id, 'name' => 'Boutique B', 'status' => 'active']);

        $this->actingAs($this->clientA)
            ->post('/colis', $this->colisPayload(['business_id' => $businessB->id]))
            ->assertNotFound();
    }

    public function test_driver_sees_and_updates_state_of_assigned_colis_only(): void
    {
        $this->actingAs($this->driver)->get('/colis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('colis.data', 1)->where('colis.data.0.code', 'OUA-AAAA0001'));

        $this->actingAs($this->driver)
            ->patch("/colis/{$this->colisA->id}/state", ['state_group' => 'livre'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('livre', $this->colisA->refresh()->state_group);

        $this->actingAs($this->driver)->patch("/colis/{$this->colisB->id}/state", ['state_group' => 'livre'])->assertForbidden();
        $this->actingAs($this->driver)->put("/colis/{$this->colisA->id}", $this->colisPayload())->assertForbidden();
        $this->actingAs($this->driver)->delete("/colis/{$this->colisA->id}")->assertForbidden();
        $this->actingAs($this->driver)->post('/colis', $this->colisPayload())->assertForbidden();
    }

    public function test_agency_without_data_link_sees_no_colis(): void
    {
        $agence = $this->userWithRole('agence', UserRole::AGENCE);

        $this->actingAs($agence)->get('/colis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('colis.data', 0)->where('stats.total', 0));
        $this->actingAs($agence)->patch("/colis/{$this->colisA->id}/state", ['state_group' => 'livre'])->assertForbidden();
    }

    public function test_admin_sees_all_colis_and_can_change_any_state(): void
    {
        $this->actingAs($this->admin)->get('/colis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('colis.data', 2)->where('stats.total', 2));

        $this->actingAs($this->admin)
            ->patch("/colis/{$this->colisB->id}/state", ['state_group' => 'en_cours'])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_dashboard_stats_are_scoped_to_driver_perimeter(): void
    {
        $this->colis($this->clientB, 'OUA-BBBB0002', $this->driver);
        $this->colis($this->clientB, 'OUA-BBBB0003');

        $this->actingAs($this->driver)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.total_shipments', 2));
    }

    /* ─────────────────── Comptes utilisateurs (prise de contrôle) ─────────────────── */

    public function test_client_cannot_access_or_mutate_user_management(): void
    {
        $this->actingAs($this->clientA)->get('/clients')->assertForbidden();
        $this->actingAs($this->clientA)->get('/livreurs')->assertForbidden();
        $this->actingAs($this->clientA)->post('/clients', $this->userPayload('x@test.ma'))->assertForbidden();
        $this->actingAs($this->clientA)->post('/livreurs', $this->userPayload('y@test.ma'))->assertForbidden();
        $this->actingAs($this->clientA)->delete("/clients/{$this->clientB->id}")->assertForbidden();
        $this->actingAs($this->clientA)->delete("/livreurs/{$this->driver->id}")->assertForbidden();
        // Même sur son propre compte, la route de gestion exige manage_users.
        $this->actingAs($this->clientA)->put("/clients/{$this->clientA->id}", $this->userPayload($this->clientA->email, ['status' => 'active']))->assertForbidden();
    }

    public function test_account_takeover_of_admin_is_blocked(): void
    {
        $originalHash = $this->admin->password;
        $payload = $this->userPayload('pirate@test.ma', ['password' => 'Pirate#2026x']);

        $this->actingAs($this->clientA)->put("/clients/{$this->admin->id}", $payload)->assertForbidden();
        $this->actingAs($this->clientA)->put("/livreurs/{$this->admin->id}", $payload)->assertForbidden();

        $this->admin->refresh();
        $this->assertSame($originalHash, $this->admin->password);
        $this->assertNotSame('pirate@test.ma', $this->admin->email);
    }

    public function test_user_manager_cannot_edit_admin_or_wrong_account_type_through_module_routes(): void
    {
        $manager = $this->customRole('gestionnaire_comptes', [PermissionEnum::MANAGE_USERS->value]);

        $this->actingAs($manager)->get('/clients')->assertOk();
        $this->actingAs($manager)->put("/clients/{$this->admin->id}", $this->userPayload('a@test.ma'))->assertNotFound();
        $this->actingAs($manager)->put("/clients/{$this->driver->id}", $this->userPayload('b@test.ma'))->assertNotFound();
        $this->actingAs($manager)->put("/livreurs/{$this->clientA->id}", $this->userPayload('c@test.ma'))->assertNotFound();

        $this->actingAs($manager)
            ->put("/clients/{$this->clientA->id}", $this->userPayload('clienta.maj@test.ma'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('clienta.maj@test.ma', $this->clientA->refresh()->email);
    }

    public function test_user_creation_requires_password_without_default(): void
    {
        $payload = $this->userPayload('nouveau@test.ma');
        unset($payload['password']);

        $this->actingAs($this->admin)->post('/clients', $payload)->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post('/livreurs', $payload)->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'nouveau@test.ma']);

        $this->actingAs($this->admin)->post('/livreurs', $this->userPayload('nouveau@test.ma'))->assertSessionHasNoErrors();
        $livreur = User::where('email', 'nouveau@test.ma')->first();
        $this->assertTrue(Hash::check('Secret#2026x', $livreur->password));
        $this->assertFalse(Hash::check('livreur@2026', $livreur->password));
        $this->assertTrue($livreur->isDriver());
    }

    /* ─────────────────────────── Enseignes ─────────────────────────── */

    public function test_business_isolation_between_merchants(): void
    {
        $businessA = Business::create(['user_id' => $this->clientA->id, 'name' => 'Boutique A', 'status' => 'active']);
        $businessB = Business::create(['user_id' => $this->clientB->id, 'name' => 'Boutique B', 'status' => 'suspended']);

        $this->actingAs($this->clientA)->get('/businesses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('businesses.data', 1)
                ->where('businesses.data.0.id', $businessA->id)
                ->where('stats.total', 1)
                ->has('clientsList', 1)
            );

        $payload = ['user_id' => $this->clientB->id, 'name' => 'Pirate', 'status' => 'active'];
        $this->actingAs($this->clientA)->put("/businesses/{$businessB->id}", $payload)->assertForbidden();
        $this->actingAs($this->clientA)->delete("/businesses/{$businessB->id}")->assertForbidden();

        // Création : propriétaire forcé au marchand connecté.
        $this->actingAs($this->clientA)->post('/businesses', ['user_id' => $this->clientB->id, 'name' => 'Ma 2e boutique'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('businesses', ['name' => 'Ma 2e boutique', 'user_id' => $this->clientA->id]);

        // Un marchand ne peut pas modifier lui-même le statut (ex. lever une suspension).
        $businessA->update(['status' => 'suspended']);
        $this->actingAs($this->clientA)->put("/businesses/{$businessA->id}", ['name' => 'Boutique A', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('suspended', $businessA->refresh()->status);
    }

    public function test_driver_cannot_access_businesses(): void
    {
        $this->actingAs($this->driver)->get('/businesses')->assertForbidden();
    }

    /* ─────────────────────── Données financières (RIB) ─────────────────────── */

    public function test_bank_accounts_isolation_and_financial_permission(): void
    {
        $bank = Bank::create(['name' => 'Attijariwafa Bank', 'code_etablissement' => '007', 'status' => 'active']);
        $compteA = $this->compte($this->clientA, $bank, '007825000111111111111111');
        $compteB = $this->compte($this->clientB, $bank, '007825000222222222222222');

        $this->actingAs($this->clientA)->get('/banks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('comptes.data', 1)
                ->where('comptes.data.0.id', $compteA->id)
                ->missing('comptes.data.0.rib')
                ->where('stats.total_comptes', 1)
                ->has('owners', 1)
            );

        $update = ['titulaire_nom' => 'Pirate', 'bank_id' => $bank->id, 'rib' => '007825000999999999999999'];
        $this->actingAs($this->clientA)->put("/banks/comptes/{$compteB->id}", $update)->assertForbidden();
        $this->actingAs($this->clientA)->delete("/banks/comptes/{$compteB->id}")->assertForbidden();
        $this->assertSame('007825000222222222222222', $compteB->refresh()->rib);

        $this->actingAs($this->clientA)->post('/banks/comptes', [
            'owner_id' => $this->clientB->id,
            'titulaire_nom' => 'Compte A2',
            'bank_id' => $bank->id,
            'rib' => '007825000333333333333333',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('comptes_bancaires', ['titulaire_nom' => 'Compte A2', 'owner_id' => $this->clientA->id]);

        // Rôle livreur : pas de view_finances.
        $this->actingAs($this->driver)->get('/banks')->assertForbidden();
    }

    /* ─────────────────────── Référentiel national ─────────────────────── */

    public function test_villes_readable_by_all_but_writable_only_with_access_admin(): void
    {
        $payload = ['name_fr' => 'Rabat', 'zone_id' => $this->ville->zone_id];

        $this->actingAs($this->clientA)->get('/villes')->assertOk();
        $this->actingAs($this->clientA)->post('/villes', $payload)->assertForbidden();
        $this->actingAs($this->clientA)->put("/villes/{$this->ville->id}", $payload)->assertForbidden();
        $this->actingAs($this->clientA)->delete("/villes/{$this->ville->id}")->assertForbidden();

        $referentiel = $this->customRole('gestion_referentiels', [PermissionEnum::ACCESS_ADMIN->value]);
        $this->actingAs($referentiel)->post('/villes', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('villes', ['name_fr' => 'Rabat']);
    }

    /* ─────────────── Rôles personnalisés : attribution / révocation ─────────────── */

    public function test_custom_merchant_role_follows_effective_permissions_and_revocation(): void
    {
        $role = Role::create(['name' => 'marchand_premium', 'guard_name' => 'web']);
        $role->givePermissionTo([PerimetreColis::PROPRE_COMPTE, PermissionEnum::TRACK_COLIS->value]);

        $vip = User::factory()->create(['role' => null]);
        $vip->assignRole('marchand_premium');
        $this->colis($vip, 'OUA-VIP00001');

        $this->actingAs($vip)->get('/colis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('colis.data', 1)->where('colis.data.0.code', 'OUA-VIP00001'));
        $this->actingAs($vip)->get('/banks')->assertForbidden();

        // Attribution d'une permission au rôle (méthode officielle Spatie, cache invalidé par le package).
        $role->givePermissionTo(PermissionEnum::VIEW_FINANCES->value);
        $this->actingAs($vip->fresh())->get('/banks')->assertOk();

        // Révocation.
        $role->revokePermissionTo([PermissionEnum::TRACK_COLIS->value, PermissionEnum::VIEW_FINANCES->value]);
        $this->actingAs($vip->fresh())->get('/colis')->assertForbidden();
        $this->actingAs($vip->fresh())->get('/banks')->assertForbidden();
    }

    public function test_guest_is_redirected_on_every_protected_route(): void
    {
        foreach (['/colis', '/businesses', '/clients', '/livreurs', '/villes', '/banks', '/dashboard'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->delete("/colis/{$this->colisA->id}")->assertRedirect('/login');
    }

    /* ─────────────────────────── Helpers ─────────────────────────── */

    private function userWithRole(string $role, UserRole $legacy): User
    {
        $user = User::factory()->create(['role' => $legacy]);
        $user->assignRole($role);

        return $user;
    }

    /** @param array<int, string> $permissions */
    private function customRole(string $name, array $permissions): User
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $user = User::factory()->create(['role' => null]);
        $user->assignRole($name);

        return $user;
    }

    private function colis(User $client, string $code, ?User $driver = null): Command
    {
        return Command::create([
            'code' => $code,
            'client_id' => $client->id,
            'livreur_id' => $driver?->id,
            'destinataire_nom' => 'Destinataire',
            'destinataire_telephone' => '0600000000',
            'destinataire_ville' => 'Casablanca',
            'destinataire_ville_id' => $this->ville->id,
            'prix_brut' => 100,
            'state' => 'Nouveau Colis',
            'state_group' => 'nouveau',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function colisPayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->clientA->id,
            'destinataire_nom' => 'Destinataire',
            'destinataire_telephone' => '0600000000',
            'destinataire_ville_id' => $this->ville->id,
            'prix_brut' => 120,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function userPayload(string $email, array $overrides = []): array
    {
        return array_merge([
            'nom_complet' => 'Utilisateur Test',
            'email' => $email,
            'telephone' => '0611111111',
            'password' => 'Secret#2026x',
        ], $overrides);
    }

    private function compte(User $owner, Bank $bank, string $rib): CompteBancaire
    {
        return CompteBancaire::create([
            'owner_id' => $owner->id,
            'owner_type' => 'client',
            'titulaire_nom' => 'Titulaire '.$owner->id,
            'bank_id' => $bank->id,
            'rib' => $rib,
            'is_default' => true,
        ]);
    }
}
