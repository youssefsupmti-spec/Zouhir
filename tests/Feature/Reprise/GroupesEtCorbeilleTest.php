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
            ->has('groupes', 8)
            ->where('onglets.tous', 5)       // 6 colis dont 1 en corbeille (trash = 0)
            ->where('onglets.corbeille', 1)
            ->where('onglets.livre', 2)
            ->where('filters.onglet', 'tous')
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
            ->has('colis.data', 5));

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
        $this->actingAs($this->admin)->get('/bons?type=livraison&corbeille=1')->assertInertia(fn (Assert $p) => $p->has('bons.data', 2)->where('stats.corbeille', 2));
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
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p->has('groupes', 9)->where('onglets.litiges', 2));

        // Un groupe non vide ou système ne se supprime pas.
        $this->actingAs($this->admin)->delete("/etat-groupes/{$litiges->id}")->assertSessionHas('error');
        $systeme = EtatGroupe::where('code', 'livre')->firstOrFail();
        $this->actingAs($this->admin)->delete("/etat-groupes/{$systeme->id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->post("/etat-groupes/{$systeme->id}/etats", ['etat_ids' => [$livre]]);
        $this->actingAs($this->admin)->delete("/etat-groupes/{$litiges->id}")->assertSessionHas('success');

        // Un groupe masqué disparaît des cartes de la page Colis.
        $this->actingAs($this->admin)->put("/etat-groupes/{$systeme->id}", ['nom' => 'Livré', 'couleur' => '#10B981', 'sort_order' => 4, 'actif' => false, 'suivi_actif' => false, 'suivi_planifie' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn (Assert $p) => $p->has('groupes', 7));

        $this->actingAs($this->client)->get('/etat-groupes')->assertForbidden();
    }
}
