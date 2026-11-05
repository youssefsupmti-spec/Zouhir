<?php

namespace Tests\Feature\Reprise;

use App\Models\EtatGroupe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Groupes d'état (etat_groupes), onglets-cartes et corbeille des colis, actions groupées. */
class GroupesEtCorbeilleTest extends TestCase
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

    public function test_groupes_systeme_et_classement_des_etats(): void
    {
        $this->assertSame(
            ['nouveau', 'en_cours', 'planifie', 'livre', 'echec', 'retour_agence', 'retour_stock', 'retour_client'],
            DB::table('etat_groupes')->where('axe', 'flux')->orderBy('sort_order')->pluck('code')->all(),
        );
        $groupe = fn (string $libelle) => DB::table('etat_groupe_etats')->join('etat_groupes', 'etat_groupes.id', '=', 'groupe_id')
            ->join('statuts', 'statuts.id', '=', 'etat_id')->where('statuts.libelle', $libelle)->value('etat_groupes.code');
        $this->assertSame('livre', $groupe('Livré'));
        $this->assertSame('retour_client', $groupe('Retour client reçu'));
        $this->assertSame('echec', $groupe('pas de réponse 3 fois'));
        $this->assertSame(0, DB::table('statuts')->whereNotIn('id', DB::table('etat_groupe_etats')->select('etat_id'))->count());

        $this->assertSame('retour_client', DB::table('commands')->find(103)->state_group);
        $this->assertSame('echec', DB::table('commands')->find(101)->state_group);
    }

    public function test_page_colis_onglets_cartes_et_corbeille_separee(): void
    {
        $this->actingAs($this->admin)->get('/colis')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Colis/Index')
            ->has('groupes', 7)               // « nouveau » a sa propre page
            ->where('onglets.tous', 4)        // ramassés hors « nouveau » ; #102 (Ajouté mais ramassé) hors listes
            ->where('onglets.corbeille', 1)
            ->where('onglets.livre', 2)
            ->where('filters.onglet', 'tous')
            ->where('filters.sort', 'updated_at')
            ->where('filters.direction', 'desc')
            ->has('colis.data', 4));

        $this->actingAs($this->admin)->get('/colis?onglet=corbeille')->assertInertia(fn (Assert $p) => $p
            ->has('colis.data', 1)->where('colis.data.0.id', 103));
        $this->actingAs($this->admin)->get('/colis?onglet=echec')->assertInertia(fn (Assert $p) => $p->has('colis.data', 2));
        // Ancien lien ?state_group= toujours compris.
        $this->actingAs($this->admin)->get('/colis?state_group=livre')->assertInertia(fn (Assert $p) => $p->where('filters.onglet', 'livre'));
    }

    public function test_actions_groupees_colis_corbeille_et_restauration(): void
    {
        $this->actingAs($this->admin)->post('/colis/groupe', ['action' => 'corbeille', 'ids' => [100, 104]])->assertSessionHas('success');
        $this->assertSoftDeleted('commands', ['id' => 100]);
        $this->assertSoftDeleted('commands', ['id' => 104]);

        $this->actingAs($this->admin)->post('/colis/groupe', ['action' => 'restaurer', 'ids' => [100, 103]])->assertSessionHas('success');
        $this->assertNotSoftDeleted('commands', ['id' => 100]);
        $this->assertNotSoftDeleted('commands', ['id' => 103]);
        $this->assertSame(1, DB::table('commands')->whereNotNull('deleted_at')->count());

        // Un marchand ne touche qu'à ses propres colis.
        DB::table('commands')->where('id', 105)->update(['client_id' => 1]);
        $this->actingAs($this->client)->post('/colis/groupe', ['action' => 'corbeille', 'ids' => [105]])->assertForbidden();
        $this->assertNotSoftDeleted('commands', ['id' => 105]);
        $this->actingAs($this->admin)->post('/colis/groupe', ['action' => 'detruire', 'ids' => [105]])->assertSessionHasErrors('action');
    }

    public function test_actions_groupees_bons_et_reprise(): void
    {
        $ids = DB::table('bons_livraison')->pluck('id')->all();
        $this->actingAs($this->admin)->post('/bons/livraison/groupe', ['action' => 'corbeille', 'ids' => $ids])->assertSessionHas('success');
        $this->assertSame(2, DB::table('bons_livraison')->whereNotNull('deleted_at')->count());
        $this->assertGreaterThan(0, DB::table('bon_livraison_command')->count()); // lignes conservées
        $this->actingAs($this->admin)->get('/bons-livraison?corbeille=1')->assertInertia(fn (Assert $p) => $p->has('bons.data', 2)->where('stats.corbeille', 2));
        $this->actingAs($this->admin)->post('/bons/livraison/groupe', ['action' => 'restaurer', 'ids' => $ids])->assertSessionHas('success');
        $this->assertSame(0, DB::table('bons_livraison')->whereNotNull('deleted_at')->count());
        $this->actingAs($this->client)->post('/bons/livraison/groupe', ['action' => 'corbeille', 'ids' => $ids])->assertForbidden();

        $anomalies = DB::table('reprise_anomalies')->where('domaine', 'villes')->pluck('id')->all();
        $this->actingAs($this->admin)->post('/reprise/groupe', ['ids' => $anomalies, 'statut' => 'ignoree'])->assertSessionHasErrors('decision');
        $this->actingAs($this->admin)->post('/reprise/groupe', ['ids' => $anomalies, 'statut' => 'ignoree', 'decision' => 'Villes vérifiées'])->assertSessionHas('success');
        $this->assertSame(count($anomalies), DB::table('reprise_anomalies')->whereIn('id', $anomalies)->where('statut', 'ignoree')->where('decision', 'Villes vérifiées')->count());
    }

    public function test_page_groupes_etat_gestion_et_rattachement(): void
    {
        $this->actingAs($this->admin)->get('/etat-groupes?onglet=etats')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('EtatGroupes/Index')
            ->has('groupes', 8)
            ->where('stats.non_classes', 0)
            ->where('groupes.0.systeme', true));

        $this->actingAs($this->admin)->post('/etat-groupes', ['nom' => 'Litiges', 'description' => 'À arbitrer', 'couleur' => '#123456', 'sort_order' => 20, 'actif' => true, 'suivi_actif' => false, 'suivi_planifie' => false])->assertSessionHasNoErrors();
        $litiges = EtatGroupe::where('code', 'litiges')->firstOrFail();

        // Rattacher « Livré » au nouveau groupe : les colis livrés changent d'onglet.
        $livre = DB::table('statuts')->where('libelle', 'Livré')->value('id');
        $this->actingAs($this->admin)->post("/etat-groupes/{$litiges->id}/etats", ['etat_ids' => [$livre]])->assertSessionHas('success');
        $this->assertSame('litiges', DB::table('commands')->find(100)->state_group);
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p->has('groupes', 8)->where('onglets.litiges', 2));

        // Un groupe non vide ou système ne se supprime pas.
        $this->actingAs($this->admin)->delete("/etat-groupes/{$litiges->id}")->assertSessionHas('error');
        $systeme = EtatGroupe::where('code', 'livre')->firstOrFail();
        $this->actingAs($this->admin)->delete("/etat-groupes/{$systeme->id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->post("/etat-groupes/{$systeme->id}/etats", ['etat_ids' => [$livre]]);
        $this->actingAs($this->admin)->delete("/etat-groupes/{$litiges->id}")->assertSessionHas('success');

        // Un groupe masqué disparaît des cartes de la page Colis.
        $this->actingAs($this->admin)->put("/etat-groupes/{$systeme->id}", ['nom' => 'Livré', 'couleur' => '#10B981', 'sort_order' => 4, 'actif' => false, 'suivi_actif' => false, 'suivi_planifie' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p->has('groupes', 6));

        $this->actingAs($this->client)->get('/etat-groupes')->assertForbidden();
    }

    public function test_liste_triee_par_derniere_mise_a_jour_et_colonne_notes(): void
    {
        DB::table('commands')->where('id', 101)->update(['updated_at' => now()->addMinute()]);
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p
            ->where('colis.data.0.id', 101)
            ->where('colis.data.1.notes_count', fn ($n) => is_int($n))
            ->missing('colis.data.0.legacy_brut')
            ->missing('villes'));          // 1 350 villes : chargées en différé

        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p
            ->where('colis.data', fn ($lignes) => collect($lignes)->firstWhere('id', 100)['derniere_note'] === 'Rappeler'
                && collect($lignes)->firstWhere('id', 100)['notes_count'] === 2));
    }

    public function test_fil_des_notes_lecture_et_ajout(): void
    {
        $this->actingAs($this->admin)->getJson('/colis/100/notes')->assertOk()
            ->assertJsonCount(2, 'notes')
            ->assertJsonPath('notes.0.message', 'Client pas chez lui')
            ->assertJsonPath('notes.0.role', 'livreur');

        $this->actingAs($this->client)->postJson('/colis/100/notes', ['message' => 'Merci de livrer avant 18h'])->assertCreated();
        $this->actingAs($this->client)->postJson('/colis/100/notes', ['message' => ''])->assertUnprocessable();
        $this->actingAs($this->admin)->getJson('/colis/100/notes')->assertJsonCount(3, 'notes')
            ->assertJsonPath('notes.2.role', 'client')
            ->assertJsonPath('notes.2.auteur', 'Speedo');

        // Ajouter une note fait remonter le colis en tête (dernière mise à jour).
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p->where('colis.data.0.id', 100)->where('colis.data.0.derniere_note', 'Merci de livrer avant 18h'));

        // Hors périmètre : ni lecture ni écriture.
        DB::table('commands')->where('id', 104)->update(['client_id' => 1]);
        $this->actingAs($this->client)->getJson('/colis/104/notes')->assertForbidden();
        $this->actingAs($this->client)->postJson('/colis/104/notes', ['message' => 'x'])->assertForbidden();
    }

    public function test_perimetre_des_listes_colis_et_nouveaux_colis(): void
    {
        // #102 : « Ajouté » mais marqué ramassé dans l'ancienne base → dans aucune liste, et signalé.
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p
            ->where('colis.data', fn ($l) => ! collect($l)->contains('id', 102)));
        $this->actingAs($this->admin)->get('/colis/nouveaux')->assertInertia(fn (Assert $p) => $p
            ->where('colis.data', fn ($l) => ! collect($l)->contains('id', 102))->has('groupes', 0));
        $this->assertTrue(DB::table('reprise_anomalies')->where('code', 'colis_hors_listes')->where('valeur_legacy', 'ramasse_mais_nouveau')->exists());

        // Un colis créé dans la plateforme est un « nouveau colis »…
        DB::table('commands')->where('id', 102)->update(['is_ramassee' => false]);
        $this->actingAs($this->admin)->get('/colis/nouveaux')->assertInertia(fn (Assert $p) => $p
            ->has('colis.data', 1)->where('colis.data.0.id', 102)->where('onglets.tous', 1));
        // … et passe dans « Colis » quand il quitte le groupe « nouveau ».
        $this->actingAs($this->admin)->patch('/colis/102/state', ['state_group' => 'en_cours'])->assertSessionHasNoErrors();
        $this->assertTrue((bool) DB::table('commands')->find(102)->is_ramassee);
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p
            ->where('colis.data', fn ($l) => collect($l)->contains('id', 102)));
    }
}
