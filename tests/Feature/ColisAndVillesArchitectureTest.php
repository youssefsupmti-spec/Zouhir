<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\UserRole;
use App\Models\Business;
use App\Models\Command as ColisModel;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColisAndVillesArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_and_ville_creation_and_relations(): void
    {
        $zone = Zone::create([
            'name' => 'Zone Rabat-Témara',
            'code' => 'RBT',
            'description' => 'Hub principal',
        ]);

        $ville = Ville::create([
            'name_fr' => 'Temara',
            'name_ar' => 'تمارة',
            'zone_id' => $zone->id,
            'code_zone' => 'RBT',
        ]);

        $this->assertNotNull($zone->id);
        $this->assertNotNull($ville->id);
        $this->assertEquals('Temara', $ville->name_fr);
        $this->assertEquals($zone->id, $ville->zone->id);
        $this->assertCount(1, $zone->villes);
    }

    public function test_colis_creation_with_client_business_and_ville_relationships(): void
    {
        $client = User::factory()->create([
            'nom_complet' => 'Marchand Test',
            'role' => UserRole::CLIENT,
        ]);

        $business = Business::create([
            'user_id' => $client->id,
            'name' => 'Boutique Alpha',
            'status' => 'active',
        ]);

        $livreur = User::factory()->create([
            'nom_complet' => 'Livreur Express',
            'role' => UserRole::LIVREUR,
        ]);

        $zone = Zone::create(['name' => 'Zone Rabat', 'code' => 'RBT']);
        $ville = Ville::create(['name_fr' => 'Rabat', 'zone_id' => $zone->id, 'code_zone' => 'RBT']);

        $colis = ColisModel::create([
            'code' => 'OIS-2026-99999',
            'produit' => 'Pack Cosmétique',
            'quantite' => 2,
            'client_id' => $client->id,
            'business_id' => $business->id,
            'business_name' => $business->name,
            'nom_cliente' => $client->nom_complet,
            'livreur_id' => $livreur->id,
            'livreur_nom' => $livreur->nom_complet,
            'destinataire_nom' => 'Fatima Zahra',
            'destinataire_telephone' => '0612345678',
            'destinataire_adresse' => 'Agdal, Rabat',
            'destinataire_ville' => 'Rabat',
            'destinataire_ville_id' => $ville->id,
            'destinataire_zone_code' => 'RBT',
            'prix_brut' => 450.00,
            'state' => 'Livré',
            'state_group' => 'livre',
            'is_ramassee' => true,
            'ouverture_autorisee' => true,
        ]);

        $this->assertNotNull($colis->id);
        $this->assertEquals('OIS-2026-99999', $colis->code);
        $this->assertEquals($client->id, $colis->client->id);
        $this->assertEquals($business->id, $colis->business->id);
        $this->assertEquals($livreur->id, $colis->livreur->id);
        $this->assertEquals($ville->id, $colis->ville->id);
        $this->assertEquals('450.00', (string) $colis->prix_brut);
        $this->assertEquals(1, ColisModel::livre()->count());
        $this->assertEquals(0, ColisModel::enCours()->count());
        $this->assertEquals(1, ColisModel::forClient($client->id)->count());
    }
}
