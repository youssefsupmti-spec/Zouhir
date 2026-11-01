<?php

namespace Tests\Feature\Tarifs;

use App\Application\Tarifs\Backfill\RapprochementLivreurFees;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\Villes\Support\VilleRef;
use App\Models\Command;
use App\Models\LivreurTarif;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** tarifs:backfill-livreur-fees sur une base legacy SIMULÉE (SQLite mémoire) : aucune base réelle. */
class BackfillLivreurFeesTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    private User $hamza;
    private User $archive;
    private Ville $casaGrand;
    private Ville $zagora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();

        $this->hamza = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Hamza temara', 'email' => 'hamza@example.test']);
        $this->archive = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Ancien', 'email' => 'ancien@example.test']);
        $this->archive->delete();
        $this->casaGrand = Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $this->zoneCasa->id]); // homonyme, id plus grand
        $this->zagora = Ville::create(['name_fr' => 'Zagora', 'zone_id' => $this->zoneRabat->id]);
        $this->zagora->delete();

        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy_test');
        Schema::connection('legacy_test')->create('users', function (Blueprint $t) {
            $t->integer('id');
            $t->string('email')->nullable();
            $t->string('fullname')->nullable();
        });
        Schema::connection('legacy_test')->create('shippingfees', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('dlm');
            $t->string('city');
            $t->double('deliveredfees');
            $t->double('refusedfees');
            $t->integer('trash');
        });
        $this->legacy()->table('users')->insert([
            ['id' => $this->hamza->id, 'email' => 'HAMZA@example.test', 'fullname' => 'Hamza temara'],
            ['id' => $this->archive->id, 'email' => 'ancien@example.test', 'fullname' => 'Ancien'],
            ['id' => $this->clientA->id, 'email' => 'fatiha@example.test', 'fullname' => 'Fatiha Ahnini'],
            ['id' => $this->clientB->id, 'email' => 'autre@example.test', 'fullname' => 'Autre'],
        ]);
        app(VilleRef::class)->oublier();
    }

    private function legacy()
    {
        return DB::connection('legacy_test');
    }

    private function fee(int $id, int $dlm, string $city, float $liv, float $refus = 0, int $trash = 1): void
    {
        $this->legacy()->table('shippingfees')->insert(['id' => $id, 'dlm' => $dlm, 'city' => $city, 'deliveredfees' => $liv, 'refusedfees' => $refus, 'trash' => $trash]);
    }

    private function scenario(): void
    {
        $h = $this->hamza->id;
        // PASS 1 — spécifiques
        $this->fee(1, $h, 'Temara', 11);
        $this->fee(2, $h, 'temara ', 11);                 // doublon identique
        $this->fee(3, $h, 'Casablanca', 12);              // homonymes → plus petit id
        $this->fee(4, $h, 'Skhirate', 10);
        $this->fee(5, $h, 'Skhirate', 12);                // doublon conflictuel
        $this->fee(6, $h, 'Zagora', 15);                  // ville archivée (seule correspondance)
        $this->fee(7, $this->archive->id, 'Temara', 9);   // livreur archivé : conservé
        $this->fee(8, $this->clientA->id, 'Temara', 9);   // compte non livreur
        $this->fee(9, $this->clientB->id, 'Temara', 9);   // identité en conflit
        $this->fee(10, 9999, 'Temara', 9);                // livreur introuvable
        $this->fee(11, $h, 'Atlantide', 9);               // ville introuvable
        $this->fee(12, $h, 'Kenitra', 7, 0, 0);           // archivée legacy → … introuvable ville
        // PASS 2 — standards (dlm = 0)
        $this->fee(20, 0, 'Temara', 10);
        $this->fee(21, 0, 'TEMARA', 10);                  // doublon identique
        $this->fee(22, 0, 'Skhirate', 10);
        $this->fee(23, 0, 'skhirate', 11);                // conflit de montants
        $this->fee(24, 0, 'Casablanca', 10, 0, 0);        // standard archivé : non repris
        $this->fee(25, $h, 'Casablanca', 13, 0, 0);       // spécifique archivée en doublon d'une active
    }

    public function test_simulation_classe_chaque_ligne_sans_rien_ecrire(): void
    {
        $this->scenario();

        $this->artisan('tarifs:backfill-livreur-fees', ['--connection' => 'legacy_test', '--details' => true])
            ->expectsOutputToContain('MODE SIMULATION')
            ->expectsOutputToContain('Total qui serait écrit : 4 commission(s) spécifique(s).')
            ->assertSuccessful();

        $this->assertSame(0, LivreurTarif::withTrashed()->count(), 'Aucune écriture.');

        $a = app(RapprochementLivreurFees::class)->analyser($this->legacy());
        $spec = collect($a['specifiques'])->pluck('statut', 'legacy_id')->all();
        $std = collect($a['standards'])->pluck('statut', 'legacy_id')->all();

        $this->assertSame('a_creer', $spec[1]);
        $this->assertSame('doublon_identique', $spec[2]);
        $this->assertSame('a_creer', $spec[3]);
        $this->assertSame(['doublon_conflictuel', 'doublon_conflictuel'], [$spec[4], $spec[5]]);
        $this->assertSame('a_creer', $spec[6]);
        $this->assertSame('a_creer', $spec[7], 'Livreur archivé : règle conservée.');
        $this->assertSame('pas_livreur', $spec[8]);
        $this->assertSame('identite_conflit', $spec[9]);
        $this->assertSame('livreur_introuvable', $spec[10]);
        $this->assertSame('ville_introuvable', $spec[11]);
        $this->assertSame('doublon_corbeille', $spec[25]);
        $this->assertSame(['non_repris_sans_bareme', 'doublon_identique', 'conflit_montants', 'conflit_montants', 'archive_legacy_non_repris'], [$std[20], $std[21], $std[22], $std[23], $std[24]]);

        $s = $a['synthese'];
        $this->assertSame([18, 15, 3], [$s['lignes'], $s['actives'], $s['archivees']]);
        $this->assertSame([11, 2, 4, 1], [$s['specifiques_actives'], $s['specifiques_archivees'], $s['standards_actives'], $s['standards_archivees']]);
        $this->assertSame([5, 2, 1], [$s['livreurs_legacy'], $s['livreurs_identifies'], $s['livreurs_archives']]);
        $this->assertSame(1, $s['standards_conflits_villes']);
    }

    public function test_apply_identifiants_villes_et_idempotence(): void
    {
        $this->scenario();

        $this->artisan('tarifs:backfill-livreur-fees', ['--connection' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $temara = LivreurTarif::pour($this->hamza->id, $this->temara->id)->firstOrFail();
        $this->assertSame([1, 2], $temara->legacy_ids, 'Traçabilité des deux identifiants legacy.');
        $this->assertSame($this->casablanca->id, LivreurTarif::where('legacy_ids', 'like', '%3%')->value('ville_id'), 'Même politique VilleRef que tarifs clients et colis.');
        $this->assertSame($this->zagora->id, LivreurTarif::pour($this->hamza->id, $this->zagora->id)->value('ville_id'));
        $this->assertSame(0, LivreurTarif::pour($this->hamza->id, $this->skhirate->id)->count(), 'Montants divergents : non migrés.');
        $this->assertSame(4, LivreurTarif::count());
        $this->assertSame(0, DB::table('livreur_standard_tarifs')->count(), 'dlm = 0 : jamais écrit (pas de barème standard).');

        $this->artisan('tarifs:backfill-livreur-fees', ['--connection' => 'legacy_test', '--apply' => true])
            ->expectsOutputToContain('Total qui serait écrit : 0 commission(s) spécifique(s).')
            ->assertSuccessful();
        $this->assertSame(4, LivreurTarif::withTrashed()->count());
    }

    public function test_regle_archivee_reprise_en_corbeille_et_existant_jamais_ecrase(): void
    {
        $this->fee(1, $this->hamza->id, 'Temara', 11, 0, 0);
        $this->fee(2, $this->hamza->id, 'Skhirate', 10);
        LivreurTarif::create(['livreur_id' => $this->hamza->id, 'ville_id' => $this->skhirate->id, 'commission_livraison' => '14', 'commission_refus' => '0']);

        $this->artisan('tarifs:backfill-livreur-fees', ['--connection' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $this->assertSame(1, LivreurTarif::onlyTrashed()->count());
        $this->assertSame('14.00', LivreurTarif::pour($this->hamza->id, $this->skhirate->id)->value('commission_livraison'), 'Conflit jamais écrasé.');
    }

    public function test_aucune_commande_modifiee(): void
    {
        $this->scenario();
        $c = Command::create(['code' => 'OUA-1', 'client_id' => $this->clientA->id, 'livreur_id' => $this->hamza->id, 'destinataire_nom' => 'X',
            'destinataire_telephone' => '06', 'destinataire_ville' => 'Temara', 'destinataire_ville_id' => $this->temara->id,
            'state' => 'Livré', 'state_group' => 'livre', 'frais_livraison_livreur' => 0]);

        $this->artisan('tarifs:backfill-livreur-fees', ['--connection' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $this->assertSame(['0.00', null], [$c->fresh()->frais_livraison_livreur, $c->fresh()->type_commission_livreur]);
    }

    public function test_apply_refuse_en_production_sans_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('tarifs:backfill-livreur-fees', ['--connection' => 'legacy_test', '--apply' => true])
            ->expectsOutputToContain('--apply refusé en production')
            ->assertFailed();
    }
}
