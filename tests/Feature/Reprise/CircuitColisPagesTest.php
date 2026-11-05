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
        $this->assertSame([2, 1, 1, 2, 1, 1], array_map($total, ['/bons-livraison', '/bons-envoi', '/bons-ramassage', '/bons-retour', '/bons-retour?sous_type=client', '/bons-retour?sous_type=livreur']));
        $this->actingAs($this->admin)->get('/bons-retour-livreur')->assertRedirect('/bons-retour?sous_type=livreur');
        $this->actingAs($this->admin)->get('/bons-retour')->assertInertia(fn (Assert $p) => $p
            ->where('sousTypes', ['tous' => 2, 'client' => 1, 'livreur' => 1])->where('page.sous_type', null));
        $this->actingAs($this->admin)->get('/bons-livraison')->assertInertia(fn (Assert $p) => $p
            ->component('Bons/Index')->where('type', 'livraison')->where('page.titre', 'Bons de livraison'));

        $this->actingAs($this->admin)->get('/bons-retour?sous_type=livreur')->assertInertia(fn (Assert $p) => $p->has('bons.data', 1)->where('bons.data.0.code', 'BRL-05'));
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

    public function test_impression_des_bons(): void
    {
        foreach (['bons_livraison' => 'livraison', 'bons_envoi' => 'envoi', 'bon_ramassage' => 'ramassage', 'bons_retour' => 'retour'] as $table => $type) {
            $bon = DB::table($table)->orderBy('id')->first();
            $html = $this->actingAs($this->admin)->get("/bons/{$type}/{$bon->id}/imprimer")->assertOk()->getContent();
            $this->assertStringContainsString($bon->code, $html);
            $this->assertStringContainsString('<svg', $html);              // code-barres du bon
            $this->assertStringContainsString('Signature', $html);
        }

        $bl = DB::table('bons_livraison')->where('source_bls_id', 1)->value('id');
        $html = $this->actingAs($this->admin)->get("/bons/livraison/{$bl}/imprimer")->getContent();
        $this->assertStringContainsString('Bon de livraison', $html);
        $this->assertStringContainsString('Mbarek livreur', $html);
        $this->assertStringContainsString('OIS-100', $html);
        $this->assertStringContainsString('Colis absent de l’ancienne base', $html); // colis #99999

        $this->actingAs($this->client)->get("/bons/livraison/{$bl}/imprimer")->assertForbidden();
        $this->actingAs($this->admin)->get('/bons/inconnu/1/imprimer')->assertNotFound();
    }

    public function test_etiquettes_colis(): void
    {
        $html = $this->actingAs($this->admin)->get('/colis/etiquettes?ids=104,100,abc,100')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'class="etiquette"'));
        $this->assertLessThan(strpos($html, 'OIS-100'), strpos($html, 'OIS-104')); // ordre demandé
        $this->assertStringContainsString('À encaisser', $html);
        $this->assertStringContainsString('Anouar', $html);                        // enseigne expéditrice
        $this->assertStringContainsString('<svg', $html);

        // Périmètre : un marchand n'imprime que ses colis.
        DB::table('commands')->where('id', 104)->update(['client_id' => 1]);
        $html = $this->actingAs($this->client)->get('/colis/etiquettes?ids=104,100')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'class="etiquette"'));
        $this->actingAs($this->client)->get('/colis/etiquettes?ids=104')->assertForbidden();
        $this->actingAs($this->admin)->get('/colis/etiquettes')->assertNotFound();
        if (getenv('APERCU_ETIQUETTE')) {
            file_put_contents(getenv('APERCU_ETIQUETTE'), $this->actingAs($this->admin)->get('/colis/etiquettes?ids=103,100')->getContent());
        }
    }

    public function test_vues_ajouter_et_nouveaux_colis(): void
    {
        $this->actingAs($this->admin)->get('/colis/nouveaux')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Colis/Index')
            ->where('vue.code', 'nouveaux')
            ->where('vue.titre', 'Nouveaux colis')
            ->where('filters.onglet', 'tous')
            ->where('colis.data', fn ($lignes) => collect($lignes)->every(fn ($c) => $c['state_group'] === 'nouveau')));

        $this->actingAs($this->admin)->get('/colis/ajouter')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Colis/Ajouter')->where('estAdmin', true)->has('villes')->has('clients'));

        // Estimation des frais (aucun tarif dans cette base miniature → manquant).
        $temara = DB::table('villes')->where('name_fr', 'Temara')->value('id');
        $this->actingAs($this->admin)->getJson("/colis/estimation?ville_id={$temara}&client_id=45")->assertOk()->assertJsonPath('manquant', true);

        // Création depuis la page : statut du référentiel, historique, note en message, retour à la liste.
        $this->actingAs($this->admin)->post('/colis', [
            'client_id' => 45, 'destinataire_nom' => 'Salma', 'destinataire_telephone' => '0611223344',
            'destinataire_ville_id' => $temara, 'prix_brut' => 150, 'note' => 'Appeler avant 18 h', 'depuis_page' => true, 'apres' => 'liste',
        ])->assertRedirect('/colis');
        $c = DB::table('commands')->where('destinataire_nom', 'Salma')->first();
        $this->assertSame('Ajouté', $c->state);
        $this->assertSame('nouveau', $c->state_group);
        $this->assertNotNull($c->statut_id);
        $this->assertTrue(DB::table('command_status_histories')->where('command_id', $c->id)->where('to_state', 'Ajouté')->exists());
        $this->assertSame('Appeler avant 18 h', DB::table('note_messages')->where('command_id', $c->id)->value('message'));

        // « Enregistrer et ajouter un autre » : retour sur le formulaire.
        $this->actingAs($this->admin)->post('/colis', [
            'client_id' => 45, 'destinataire_nom' => 'Karim', 'destinataire_telephone' => '0611223355',
            'destinataire_ville_id' => $temara, 'prix_brut' => 90, 'depuis_page' => true, 'apres' => 'nouveau',
        ])->assertRedirect('/colis/ajouter');
    }

    public function test_options_essayage_et_echange_a_la_creation(): void
    {
        $temara = DB::table('villes')->where('name_fr', 'Temara')->value('id');
        $base = ['client_id' => 45, 'destinataire_telephone' => '0611223344', 'destinataire_ville_id' => $temara, 'prix_brut' => 100, 'depuis_page' => true];

        // Essayage sans ouverture : refusé par la règle (enregistré à faux).
        $this->actingAs($this->admin)->post('/colis', $base + ['destinataire_nom' => 'A', 'essayage_autorise' => true, 'ouverture_autorisee' => false])->assertSessionHasNoErrors();
        $this->assertFalse((bool) DB::table('commands')->where('destinataire_nom', 'A')->value('essayage_autorise'));
        $this->actingAs($this->admin)->post('/colis', $base + ['destinataire_nom' => 'B', 'essayage_autorise' => true, 'ouverture_autorisee' => true])->assertSessionHasNoErrors();
        $this->assertTrue((bool) DB::table('commands')->where('destinataire_nom', 'B')->value('essayage_autorise'));

        // Échange : l'ancien colis est obligatoire, doit exister, et un code en double est refusé.
        $this->actingAs($this->admin)->post('/colis', $base + ['destinataire_nom' => 'C', 'echange' => true])->assertSessionHasErrors('ancien_colis');
        $this->actingAs($this->admin)->post('/colis', $base + ['destinataire_nom' => 'C', 'echange' => true, 'ancien_colis' => 'INCONNU'])->assertSessionHasErrors('ancien_colis');
        $this->actingAs($this->admin)->post('/colis', $base + ['destinataire_nom' => 'C', 'echange' => true, 'ancien_colis' => 'CODE1'])->assertSessionHasErrors('ancien_colis');
        $this->actingAs($this->admin)->post('/colis', $base + ['destinataire_nom' => 'C', 'echange' => true, 'ancien_colis' => 'OIS-100'])->assertSessionHasNoErrors();
        $c = DB::table('commands')->where('destinataire_nom', 'C')->first();
        $this->assertSame(100, (int) $c->parent_echange_id);
        $this->assertSame('OIS-100', $c->code_echange);
        $this->actingAs($this->admin)->post('/colis', $base + ['destinataire_nom' => 'D', 'echange' => true, 'ancien_colis' => '102'])->assertSessionHasNoErrors();
        $this->assertSame(102, (int) DB::table('commands')->where('destinataire_nom', 'D')->value('parent_echange_id'));

        // Un marchand ne peut pas désigner le colis d'un autre.
        DB::table('commands')->where('id', 104)->update(['client_id' => 1]);
        $this->actingAs($this->client)->post('/colis', $base + ['destinataire_nom' => 'E', 'echange' => true, 'ancien_colis' => 'OIS-104'])->assertSessionHasErrors('ancien_colis');

        // Modification d'un échange sans ressaisie : l'ancien colis lié est conservé.
        $this->actingAs($this->admin)->put("/colis/{$c->id}", [
            'client_id' => 45, 'destinataire_nom' => 'C2', 'destinataire_telephone' => '0611223344', 'destinataire_ville_id' => $temara, 'prix_brut' => 100, 'echange' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame(100, (int) DB::table('commands')->find($c->id)->parent_echange_id);
    }
}
