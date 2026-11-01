<?php

namespace Tests\Feature\Tarifs;

use App\Application\Tarifs\Backfill\RapprochementClientFees;
use App\Console\Commands\BackfillCommandsFromStockoutCommand;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\Tarifs\Clients\Services\ClientTarifQuery;
use App\Domain\Tarifs\Commun\Enums\TypeTarif;
use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Domain\Villes\Support\VilleRef;
use App\Models\ClientTarif;
use App\Models\Command;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Décisions validées du rapport #35 (réconciliation legacy) — base legacy SIMULÉE (SQLite mémoire).
 */
class ReconciliationDecisionsTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    private Ville $casaPetit;
    private Ville $casaGrand;
    private Ville $zagora;
    private User $archive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();

        // Homonymes actifs (comme 346 / 1373 en production) et ville archivée (comme Zagora 1326).
        $this->casaPetit = $this->casablanca;                                                   // plus petit id
        $this->casaGrand = Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $this->zoneCasa->id]); // plus grand id
        $this->zagora = Ville::create(['name_fr' => 'ZAGORA', 'zone_id' => $this->zoneRabat->id]);
        $this->zagora->delete();

        // Client archivé (comme 62 Alta).
        $this->archive = $this->compte('client', UserRole::CLIENT, ['nom_complet' => 'Alta', 'email' => 'alta@example.test']);
        $this->archive->delete();

        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy_test');
        Schema::connection('legacy_test')->create('users', function (Blueprint $t) {
            $t->integer('id');
            $t->string('email')->nullable();
            $t->string('fullname')->nullable();
        });
        Schema::connection('legacy_test')->create('clientfees', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('client');
            $t->string('city');
            $t->double('deliveredfees');
            $t->double('refusedfees');
            $t->double('returnedfees');
            $t->integer('trash');
        });
        $this->legacy()->table('users')->insert([
            ['id' => $this->clientA->id, 'email' => 'fatiha@example.test', 'fullname' => 'Fatiha Ahnini'],
            ['id' => $this->clientB->id, 'email' => 'pixa@example.test', 'fullname' => 'PixaMaroc'],
            ['id' => $this->archive->id, 'email' => 'alta@example.test', 'fullname' => 'Alta'],
        ]);
        app(VilleRef::class)->oublier();
    }

    private function legacy()
    {
        return DB::connection('legacy_test');
    }

    private function fee(int $id, int $client, string $city, float $l, float $r = 0, float $ret = 0, int $trash = 1): void
    {
        $this->legacy()->table('clientfees')->insert(['id' => $id, 'client' => $client, 'city' => $city, 'deliveredfees' => $l, 'refusedfees' => $r, 'returnedfees' => $ret, 'trash' => $trash]);
    }

    private function analyse(): array
    {
        return app(RapprochementClientFees::class)->analyserClientFees($this->legacy());
    }

    private function appliquer(): void
    {
        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true])->assertSuccessful();
    }

    /* ─────────── Décision 1 : même identifiant ville partout ─────────── */

    public function test_tarif_colis_importe_et_destination_utilisent_le_meme_identifiant_ville(): void
    {
        $this->fee(1, $this->clientA->id, 'Casablanca', 35);
        $this->appliquer();

        $tarif = ClientTarif::firstOrFail();
        $villeColis = BackfillCommandsFromStockoutCommand::villePourLibelle(app(VilleRef::class), 'Casablanca');

        $this->assertSame($this->casaPetit->id, $tarif->ville_id, 'Tarif : plus petit id actif.');
        $this->assertSame($this->casaPetit->id, $villeColis, 'Colis importé : même politique.');

        // Destination : un colis importé sur cette ville retrouve bien le tarif négocié.
        $colis = new Command(['client_id' => $this->clientA->id, 'destinataire_ville_id' => $villeColis, 'destinataire_ville' => 'Casablanca']);
        $r = app(FeeResolutionService::class)->pourColis($colis);
        $this->assertSame(TypeTarif::Client, $r->type);
        $this->assertSame('35.00', $r->livraison);
    }

    public function test_audit_des_colis_signale_les_divergences_sans_rien_ecrire(): void
    {
        $avant = Command::create([
            'code' => 'OIS-1', 'client_id' => $this->clientA->id, 'destinataire_nom' => 'X', 'destinataire_telephone' => '0600000000',
            'destinataire_ville' => 'Casablanca', 'destinataire_ville_id' => $this->casaGrand->id, // ancienne règle (plus grand id)
            'state' => 'Livré', 'state_group' => 'livre',
        ]);
        Command::create([
            'code' => 'OIS-2', 'client_id' => $this->clientA->id, 'destinataire_nom' => 'Y', 'destinataire_telephone' => '0600000001',
            'destinataire_ville' => 'Temara', 'destinataire_ville_id' => $this->temara->id,
            'state' => 'Livré', 'state_group' => 'livre',
        ]);

        $this->artisan('tarifs:audit-villes-colis')
            ->expectsOutputToContain('LECTURE SEULE')
            ->expectsTable(['État', 'Colis'], [['identique', 1], ['divergent', 1], ['vide_resoluble', 0], ['introuvable', 0], ['sans_libelle', 0]])
            ->assertSuccessful();

        $this->assertSame($this->casaGrand->id, $avant->fresh()->destinataire_ville_id, 'Aucun colis modifié.');
    }

    /* ─────────── Décision 2 : villes archivées ─────────── */

    public function test_tarif_d_une_ville_archivee_conserve_masque_puis_retrouve_a_la_restauration(): void
    {
        $this->fee(1, $this->clientA->id, 'Zagora', 40);
        $this->appliquer();

        $tarif = ClientTarif::firstOrFail();
        $this->assertSame($this->zagora->id, $tarif->ville_id);
        $this->assertNotNull($tarif->ville, 'Relation historique conservée (ville soft-deleted).');

        $query = app(ClientTarifQuery::class);
        $this->assertSame([], $query->grilleDuClient($this->clientA->id), 'Grille opérationnelle : ville archivée masquée.');

        $this->zagora->restore();
        $this->assertSame('40.00', $query->grilleDuClient($this->clientA->id)[0]['lignes'][0]['livraison'], 'Restaurée : la règle réapparaît.');
    }

    /* ─────────── Décision 3 : clients archivés ─────────── */

    public function test_tarifs_d_un_client_archive_conserves_mais_hors_liste_active(): void
    {
        $this->fee(1, $this->archive->id, 'Temara', 15);
        $this->fee(2, $this->clientA->id, 'Temara', 20);
        $this->appliquer();

        $tarif = ClientTarif::where('client_id', $this->archive->id)->firstOrFail();
        $this->assertNotNull($tarif->client, 'Relation historique conservée (client soft-deleted).');
        $this->assertTrue($tarif->client->trashed());

        $this->actingAs($this->admin)->get('/tarifs-clients')->assertInertia(fn (Assert $p) => $p
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->clientA->id)
            ->where('stats.tarifs', 1));
    }

    /* ─────────── Décision 5 : doublons et traçabilité ─────────── */

    public function test_doublons_identiques_fusionnes_avec_traçabilite_des_deux_identifiants(): void
    {
        $this->fee(64, $this->clientA->id, 'Temara', 45);
        $this->fee(66, $this->clientA->id, 'temara ', 45);
        $this->fee(70, $this->clientB->id, 'Temara', 30);
        $this->fee(71, $this->clientB->id, 'Temara', 35); // montants différents : jamais dédupliqués

        $s = $this->analyse()['synthese'];
        $this->assertSame(1, $s['neutralisees']);

        $this->appliquer();
        $this->assertSame([64, 66], ClientTarif::where('client_id', $this->clientA->id)->firstOrFail()->legacy_ids);
        $this->assertSame(0, ClientTarif::where('client_id', $this->clientB->id)->count(), 'Montants divergents : rien migré.');
    }

    /* ─────────── Décision 6 : frais historiques exacts ─────────── */

    public function test_frais_de_refus_et_retour_repris_exactement(): void
    {
        $this->fee(1, $this->clientA->id, 'Temara', 13, 10, 0);
        $this->fee(2, $this->clientA->id, 'Skhirate', 18, 0, 0);
        $this->appliquer();

        $this->assertSame('10.00', ClientTarif::pour($this->clientA->id, $this->temara->id)->first()->frais_refus);
        $this->assertSame('0.00', ClientTarif::pour($this->clientA->id, $this->skhirate->id)->first()->frais_refus);
    }

    /* ─────────── Corbeille legacy et synthèse de contrôle ─────────── */

    public function test_regle_en_corbeille_dans_le_legacy_reprise_en_corbeille(): void
    {
        $this->fee(1, $this->clientA->id, 'Temara', 15, 0, 0, trash: 0);
        $this->appliquer();

        $this->assertSame(0, ClientTarif::count());
        $this->assertSame(1, ClientTarif::onlyTrashed()->count());
    }

    public function test_synthese_de_controle_par_categorie(): void
    {
        $this->fee(1, $this->clientA->id, 'Temara', 15);          // simple
        $this->fee(2, $this->clientA->id, 'Casablanca', 35);      // homonymes
        $this->fee(3, $this->clientA->id, 'Zagora', 40);          // ville archivée
        $this->fee(4, $this->archive->id, 'Skhirate', 20);        // client archivé
        $this->fee(5, $this->clientA->id, 'Temara', 15);          // doublon identique

        $s = $this->analyse()['synthese'];

        $this->assertSame(5, $s['lignes_examinees']);
        $this->assertSame(4, $s['regles_uniques']);
        $this->assertSame(1, $s['neutralisees']);
        $this->assertSame(['simples' => 1, 'homonymes' => 1, 'villes_archivees' => 1, 'clients_archives' => 1], $s['categories']);
        $this->assertSame([2, 2, 1], [$s['clients_legacy'], $s['clients_identifies'], $s['clients_archives']]);
        $this->assertSame(0, $s['conflits']);
    }
}
