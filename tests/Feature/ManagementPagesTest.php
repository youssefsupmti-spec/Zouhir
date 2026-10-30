<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\UserRole;
use App\Models\Bank;
use App\Models\Business;
use App\Models\CompteBancaire;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_on_management_routes(): void
    {
        $this->get('/businesses')->assertRedirect('/login');
        $this->get('/clients')->assertRedirect('/login');
        $this->get('/livreurs')->assertRedirect('/login');
        $this->get('/villes')->assertRedirect('/login');
        $this->get('/banks')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_businesses_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::CLIENT,
        ]);

        Business::create([
            'user_id' => $user->id,
            'name' => 'Boutique Alpha',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/businesses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Businesses/Index')
                ->has('businesses.data', 1)
                ->has('stats.total')
                ->where('stats.active', 1)
            );
    }

    public function test_authenticated_user_can_access_clients_index_with_masked_rib(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $client = User::factory()->create([
            'nom_complet' => 'Client Test',
            'role' => UserRole::CLIENT,
            'ville' => 'Casablanca',
        ]);

        $bank = Bank::create([
            'name' => 'CIH Bank',
            'code_etablissement' => '230',
        ]);

        CompteBancaire::create([
            'owner_id' => $client->id,
            'owner_type' => 'client',
            'titulaire_nom' => 'Client Test',
            'bank_id' => $bank->id,
            'rib' => '230810123456789012345678',
            'is_default' => true,
        ]);

        $this->actingAs($admin)
            ->get('/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 1)
                ->where('clients.data.0.comptes_bancaires.0.masked_rib', '2308 •••• •••• 5678')
                ->missing('clients.data.0.comptes_bancaires.0.rib') // RIB en clair exclu de la réponse
            );
    }

    public function test_authenticated_user_can_access_livreurs_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        User::factory()->create([
            'nom_complet' => 'Coursier Express',
            'role' => UserRole::LIVREUR,
            'ville' => 'Témara',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/livreurs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Livreurs/Index')
                ->has('livreurs.data', 1)
                ->where('stats.total', 1)
            );
    }

    public function test_authenticated_user_can_access_villes_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::CLIENT,
        ]);

        $zone = Zone::create([
            'name' => 'Rabat-Témara',
            'code' => 'RBT',
        ]);

        Ville::create([
            'name_fr' => 'Temara',
            'name_ar' => 'تمارة',
            'zone_id' => $zone->id,
            'code_zone' => 'RBT',
        ]);

        $this->actingAs($user)
            ->get('/villes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Villes/Index')
                ->has('villes.data', 1)
                ->has('zones', 1)
            );
    }

    public function test_authenticated_user_can_access_banks_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::CLIENT,
        ]);

        $bank = Bank::create([
            'name' => 'Attijariwafa Bank',
            'code_etablissement' => '007',
            'status' => 'active',
        ]);

        CompteBancaire::create([
            'owner_id' => $user->id,
            'owner_type' => 'client',
            'titulaire_nom' => 'Titulaire AWB',
            'bank_id' => $bank->id,
            'rib' => '007825000123456789012345',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->get('/banks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Banks/Index')
                ->has('comptes.data', 1)
                ->has('banks', 1)
                ->where('stats.total_banks', 1)
            );
    }
}
