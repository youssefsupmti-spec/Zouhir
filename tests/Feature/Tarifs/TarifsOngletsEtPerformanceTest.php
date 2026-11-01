<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Auth\Enums\UserRole;
use App\Models\ClientTarif;
use App\Models\LivreurTarif;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Onglets (compteurs serveur rafraîchis après chaque action) et performance des trois pages
 * Tarifs : le nombre de requêtes SQL doit rester CONSTANT quand le volume grandit (pas de N+1).
 */
class TarifsOngletsEtPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
    }

    /* ─────────── Onglets et compteurs ─────────── */

    public function test_corbeille_des_tarifs_clients_et_compteurs_rafraichis(): void
    {
        $a = $this->tarif($this->clientA, $this->temara, '15');
        $b = $this->tarif($this->clientB, $this->skhirate, '18');
        $this->tarif($this->clientA, $this->casablanca, '30');

        $this->actingAs($this->admin)->get('/tarifs-clients')
            ->assertInertia(fn (Assert $p) => $p->where('stats.tarifs', 3)->where('stats.corbeille', 0)->where('corbeille', null)->has('clients.data', 2));

        // Mise à la corbeille : les deux compteurs bougent au rechargement suivant.
        $this->actingAs($this->admin)->delete('/tarifs-clients', ['client_id' => $this->clientA->id, 'ids' => [$a->id]])->assertSessionHasNoErrors();
        $b->delete();
        $this->actingAs($this->admin)->get('/tarifs-clients?onglet=corbeille')
            ->assertInertia(fn (Assert $p) => $p
                ->where('filters.onglet', 'corbeille')
                ->where('stats.tarifs', 1)
                ->where('stats.corbeille', 2)
                ->where('clients', null)
                ->has('corbeille', 2)
                ->where('corbeille.0.titulaire', 'PixaMaroc')
                ->where('corbeille.1.titulaire', 'Fatiha Ahnini')
                ->where('corbeille.1.montants', ['15.00', '0.00', '0.00']));

        $this->actingAs($this->admin)->post('/tarifs-clients/corbeille/restaurer', ['ids' => [$a->id]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete('/tarifs-clients/corbeille', ['ids' => [$b->id]])->assertSessionHasNoErrors();

        $this->assertNull($a->fresh()->deleted_at);
        $this->assertNull(ClientTarif::withTrashed()->find($b->id), 'Supprimé définitivement.');
        $this->actingAs($this->admin)->get('/tarifs-clients')
            ->assertInertia(fn (Assert $p) => $p->where('stats.tarifs', 2)->where('stats.corbeille', 0));
    }

    public function test_corbeille_clients_refuse_un_tarif_actif_et_un_compte_non_autorise(): void
    {
        $actif = $this->tarif($this->clientA, $this->temara, '15');

        $this->actingAs($this->admin)->delete('/tarifs-clients/corbeille', ['ids' => [$actif->id]])->assertSessionHasErrors('ids');
        $this->assertNotNull($actif->fresh());

        $this->actingAs($this->clientA)->post('/tarifs-clients/corbeille/restaurer', ['ids' => [$actif->id]])->assertForbidden();
    }

    public function test_compteurs_des_onglets_tarifs_de_livraison(): void
    {
        $this->standard($this->temara, '15');

        $this->actingAs($this->admin)->get('/tarifs-standard')->assertInertia(fn (Assert $p) => $p
            ->where('stats.villes', 3)->where('stats.tarifees', 1)->where('stats.sans_tarif', 2));

        $this->actingAs($this->admin)->post('/tarifs-standard', ['ville_ids' => [$this->skhirate->id], 'livraison' => '20', 'refus' => '0', 'retour' => '0', 'existants' => 'ignorer']);

        $this->actingAs($this->admin)->get('/tarifs-standard?statut=sans')->assertInertia(fn (Assert $p) => $p
            ->where('filters.statut', 'sans')->where('stats.tarifees', 2)->where('stats.sans_tarif', 1)->has('villes.data', 1));
    }

    /* ─────────── Performance : pas de N+1 ─────────── */

    /** Nombre de requêtes d'une requête HTTP, mesurée APRÈS un appel de préchauffe (caches de permissions et de rôles). */
    private function requetes(string $uri, bool $json = false): int
    {
        $this->actingAs($this->admin);
        $json ? $this->getJson($uri)->assertOk() : $this->get($uri)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $json ? $this->getJson($uri)->assertOk() : $this->get($uri)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    /** Ajoute $n clients, livreurs et villes portant des tarifs, commissions et standards. */
    private function volume(int $n, int $depart): void
    {
        for ($i = 0; $i < $n; $i++) {
            $ville = Ville::create(['name_fr' => 'Ville '.($depart + $i), 'zone_id' => $i % 2 ? $this->zoneRabat->id : $this->zoneCasa->id]);
            $client = $this->compte('client', UserRole::CLIENT, ['nom_complet' => 'Client '.($depart + $i)]);
            $livreur = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Livreur '.($depart + $i)]);
            $this->tarif($client, $ville, '20');
            $this->tarif($client, $this->temara, '15')->delete();
            $this->standard($ville, '25');
            LivreurTarif::create(['livreur_id' => $livreur->id, 'ville_id' => $ville->id, 'commission_livraison' => '10', 'commission_refus' => '0']);
            LivreurTarif::create(['livreur_id' => $livreur->id, 'ville_id' => $this->temara->id, 'commission_livraison' => '11', 'commission_refus' => '0']);
        }
    }

    public function test_nombre_de_requetes_constant_quand_le_volume_grandit(): void
    {
        $pages = [
            ['/tarifs-standard', false],
            ['/tarifs-standard?statut=sans', false],
            ['/tarifs-clients', false],
            ['/tarifs-clients?onglet=corbeille', false],
            ['/tarifs-livreurs', false],
            ['/tarifs-livreurs?statut=corbeille', false],
            ['/tarifs-livreurs/referentiel', true],
            ['/tarifs-standard/referentiel', true],
        ];

        $this->volume(2, 0);
        $petit = array_map(fn ($p) => $this->requetes($p[0], $p[1]), $pages);

        $this->volume(12, 100);
        $grand = array_map(fn ($p) => $this->requetes($p[0], $p[1]), $pages);

        foreach ($pages as $i => [$uri]) {
            $this->assertSame($petit[$i], $grand[$i], "N+1 sur $uri : {$petit[$i]} requêtes avec peu de données, {$grand[$i]} avec 6× plus.");
        }
    }

    public function test_grilles_json_en_nombre_de_requetes_constant(): void
    {
        $client = $this->compte('client', UserRole::CLIENT, ['nom_complet' => 'Gros client']);
        $livreur = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Gros livreur']);
        $this->tarif($client, $this->temara, '15');
        LivreurTarif::create(['livreur_id' => $livreur->id, 'ville_id' => $this->temara->id, 'commission_livraison' => '10', 'commission_refus' => '0']);

        $petitClient = $this->requetes('/tarifs-clients/'.$client->id.'/grille', true);
        $petitLivreur = $this->requetes('/tarifs-livreurs/'.$livreur->id.'/grille', true);

        for ($i = 0; $i < 15; $i++) {
            $ville = Ville::create(['name_fr' => 'V'.$i, 'zone_id' => $i % 2 ? $this->zoneRabat->id : $this->zoneCasa->id]);
            $this->tarif($client, $ville, '20');
            LivreurTarif::create(['livreur_id' => $livreur->id, 'ville_id' => $ville->id, 'commission_livraison' => '10', 'commission_refus' => '0']);
        }

        $this->assertSame($petitClient, $this->requetes('/tarifs-clients/'.$client->id.'/grille', true), 'Grille client : N+1.');
        $this->assertSame($petitLivreur, $this->requetes('/tarifs-livreurs/'.$livreur->id.'/grille', true), 'Grille livreur : N+1.');
    }

    public function test_relations_eloquent_et_eager_loading(): void
    {
        $livreur = $this->compte('livreur', UserRole::LIVREUR);
        LivreurTarif::create(['livreur_id' => $livreur->id, 'ville_id' => $this->temara->id, 'commission_livraison' => '10', 'commission_refus' => '0']);
        LivreurTarif::create(['livreur_id' => $livreur->id, 'ville_id' => $this->skhirate->id, 'commission_livraison' => '12', 'commission_refus' => '0']);

        $this->assertSame(2, $livreur->livreurTarifs()->count());
        $this->assertSame(1, $this->temara->livreurTarifs()->count());

        // Chargement anticipé : 3 requêtes (livreurs, commissions, villes) quel que soit le nombre de lignes.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $charges = User::whereKey($livreur->id)->with('livreurTarifs.ville')->get();
        $charges->each(fn (User $u) => $u->livreurTarifs->each(fn ($t) => $t->ville->name_fr));
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
