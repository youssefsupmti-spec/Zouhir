<?php

namespace Tests\Feature\Reprise;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Pages du circuit colis et de la reprise, alimentées par une reprise réelle de la base miniature. */
class CircuitColisPagesTest extends TestCase
{
    use LegacyFixture;
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerLegacy();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::findOrFail(1);
        $this->admin->assignRole('admin');
        $this->client = User::findOrFail(45);
        $this->client->assignRole('client');
        $this->artisan('reprise:legacy')->assertSuccessful();
    }

    public function test_page_reprise_onglets_filtres_et_decision(): void
    {
        $this->actingAs($this->admin)->get('/reprise')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reprise/Index')
            ->where('filters.statut', 'ouverte')
            ->where('compteurs.tous', DB::table('reprise_anomalies')->count())
            ->has('compteurs.villes')
            ->where('can.manage', true));

        $this->actingAs($this->admin)->get('/reprise?domaine=villes&code=ville_introuvable')->assertInertia(fn (Assert $p) => $p
            ->has('anomalies.data', 1)
            ->where('anomalies.data.0.valeur_legacy', 'Ville Fantome')
            ->where('anomalies.data.0.nb_elements', 1));

        $id = DB::table('reprise_anomalies')->where('code', 'ville_introuvable')->value('id');
        $this->actingAs($this->admin)->put("/reprise/{$id}", ['statut' => 'resolue'])->assertSessionHasErrors('decision');
        $this->actingAs($this->admin)->put("/reprise/{$id}", ['statut' => 'resolue', 'decision' => 'Ville corrigée'])->assertSessionHasNoErrors();
        $this->assertSame('resolue', DB::table('reprise_anomalies')->where('id', $id)->value('statut'));
        $this->assertSame(1, (int) DB::table('reprise_anomalies')->where('id', $id)->value('traite_par'));

        $this->actingAs($this->admin)->get('/reprise?statut=resolue')->assertInertia(fn (Assert $p) => $p->has('anomalies.data', 1));

        $csv = $this->actingAs($this->admin)->get('/reprise/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('ville_introuvable', $csv);

        $this->actingAs($this->client)->get('/reprise')->assertForbidden();
        $this->actingAs($this->client)->put("/reprise/{$id}", ['statut' => 'ouverte'])->assertForbidden();
    }

    public function test_pages_bons_par_famille_et_detail(): void
    {
        $this->actingAs($this->admin)->get('/bons')->assertRedirect('/bons-livraison');
        $total = fn (string $url) => $this->actingAs($this->admin)->get($url)->assertOk()->viewData('page')['props']['stats']['total'];
        $this->assertSame([2, 1, 1, 1, 1], array_map($total, ['/bons-livraison', '/bons-envoi', '/bons-ramassage', '/bons-retour-client', '/bons-retour-livreur']));
        $this->actingAs($this->admin)->get('/bons-livraison')->assertInertia(fn (Assert $p) => $p
            ->component('Bons/Index')->where('type', 'livraison')->where('page.titre', 'Bons de livraison'));

        $this->actingAs($this->admin)->get('/bons-retour-livreur')->assertInertia(fn (Assert $p) => $p->has('bons.data', 1)->where('bons.data.0.code', 'BRL-05'));
        $this->actingAs($this->admin)->get('/bons-livraison?search=OIS-104')->assertInertia(fn (Assert $p) => $p->has('bons.data', 1));
        $this->actingAs($this->admin)->get('/bons-livraison?statut=traite')->assertInertia(fn (Assert $p) => $p->has('bons.data', 1));

        $bl = DB::table('bons_livraison')->where('source_bls_id', 1)->value('id');
        $this->actingAs($this->admin)->get("/bons/livraison/{$bl}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Bons/Show')
            ->has('lignes', 3)
            ->where('lignes.2.command_id', null)
            ->where('lignes.2.legacy_command_id', 99999)
            ->where('lignes.0.etat_actuel', 'Livré')
            ->has('anomalies'));

        $this->actingAs($this->admin)->get('/bons/inconnu/1')->assertNotFound();
        $this->actingAs($this->client)->get('/bons-livraison')->assertForbidden();
    }

    public function test_page_statuts_et_reclassement_des_colis(): void
    {
        $this->actingAs($this->admin)->get('/statuts')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Statuts/Index')
            ->where('stats.hors_referentiel', 2));

        $statut = DB::table('statuts')->where('libelle', 'pas de réponse 3 fois')->first();
        $this->actingAs($this->admin)->put("/statuts/{$statut->id}", ['groupe' => 'retour_client', 'couleur' => '#FF7700', 'actif' => false])->assertSessionHasNoErrors();
        $this->assertSame('retour_client', DB::table('commands')->find(101)->state_group);

        $this->actingAs($this->client)->get('/statuts')->assertForbidden();
    }

    public function test_page_reseau_agences_livreurs_partenaires(): void
    {
        $this->actingAs($this->admin)->get('/reseau?onglet=livreurs')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reseau/Index')
            ->has('agences', 1)
            ->where('agences.0.est_principale', true)
            ->has('partenaires', 1)
            ->where('partenaires.0.bons', 1)
            ->where('membres.0.nom', 'Mbarek livreur'));

        $partenaire = DB::table('partenaires')->value('id');
        $this->actingAs($this->admin)->put("/reseau/partenaires/{$partenaire}", ['raison_sociale' => 'Réseau national Abdelkrim', 'actif' => true])->assertSessionHasNoErrors();
        $this->assertSame('Réseau national Abdelkrim', DB::table('partenaires')->value('raison_sociale'));

        $this->actingAs($this->client)->get('/reseau')->assertForbidden();
    }

    public function test_suivi_colis_et_changement_de_statut_trace(): void
    {
        $this->actingAs($this->admin)->getJson('/colis/100/suivi')->assertOk()
            ->assertJsonCount(4, 'historique')
            ->assertJsonCount(2, 'notes')
            ->assertJsonPath('bons.0.code', 'BL-01');

        $this->actingAs($this->admin)->patch('/colis/100/state', ['state_group' => 'retour_client', 'note' => 'Client a refusé'])->assertSessionHasNoErrors();

        $this->assertStringContainsString('<b>Livreur: </b>', DB::table('commands')->find(100)->note); // note d'origine intacte
        $this->assertTrue(DB::table('note_messages')->where('command_id', 100)->where('source', 'application')->where('message', 'Client a refusé')->exists());
        $this->assertTrue(DB::table('command_status_histories')->where('command_id', 100)->where('change_source', 'application')->where('from_state', 'Livré')->exists());

        // Un colis d'un autre expéditeur n'est pas visible par ce client.
        DB::table('commands')->where('id', 104)->update(['client_id' => 1]);
        $this->actingAs($this->client)->getJson('/colis/104/suivi')->assertForbidden();
    }
}
