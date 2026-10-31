<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\UserRole;
use App\Models\Business;
use App\Models\Command;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ColisManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_on_colis_index(): void
    {
        $this->get('/colis')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_colis_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $client = User::factory()->create(['role' => UserRole::CLIENT]);
        $zone = Zone::create(['name' => 'Casablanca', 'code' => 'CAS']);
        $ville = Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $zone->id]);

        Command::create([
            'code' => 'OUA-TEST1234',
            'client_id' => $client->id,
            'destinataire_nom' => 'Fatima Alami',
            'destinataire_telephone' => '0612345678',
            'destinataire_ville' => 'Casablanca',
            'destinataire_ville_id' => $ville->id,
            'prix_brut' => 250.00,
            'state' => 'Livré avec succès',
            'state_group' => 'livre',
            'livre_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/colis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Colis/Index')
                ->has('colis.data', 1)
                ->where('stats.total', 1)
                ->where('stats.livres', 1)
                ->where('stats.total_crbt', 250)
            );
    }

    public function test_can_create_new_colis_with_auto_generated_awb(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $client = User::factory()->create(['role' => UserRole::CLIENT]);
        $business = Business::create([
            'user_id' => $client->id,
            'name' => 'Boutique Alpha',
            'status' => 'active',
        ]);
        $zone = Zone::create(['name' => 'Rabat-Témara', 'code' => 'RBT']);
        $ville = Ville::create([
            'name_fr' => 'Temara',
            'zone_id' => $zone->id,
            'code_zone' => 'RBT',
        ]);

        $response = $this->actingAs($admin)->post('/colis', [
            'client_id' => $client->id,
            'business_id' => $business->id,
            'produit' => 'Kaftan de Soie',
            'quantite' => 1,
            'destinataire_nom' => 'Sara Benjelloun',
            'destinataire_telephone' => '0661122334',
            'destinataire_ville_id' => $ville->id,
            'destinataire_adresse' => 'Avenue Hassan II, Appt 4',
            'prix_brut' => 450.00,
            'frais_livraison_client' => 35.00,
            'ouverture_autorisee' => true,
            'echange' => false,
            'note' => 'Appeler 10 min avant livraison',
        ]);

        $response->assertSessionHasNoErrors();

        $colis = Command::where('destinataire_nom', 'Sara Benjelloun')->first();
        $this->assertNotNull($colis);
        $this->assertStringStartsWith('OUA-', $colis->code);
        $this->assertEquals('Boutique Alpha', $colis->business_name);
        $this->assertEquals('Temara', $colis->destinataire_ville);
        $this->assertEquals('RBT', $colis->destinataire_zone_code);
        $this->assertEquals(450.00, $colis->prix_brut);
        $this->assertTrue($colis->ouverture_autorisee);
        $this->assertEquals('nouveau', $colis->state_group);
    }

    public function test_can_update_colis_and_change_state_with_livreur(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $client = User::factory()->create(['role' => UserRole::CLIENT]);
        $livreur = User::factory()->create([
            'nom_complet' => 'Coursier Youssef',
            'role' => UserRole::LIVREUR,
            'telephone' => '0622334455',
        ]);
        $zone = Zone::create(['name' => 'Casablanca', 'code' => 'CAS']);
        $ville = Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $zone->id]);

        $colis = Command::create([
            'code' => 'OUA-TEST8888',
            'client_id' => $client->id,
            'destinataire_nom' => 'Client Initial',
            'destinataire_telephone' => '0600000000',
            'destinataire_ville' => 'Casablanca',
            'destinataire_ville_id' => $ville->id,
            'prix_brut' => 150.00,
            'state' => 'Nouveau Colis',
            'state_group' => 'nouveau',
        ]);

        // 1. Update basic info
        $this->actingAs($admin)->put("/colis/{$colis->id}", [
            'destinataire_nom' => 'Client Modifié',
            'destinataire_telephone' => '0611111111',
            'destinataire_ville_id' => $ville->id,
            'prix_brut' => 180.00,
        ])->assertSessionHasNoErrors();

        $colis->refresh();
        $this->assertEquals('Client Modifié', $colis->destinataire_nom);
        $this->assertEquals(180.00, $colis->prix_brut);

        // 2. Change state to livre with livreur assignment
        $this->actingAs($admin)->patch("/colis/{$colis->id}/state", [
            'state_group' => 'livre',
            'livreur_id' => $livreur->id,
            'note' => 'Colis livré et payé en espèces',
        ])->assertSessionHasNoErrors();

        $colis->refresh();
        $this->assertEquals('livre', $colis->state_group);
        $this->assertEquals($livreur->id, $colis->livreur_id);
        $this->assertEquals('Coursier Youssef', $colis->livreur_nom);
        $this->assertNotNull($colis->livre_at);

        // 3. Delete / SoftDelete
        $this->actingAs($admin)->delete("/colis/{$colis->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('commands', ['id' => $colis->id]);
    }
}
