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
use Tests\TestCase;

class ManagementMutationsCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_update_and_delete_business(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $client = User::factory()->create(['role' => UserRole::CLIENT]);

        // 1. Create
        $response = $this->actingAs($admin)->post('/businesses', [
            'user_id' => $client->id,
            'name' => 'Boutique Atlas',
            'phone' => '0612345678',
            'status' => 'active',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('businesses', [
            'user_id' => $client->id,
            'name' => 'Boutique Atlas',
        ]);

        $business = Business::where('name', 'Boutique Atlas')->first();

        // 2. Scoped uniqueness: same client cannot create duplicate
        $dup = $this->actingAs($admin)->post('/businesses', [
            'user_id' => $client->id,
            'name' => 'Boutique Atlas',
        ]);
        $dup->assertSessionHasErrors(['name']);

        // 3. Update
        $this->actingAs($admin)->put("/businesses/{$business->id}", [
            'user_id' => $client->id,
            'name' => 'Boutique Atlas Renommée',
            'status' => 'suspended',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name' => 'Boutique Atlas Renommée',
            'status' => 'suspended',
        ]);

        // 4. Delete
        $this->actingAs($admin)->delete("/businesses/{$business->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('businesses', ['id' => $business->id]);
    }

    public function test_can_create_update_and_delete_client(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        // 1. Create client with initial business
        $this->actingAs($admin)->post('/clients', [
            'nom_complet' => 'Marchand Casablanca',
            'email' => 'marchand.casa@test.ma',
            'telephone' => '0661998877',
            'ville_name' => 'Casablanca',
            'store_name' => 'Boutique Casa Mode',
            'status' => 'active',
            'password' => 'Secret#2026x',
        ])->assertSessionHasNoErrors();

        $client = User::where('email', 'marchand.casa@test.ma')->first();
        $this->assertNotNull($client);
        $this->assertEquals('client', $client->role?->value ?? $client->role);
        $this->assertDatabaseHas('businesses', [
            'user_id' => $client->id,
            'name' => 'Boutique Casa Mode',
        ]);

        // 2. Update
        $this->actingAs($admin)->put("/clients/{$client->id}", [
            'nom_complet' => 'Marchand Casablanca Modifié',
            'email' => 'marchand.casa@test.ma',
            'telephone' => '0661000000',
            'status' => 'suspended',
        ])->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertEquals('Marchand Casablanca Modifié', $client->nom_complet);
        $this->assertFalse($client->active);

        // 3. Delete
        $this->actingAs($admin)->delete("/clients/{$client->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('users', ['id' => $client->id]);
    }

    public function test_can_create_update_and_delete_livreur(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        // 1. Create livreur
        $this->actingAs($admin)->post('/livreurs', [
            'nom_complet' => 'Chauffeur Rabat',
            'email' => 'chauffeur.rbt@test.ma',
            'telephone' => '0677112233',
            'ville_name' => 'Rabat',
            'status' => 'active',
            'password' => 'Secret#2026x',
        ])->assertSessionHasNoErrors();

        $livreur = User::where('email', 'chauffeur.rbt@test.ma')->first();
        $this->assertNotNull($livreur);
        $this->assertEquals('livreur', $livreur->role?->value ?? $livreur->role);

        // 2. Update
        $this->actingAs($admin)->put("/livreurs/{$livreur->id}", [
            'nom_complet' => 'Chauffeur Rabat Salé',
            'email' => 'chauffeur.rbt@test.ma',
            'telephone' => '0677999999',
            'ville_name' => 'Salé',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $livreur->refresh();
        $this->assertEquals('Salé', $livreur->ville_name);

        // 3. Delete
        $this->actingAs($admin)->delete("/livreurs/{$livreur->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('users', ['id' => $livreur->id]);
    }

    public function test_can_create_update_and_delete_ville(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $zone = Zone::create(['name' => 'Zone Nord', 'code' => 'NORD']);

        // 1. Create ville
        $this->actingAs($admin)->post('/villes', [
            'name_fr' => 'Tétouan',
            'name_ar' => 'تطوان',
            'zone_id' => $zone->id,
        ])->assertSessionHasNoErrors();

        $ville = Ville::where('name_fr', 'Tétouan')->first();
        $this->assertNotNull($ville);
        $this->assertEquals('NORD', $ville->code_zone);

        // 2. Update
        $this->actingAs($admin)->put("/villes/{$ville->id}", [
            'name_fr' => 'Tétouan Centre',
            'name_ar' => 'تطوان المركز',
            'zone_id' => $zone->id,
        ])->assertSessionHasNoErrors();

        $ville->refresh();
        $this->assertEquals('Tétouan Centre', $ville->name_fr);

        // 3. Delete
        $this->actingAs($admin)->delete("/villes/{$ville->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('villes', ['id' => $ville->id]);
    }

    public function test_can_create_update_and_delete_compte_bancaire(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $client = User::factory()->create(['role' => UserRole::CLIENT]);
        $bank = Bank::create(['name' => 'CIH Bank', 'code_etablissement' => '230']);

        // 1. Invalid RIB length rejected
        $this->actingAs($admin)->post('/banks/comptes', [
            'owner_id' => $client->id,
            'owner_type' => 'client',
            'titulaire_nom' => 'Marchand Test',
            'bank_id' => $bank->id,
            'rib' => '12345',
        ])->assertSessionHasErrors(['rib']);

        // 2. Valid 24-digit BAM RIB
        $this->actingAs($admin)->post('/banks/comptes', [
            'owner_id' => $client->id,
            'owner_type' => 'client',
            'titulaire_nom' => 'Marchand Test CIH',
            'bank_id' => $bank->id,
            'rib' => '230810123456789012345678',
            'is_default' => true,
        ])->assertSessionHasNoErrors();

        $compte = CompteBancaire::where('titulaire_nom', 'Marchand Test CIH')->first();
        $this->assertNotNull($compte);
        $this->assertTrue($compte->is_default);

        // 3. Update
        $this->actingAs($admin)->put("/banks/comptes/{$compte->id}", [
            'titulaire_nom' => 'Marchand Test CIH Principal',
            'bank_id' => $bank->id,
            'is_default' => true,
        ])->assertSessionHasNoErrors();

        $compte->refresh();
        $this->assertEquals('Marchand Test CIH Principal', $compte->titulaire_nom);

        // 4. Delete
        $this->actingAs($admin)->delete("/banks/comptes/{$compte->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('comptes_bancaires', ['id' => $compte->id]);
    }
}
