<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Auth\Enums\Permission as P;
use App\Models\ClientTarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Endpoints, Policy, isolation et contrat Inertia de /tarifs-clients. */
class ClientTarifHttpTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
    }

    private function negociation(array $extra = []): array
    {
        return $extra + [
            'client_id' => $this->clientA->id,
            'mode' => 'villes',
            'ville_ids' => [$this->temara->id],
            'livraison' => '15',
            'refus' => '0',
            'retour' => '0',
            'existants' => 'ignorer',
            'restaurer' => true,
        ];
    }

    public function test_page_kpi_dynamiques_et_filtres(): void
    {
        $this->standard($this->temara, '20');
        $this->tarif($this->clientA, $this->temara, '15');
        $this->tarif($this->clientA, $this->skhirate, '0');
        $this->tarif($this->clientB, $this->casablanca, '35');
        $this->tarif($this->clientB, $this->temara, '25')->delete();

        $this->actingAs($this->admin)->get('/tarifs-clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TarifsClients/Index')
                ->where('stats.clients', 2)
                ->where('stats.tarifs', 3)
                ->where('stats.villes', 3)
                ->where('stats.livraison_min', '0.00')
                ->where('stats.livraison_max', '35.00')
                ->where('stats.livraison_moyenne', '16.67')
                ->where('stats.gratuits', 1)
                ->has('clients.data', 2)
                ->where('can.create', true)
            );

        $this->actingAs($this->admin)->get('/tarifs-clients?search=pixa')
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.nom', 'PixaMaroc'));

        $this->actingAs($this->admin)->get('/tarifs-clients?ville=skhir')
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.id', $this->clientA->id));

        $this->actingAs($this->admin)->get('/tarifs-clients?zone_id='.$this->zoneCasa->id)
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.id', $this->clientB->id)->where('filters.zone_id', (string) $this->zoneCasa->id));
    }

    public function test_requetes_de_la_page_bornees_quel_que_soit_le_volume(): void
    {
        foreach ([$this->temara, $this->skhirate, $this->casablanca] as $v) {
            $this->tarif($this->clientA, $v, '15');
            $this->tarif($this->clientB, $v, '20');
        }

        DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/tarifs-clients')->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(25, $n, "Trop de requêtes pour la page : $n");
    }

    public function test_grille_groupee_par_zone_avec_standard_en_regard(): void
    {
        $this->standard($this->temara, '20');
        $this->tarif($this->clientA, $this->temara, '25');
        $this->tarif($this->clientA, $this->skhirate, '0');
        $this->tarif($this->clientA, $this->casablanca, '30');

        $json = $this->actingAs($this->admin)->getJson("/tarifs-clients/{$this->clientA->id}/grille")->assertOk()->json();

        $this->assertCount(2, $json['zones']);
        $rabat = collect($json['zones'])->firstWhere('zone_id', $this->zoneRabat->id);
        $skh = collect($rabat['lignes'])->firstWhere('ville_nom', 'Skhirate');
        $tem = collect($rabat['lignes'])->firstWhere('ville_nom', 'Temara');
        $this->assertSame('superieur', $tem['comparaison']);
        $this->assertSame('20.00', $tem['standard']);
        $this->assertTrue($skh['gratuit']);
        $this->assertSame('indisponible', $skh['comparaison']);
    }

    public function test_creation_modification_suppression_par_http(): void
    {
        $this->actingAs($this->admin)->post('/tarifs-clients', $this->negociation())->assertSessionHasNoErrors()->assertRedirect();
        $t = ClientTarif::firstOrFail();

        $this->actingAs($this->admin)->put('/tarifs-clients', [
            'client_id' => $this->clientA->id,
            'lignes' => [['id' => $t->id, 'livraison' => '12.50', 'refus' => '1', 'retour' => '2']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('12.50', $t->fresh()->frais_livraison);

        $this->actingAs($this->admin)->delete('/tarifs-clients', ['client_id' => $this->clientA->id, 'ids' => [$t->id]])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($t);
    }

    public function test_validation_serveur_des_montants(): void
    {
        $this->actingAs($this->admin)->post('/tarifs-clients', $this->negociation(['livraison' => '']))->assertSessionHasErrors('livraison');
        $this->actingAs($this->admin)->post('/tarifs-clients', $this->negociation(['refus' => '-3']))->assertSessionHasErrors('refus');
        $this->actingAs($this->admin)->post('/tarifs-clients', $this->negociation(['retour' => '1.999']))->assertSessionHasErrors('retour');
        $this->actingAs($this->admin)->post('/tarifs-clients', $this->negociation(['ville_ids' => [999999]]))->assertSessionHasErrors('ville_ids.0');
        $this->actingAs($this->admin)->post('/tarifs-clients', $this->negociation(['client_id' => $this->admin->id]))->assertSessionHasErrors('client_id');
        $this->assertSame(0, ClientTarif::withTrashed()->count());

        $this->actingAs($this->admin)->post('/tarifs-clients', $this->negociation(['livraison' => '0', 'refus' => '0', 'retour' => '0']))->assertSessionHasNoErrors();
        $this->assertSame('0.00', ClientTarif::firstOrFail()->frais_livraison);
    }

    public function test_apercu_json_sans_ecriture(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');

        $this->actingAs($this->admin)->postJson('/tarifs-clients/apercu', ['client_id' => $this->clientA->id, 'mode' => 'zone', 'zone_id' => $this->zoneRabat->id])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('compte.actif', 1)
            ->assertJsonPath('compte.nouveau', 1);

        $this->assertSame(1, ClientTarif::count());
    }

    /* ─────────────── Sécurité ─────────────── */

    public function test_consultation_seule(): void
    {
        $lecteur = $this->delegue([P::TARIFS_CLIENT_VOIR->value]);
        $t = $this->tarif($this->clientA, $this->temara, '15');

        $this->actingAs($lecteur)->get('/tarifs-clients')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.create', false)->where('can.update', false)->where('can.delete', false));
        $this->actingAs($lecteur)->getJson("/tarifs-clients/{$this->clientA->id}/grille")->assertOk();

        $this->actingAs($lecteur)->post('/tarifs-clients', $this->negociation(['ville_ids' => [$this->skhirate->id]]))->assertForbidden();
        $this->actingAs($lecteur)->put('/tarifs-clients', ['client_id' => $this->clientA->id, 'lignes' => [['id' => $t->id, 'livraison' => 1, 'refus' => 0, 'retour' => 0]]])->assertForbidden();
        $this->actingAs($lecteur)->delete('/tarifs-clients', ['client_id' => $this->clientA->id, 'ids' => [$t->id]])->assertForbidden();
        $this->actingAs($lecteur)->getJson('/tarifs-clients/referentiel')->assertForbidden();
        $this->assertSame('15.00', $t->fresh()->frais_livraison);
    }

    public function test_sans_permission_aucun_acces(): void
    {
        $sans = $this->delegue([P::TRACK_COLIS->value]);

        $this->actingAs($sans)->get('/tarifs-clients')->assertForbidden();
        $this->actingAs($sans)->getJson("/tarifs-clients/{$this->clientA->id}/grille")->assertForbidden();
        $this->actingAs($sans)->postJson('/tarifs-clients/apercu', ['client_id' => $this->clientA->id, 'mode' => 'villes', 'ville_ids' => [$this->temara->id]])->assertForbidden();
    }

    public function test_creation_sans_modification_ni_suppression(): void
    {
        $createur = $this->delegue([P::TARIFS_CLIENT_VOIR->value, P::TARIFS_CLIENT_CREER->value]);
        $t = $this->tarif($this->clientA, $this->skhirate, '15');

        $this->actingAs($createur)->post('/tarifs-clients', $this->negociation())->assertSessionHasNoErrors();
        $this->assertSame(2, ClientTarif::count());

        // « Mettre à jour les existants » via la création reste possible : c'est une décision explicite
        // de négociation ; la modification/suppression de lignes reste refusée.
        $this->actingAs($createur)->put('/tarifs-clients', ['client_id' => $this->clientA->id, 'lignes' => [['id' => $t->id, 'livraison' => 1, 'refus' => 0, 'retour' => 0]]])->assertForbidden();
        $this->actingAs($createur)->delete('/tarifs-clients', ['client_id' => $this->clientA->id, 'ids' => [$t->id]])->assertForbidden();
    }

    public function test_client_marchand_n_accede_ni_a_sa_grille_ni_a_celle_des_autres(): void
    {
        $this->tarif($this->clientB, $this->temara, '25');

        $this->actingAs($this->clientA)->get('/tarifs-clients')->assertForbidden();
        $this->actingAs($this->clientA)->getJson("/tarifs-clients/{$this->clientB->id}/grille")->assertForbidden();
        $this->actingAs($this->clientA)->post('/tarifs-clients', $this->negociation(['client_id' => $this->clientA->id]))->assertForbidden();
    }

    public function test_client_avec_permission_deleguee_par_erreur_reste_isole(): void
    {
        $this->clientA->givePermissionTo(P::TARIFS_CLIENT_VOIR->value);
        $this->tarif($this->clientA, $this->temara, '15');
        $this->tarif($this->clientB, $this->temara, '25');

        $this->actingAs($this->clientA)->getJson("/tarifs-clients/{$this->clientB->id}/grille")->assertForbidden();
        $this->actingAs($this->clientA)->getJson("/tarifs-clients/{$this->clientA->id}/grille")->assertOk();
        $this->actingAs($this->clientA)->get('/tarifs-clients?client_id='.$this->clientB->id)
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.id', $this->clientA->id));
    }

    public function test_invite_redirige_vers_login(): void
    {
        $this->get('/tarifs-clients')->assertRedirect('/login');
        $this->post('/tarifs-clients', [])->assertRedirect('/login');
    }

    public function test_grille_d_un_compte_non_client_introuvable(): void
    {
        $this->actingAs($this->admin)->getJson("/tarifs-clients/{$this->admin->id}/grille")->assertNotFound();
    }

    public function test_capacite_partagee_pour_la_sidebar(): void
    {
        $this->actingAs($this->admin)->get('/tarifs-clients')
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.tarifsClients', true));
        $this->actingAs($this->clientA)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.tarifsClients', false));
    }
}
