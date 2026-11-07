<?php

namespace Tests\Feature\Reseau;

use App\Models\Command;
use App\Models\DeliveryPartner;
use App\Models\Statut;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneCouverture;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Pages Flotte régionale / Réseau national / Couverture des zones (C132, rapport 63 — lots 2 et 3). */
class ReseauNationalPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Zone $temara;

    private Zone $casa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->client = User::factory()->create(['role' => 'client']);
        $this->client->assignRole('client');
        $this->temara = Zone::create(['name' => 'Témara centre', 'code' => 'TEM1', 'type' => 'locale']);
        $this->casa = Zone::create(['name' => 'Grand Casablanca', 'code' => 'CASA', 'type' => 'nationale']);
    }

    private function livreur(string $nom, bool $actif = true): User
    {
        $u = User::factory()->create(['nom_complet' => $nom, 'role' => 'livreur', 'active' => $actif]);
        $u->assignRole('livreur');

        return $u;
    }

    public function test_acces_reserve_au_personnel(): void
    {
        foreach (['/partenaires', '/partenaires/tarifs', '/partenaires/api', '/zones/couverture'] as $url) {
            $this->actingAs($this->client)->get($url)->assertForbidden();
        }
    }

    public function test_coursiers_onglets_archives_exclusion_partenaires_et_zones(): void
    {
        $actif = $this->livreur('Coursier actif');
        $this->livreur('Coursier inactif', false);
        $archive = $this->livreur('Coursier archivé');
        $archive->delete();
        $comptePartenaire = $this->livreur('Compte technique partenaire');
        DeliveryPartner::create(['code' => 'CTM', 'name' => 'CTM Messagerie', 'user_id' => $comptePartenaire->id, 'is_active' => true]);

        $this->actingAs($this->admin)->get('/livreurs')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Livreurs/Index')->where('onglet', 'tous')
            ->has('livreurs.data', 2)->where('stats.total', 2)->where('stats.active', 1)->where('stats.archives', 1));
        $this->actingAs($this->admin)->get('/livreurs?onglet=actifs')->assertInertia(fn (Assert $p) => $p
            ->has('livreurs.data', 1)->where('livreurs.data.0.nom_complet', 'Coursier actif'));
        $this->actingAs($this->admin)->get('/livreurs?onglet=archives')->assertInertia(fn (Assert $p) => $p
            ->has('livreurs.data', 1)->where('livreurs.data.0.archive', true));

        // Restauration (jamais de suppression physique).
        $this->actingAs($this->admin)->post("/livreurs/{$archive->id}/restaurer")->assertRedirect();
        $this->assertNull($archive->fresh()->deleted_at);

        // Édition rapide des zones (table livreur_zones).
        $this->actingAs($this->admin)->put("/livreurs/{$actif->id}/zones", ['zones' => [$this->temara->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('livreur_zones', ['user_id' => $actif->id, 'zone_id' => $this->temara->id, 'zone_type' => 'livraison']);
        $this->actingAs($this->admin)->get('/livreurs?onglet=actifs')->assertInertia(fn (Assert $p) => $p->where('livreurs.data.0.zones.0.code', 'TEM1'));
        $this->actingAs($this->admin)->put("/livreurs/{$actif->id}/zones", ['zones' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('livreur_zones', ['user_id' => $actif->id]);

        // Badges réels de la barre latérale.
        $this->actingAs($this->admin)->get('/livreurs')->assertInertia(fn (Assert $p) => $p
            ->where('navCompteurs.coursiersActifs', 2)->where('navCompteurs.partenairesActifs', 1)->where('auth.can.partenaires', true));
    }

    public function test_partenaires_crud_corbeille_et_tarifs_de_transit(): void
    {
        $this->actingAs($this->admin)->post('/partenaires', ['name' => 'Atlas Transit', 'type_partenaire' => 'transporteur_ligne', 'ice' => '001234567000089', 'is_active' => true])
            ->assertSessionHasNoErrors();
        $p = DeliveryPartner::where('code', 'ATLASTRANSIT')->firstOrFail();
        $this->actingAs($this->admin)->post('/partenaires', ['name' => 'Atlas Transit', 'type_partenaire' => 'transporteur_ligne', 'is_active' => true])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/partenaires', ['name' => 'X', 'type_partenaire' => 'inconnu', 'is_active' => true])->assertSessionHasErrors('type_partenaire');

        $this->actingAs($this->admin)->get('/partenaires')->assertOk()->assertInertia(fn (Assert $a) => $a
            ->component('Partenaires/Index')->has('partenaires', 1)->where('partenaires.0.ice', '001234567000089')->where('partenaires.0.api.jeton', false));

        // Grille : desservie avec tarif, puis retirée.
        $cellule = ['delivery_partner_id' => $p->id, 'zone_id' => $this->casa->id, 'desservie' => true, 'tarif' => 18, 'delai' => 48, 'exclusif' => true];
        $this->actingAs($this->admin)->put('/partenaires/tarifs', ['cellules' => [$cellule]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('delivery_partner_zones', ['delivery_partner_id' => $p->id, 'zone_id' => $this->casa->id, 'delai_garanti_h' => 48, 'is_exclusif' => true]);
        $this->actingAs($this->admin)->get('/partenaires/tarifs')->assertInertia(fn (Assert $a) => $a
            ->component('Partenaires/Tarifs')->has('zones', 1)->where("cellules.{$p->id}-{$this->casa->id}.tarif", 18));
        $this->actingAs($this->admin)->put('/partenaires/tarifs', ['cellules' => [['desservie' => false] + $cellule]])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('delivery_partner_zones', ['delivery_partner_id' => $p->id]);

        // Corbeille puis restauration.
        $this->actingAs($this->admin)->delete("/partenaires/{$p->id}")->assertRedirect();
        $this->assertSoftDeleted('delivery_partners', ['id' => $p->id]);
        $this->actingAs($this->admin)->get('/partenaires?corbeille=1')->assertInertia(fn (Assert $a) => $a->has('partenaires', 1)->where('compteurs.corbeille', 1));
        $this->actingAs($this->admin)->post("/partenaires/{$p->id}/restaurer")->assertRedirect();
        $this->assertNotSoftDeleted('delivery_partners', ['id' => $p->id]);
    }

    public function test_passerelle_api_cle_revelee_une_fois_et_correspondances(): void
    {
        $p = DeliveryPartner::create(['code' => 'ATL', 'name' => 'Atlas', 'is_active' => true]);
        $refus = Statut::create(['libelle' => 'Refusé', 'groupe' => 'echec', 'actif' => true, 'origine' => 'referentiel_legacy']);

        $this->actingAs($this->admin)->post("/partenaires/{$p->id}/cle", ['type' => 'jeton'])->assertRedirect("/partenaires/api?partenaire={$p->id}");
        $hash = $p->fresh()->api_key_hash;
        $this->assertNotNull($hash);

        // Affiché à la page suivante seulement, et c'est bien le jeton dont on garde l'empreinte.
        $this->actingAs($this->admin)->get("/partenaires/api?partenaire={$p->id}")->assertInertia(function (Assert $a) use ($hash) {
            $a->component('Partenaires/Api')->where('courant.jeton', true)->where('courant.secret', false)
                ->where('revele.type', 'jeton')->where('revele.valeur', fn ($v) => hash('sha256', $v) === $hash);
        });
        $this->actingAs($this->admin)->get("/partenaires/api?partenaire={$p->id}")->assertInertia(fn (Assert $a) => $a->where('revele', null));

        $this->actingAs($this->admin)->post("/partenaires/{$p->id}/correspondances", ['partner_status' => 'refus_client', 'statut_id' => $refus->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('partner_status_mappings', ['delivery_partner_id' => $p->id, 'partner_status' => 'REFUS_CLIENT', 'colis_status' => 'Refusé']);
        $this->actingAs($this->admin)->post("/partenaires/{$p->id}/correspondances", ['partner_status' => 'REFUS_CLIENT', 'statut_id' => $refus->id])->assertSessionHasErrors('partner_status');

        $this->actingAs($this->admin)->put("/partenaires/{$p->id}/api", ['api_endpoint' => 'pas-une-url', 'format_payload' => 'json_standard'])->assertSessionHasErrors('api_endpoint');
        $this->actingAs($this->admin)->put("/partenaires/{$p->id}/api", ['api_endpoint' => 'https://atlas.example/hook', 'format_payload' => 'webhook_winning'])->assertSessionHasNoErrors();
        $this->assertSame('webhook_winning', $p->fresh()->format_payload);
    }

    public function test_coverage_board_affectation_jauge_et_bascule(): void
    {
        $titulaire = $this->livreur('Titulaire');
        $backup = $this->livreur('Remplaçant');
        $p = DeliveryPartner::create(['code' => 'ATL', 'name' => 'Atlas', 'is_active' => true]);

        // Les zones créées après la migration reçoivent leur ligne « à affecter ».
        $this->actingAs($this->admin)->get('/zones/couverture')->assertOk()->assertInertia(fn (Assert $a) => $a
            ->component('Zones/Couverture')->has('couvertures', 2)
            ->where('couvertures.0.zone.code', 'TEM1')->where('couvertures.0.type_couverture', 'regional_flotte')->where('couvertures.0.statut_couverture', 'non_couvert')
            ->where('couvertures.1.type_couverture', 'national_partenaire'));
        $tem = ZoneCouverture::where('zone_id', $this->temara->id)->firstOrFail();
        $nat = ZoneCouverture::where('zone_id', $this->casa->id)->firstOrFail();

        $this->actingAs($this->admin)->put("/zones/couverture/{$tem->id}", ['type_couverture' => 'regional_flotte', 'titulaire_user_id' => $titulaire->id, 'backup_user_id' => $titulaire->id])
            ->assertSessionHasErrors('backup_user_id');
        $this->actingAs($this->admin)->put("/zones/couverture/{$tem->id}", ['type_couverture' => 'regional_flotte', 'titulaire_user_id' => $this->client->id])
            ->assertSessionHasErrors('titulaire_user_id');
        $this->actingAs($this->admin)->put("/zones/couverture/{$tem->id}", ['type_couverture' => 'regional_flotte', 'titulaire_user_id' => $titulaire->id, 'backup_user_id' => $backup->id, 'capacite_journaliere' => 4])
            ->assertSessionHasNoErrors();
        $this->assertSame('optimal', $tem->fresh()->statut_couverture);
        $this->assertDatabaseHas('livreur_zones', ['user_id' => $backup->id, 'zone_id' => $this->temara->id]);

        $this->actingAs($this->admin)->put("/zones/couverture/{$nat->id}", ['type_couverture' => 'national_partenaire', 'delivery_partner_id' => $p->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('delivery_partner_zones', ['delivery_partner_id' => $p->id, 'zone_id' => $this->casa->id]);

        // Charge : 3 colis en cours sur 4 (75 %, orange) ; un colis livré n'est pas compté.
        foreach (['en_cours', 'en_cours', 'planifie', 'livre'] as $i => $groupe) {
            Command::create(['code' => "OEX-T{$i}", 'destinataire_zone_id' => $this->temara->id, 'livreur_id' => $titulaire->id, 'state' => 'x', 'state_group' => $groupe, 'prix_brut' => 100, 'destinataire_nom' => 'Client test', 'destinataire_telephone' => '0661000000', 'destinataire_adresse' => 'Témara', 'destinataire_ville' => 'Témara']);
        }
        $this->actingAs($this->admin)->get('/zones/couverture')->assertInertia(fn (Assert $a) => $a
            ->where('couvertures.0.charge', 3)->where('couvertures.0.taux', 75)->where('couvertures.1.partenaire.code', 'ATL'));

        // Bascule : le remplaçant reprend les colis en cours, pas le colis livré.
        $this->actingAs($this->admin)->post("/zones/couverture/{$tem->id}/basculer")->assertSessionHas('success');
        $tem->refresh();
        $this->assertSame($backup->id, (int) $tem->titulaire_user_id);
        $this->assertSame($titulaire->id, (int) $tem->backup_user_id);
        $this->assertSame('degrade', $tem->statut_couverture);
        $this->assertSame(3, DB::table('commands')->where('livreur_id', $backup->id)->count());
        $this->assertSame($titulaire->id, (int) DB::table('commands')->where('code', 'OEX-T3')->value('livreur_id'));

        // Pas de bascule sans remplaçant.
        $this->actingAs($this->admin)->post("/zones/couverture/{$nat->id}/basculer")->assertSessionHas('error');
    }
}
