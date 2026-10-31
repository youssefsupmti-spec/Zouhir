<?php

namespace Tests\Feature\Tarifs;

use App\Models\ClientTarif;
use App\Models\StandardTarif;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Commande tarifs:backfill-client-fees sur une base legacy SIMULÉE (SQLite en mémoire,
 * connexion `legacy_test`) : aucune base réelle n'est contactée.
 */
class BackfillClientFeesTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();

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
            $t->double('deliveredfees')->nullable();
            $t->double('refusedfees')->nullable();
            $t->double('returnedfees')->nullable();
            $t->integer('trash');
        });
        Schema::connection('legacy_test')->create('gshippingfees', function (Blueprint $t) {
            $t->integer('id');
            $t->string('city');
            $t->double('deliveredfees');
            $t->double('refusedfees');
            $t->double('returnedfees');
            $t->integer('trash');
        });

        $this->legacy()->table('users')->insert([
            ['id' => $this->clientA->id, 'email' => 'FATIHA@example.test ', 'fullname' => 'Fatiha Ahnini'],
            ['id' => $this->clientB->id, 'email' => 'autre@example.test', 'fullname' => 'Quelqu\'un d\'autre'], // identité différente
            ['id' => 9999, 'email' => 'fantome@example.test', 'fullname' => 'Fantôme'],
        ]);

        Ville::create(['name_fr' => 'Tamesna', 'zone_id' => $this->zoneRabat->id]);
        Ville::create(['name_fr' => 'Bouznika', 'zone_id' => $this->zoneRabat->id]);
        Ville::create(['name_fr' => 'Bouznika', 'zone_id' => $this->zoneCasa->id]); // homonyme
    }

    private function legacy()
    {
        return DB::connection('legacy_test');
    }

    private function fee(int $id, int $client, string $city, $l, $r = 0, $ret = 0, int $trash = 1): void
    {
        $this->legacy()->table('clientfees')->insert(['id' => $id, 'client' => $client, 'city' => $city, 'deliveredfees' => $l, 'refusedfees' => $r, 'returnedfees' => $ret, 'trash' => $trash]);
    }

    private function scenario(): void
    {
        $a = $this->clientA->id;
        $this->fee(1, $a, 'Temara', 15);                 // a_creer (exacte)
        $this->fee(2, $a, 'Tamessna', 20);               // a_creer (alias)
        $this->fee(3, $a, 'Bouznika', 30);               // ville_ambigue
        $this->fee(4, $a, 'Atlantide', 30);              // ville_introuvable
        $this->fee(5, $this->clientB->id, 'Temara', 25); // identite_conflit
        $this->fee(6, 9999, 'Temara', 25);               // client_introuvable (absent de Zouhir)
        $this->fee(7, $a, 'Skhirate', 18);               // doublon identique (avec 8)
        $this->fee(8, $a, 'skhirate ', 18);
        $this->fee(9, $a, 'Casablanca', 35);             // conflit_valeur (Zouhir = 40)
        $this->fee(10, $a, 'Temara', 99, 0, 0, 0);       // archivee_legacy
        $this->fee(11, $this->admin->id, 'Temara', 10);  // absent du legacy users → client_introuvable
        $this->tarif($this->clientA, $this->casablanca, '40');
    }

    public function test_simulation_n_ecrit_rien_et_classe_chaque_ligne(): void
    {
        $this->scenario();

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--details' => true])
            ->expectsOutputToContain('MODE SIMULATION')
            ->expectsOutputToContain('Total qui serait écrit : 3 ligne(s).')
            ->assertSuccessful();

        $this->assertSame(1, ClientTarif::withTrashed()->count(), 'Aucune écriture en simulation.');

        $analyse = app(\App\Application\Tarifs\Backfill\RapprochementClientFees::class)->analyserClientFees($this->legacy());
        $statuts = collect($analyse['lignes'])->pluck('statut', 'legacy_id')->all();

        $this->assertSame('a_creer', $statuts[1]);
        $this->assertSame('a_creer', $statuts[2]);
        $this->assertSame('ville_ambigue', $statuts[3]);
        $this->assertSame('ville_introuvable', $statuts[4]);
        $this->assertSame('identite_conflit', $statuts[5]);
        $this->assertSame('client_introuvable', $statuts[6]);
        $this->assertSame('a_creer', $statuts[7]);
        $this->assertSame('doublon_identique', $statuts[8]);
        $this->assertSame('conflit_valeur', $statuts[9]);
        $this->assertSame('archivee_legacy', $statuts[10]);
        $this->assertSame('client_introuvable', $statuts[11]);
    }

    public function test_apply_ecrit_seulement_les_lignes_sures_et_est_idempotent(): void
    {
        $this->scenario();

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $this->assertSame(4, ClientTarif::count()); // 3 créées + 1 existante
        $this->assertSame('40.00', ClientTarif::pour($this->clientA->id, $this->casablanca->id)->first()->frais_livraison, 'Conflit jamais écrasé.');
        $this->assertSame('0.00', ClientTarif::pour($this->clientA->id, $this->temara->id)->first()->frais_refus, 'Refus repris tel quel du legacy.');

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true])
            ->expectsOutputToContain('Total qui serait écrit : 0 ligne(s).')
            ->assertSuccessful();
        $this->assertSame(4, ClientTarif::count(), 'Réexécution sans doublon.');
    }

    public function test_doublon_legacy_conflictuel_n_est_pas_migre(): void
    {
        $this->fee(1, $this->clientA->id, 'Temara', 15);
        $this->fee(2, $this->clientA->id, 'Temara', 18);

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $this->assertSame(0, ClientTarif::count());
    }

    public function test_restauration_controlee_uniquement_avec_option(): void
    {
        $this->fee(1, $this->clientA->id, 'Temara', 15);
        $this->tarif($this->clientA, $this->temara, '12')->delete();

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true])->assertSuccessful();
        $this->assertSame(0, ClientTarif::count());

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true, '--restaurer' => true])->assertSuccessful();
        $this->assertSame(1, ClientTarif::count());
        $this->assertSame('15.00', ClientTarif::firstOrFail()->frais_livraison);
        $this->assertSame(1, ClientTarif::withTrashed()->count());
    }

    public function test_compte_non_client_refuse(): void
    {
        $livreur = $this->compte('livreur', \App\Domain\Auth\Enums\UserRole::LIVREUR, ['email' => 'dlm@example.test', 'nom_complet' => 'DLM']);
        $this->legacy()->table('users')->insert(['id' => $livreur->id, 'email' => 'dlm@example.test', 'fullname' => 'DLM']);
        $this->fee(1, $livreur->id, 'Temara', 15);

        $analyse = app(\App\Application\Tarifs\Backfill\RapprochementClientFees::class)->analyserClientFees($this->legacy());

        $this->assertSame('pas_client', $analyse['lignes'][0]['statut']);
    }

    public function test_colonnes_refus_retour_absentes_aucune_valeur_inventee(): void
    {
        Schema::connection('legacy_test')->drop('clientfees');
        Schema::connection('legacy_test')->create('clientfees', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('client');
            $t->string('city');
            $t->double('deliveredfees');
            $t->integer('trash');
        });
        $this->legacy()->table('clientfees')->insert(['id' => 1, 'client' => $this->clientA->id, 'city' => 'Temara', 'deliveredfees' => 15, 'trash' => 1]);

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true])
            ->expectsOutputToContain('refusedfees ABSENTE')
            ->assertSuccessful();

        $this->assertSame(0, ClientTarif::count());
    }

    public function test_montant_illisible_non_migrable(): void
    {
        $this->fee(1, $this->clientA->id, 'Temara', null);
        $this->fee(2, $this->clientA->id, 'Skhirate', -5);

        $analyse = app(\App\Application\Tarifs\Backfill\RapprochementClientFees::class)->analyserClientFees($this->legacy());

        $this->assertSame(['montant_invalide', 'montant_invalide'], array_column($analyse['lignes'], 'statut'));
    }

    public function test_standards_legacy_optionnels(): void
    {
        $this->legacy()->table('gshippingfees')->insert([
            ['id' => 1, 'city' => 'Temara', 'deliveredfees' => 15, 'refusedfees' => 0, 'returnedfees' => 0, 'trash' => 1],
            ['id' => 2, 'city' => 'Ain el aouda', 'deliveredfees' => 20, 'refusedfees' => 0, 'returnedfees' => 0, 'trash' => 1],
        ]);

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--standard' => true, '--apply' => true])->assertSuccessful();

        $this->assertSame(1, StandardTarif::count(), 'Ville introuvable non migrée.');
        $this->assertSame('15.00', StandardTarif::firstOrFail()->frais_livraison);
    }

    public function test_apply_refuse_en_production_sans_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'legacy_test', '--apply' => true])
            ->expectsOutputToContain('--apply refusé en production')
            ->assertFailed();
    }

    public function test_connexion_absente_echoue_proprement(): void
    {
        $this->artisan('tarifs:backfill-client-fees', ['--connection' => 'inexistante'])->assertFailed();
        $this->assertInstanceOf(User::class, $this->clientA);
    }
}
