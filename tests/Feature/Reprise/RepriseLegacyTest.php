<?php

namespace Tests\Feature\Reprise;

use App\Domain\Reprise\NoteLegacyParser;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reprise legacy sur une copie miniature de l'ancienne base (SQLite en mémoire), avec les cas réels
 * relevés en production : codes en double, colis sans expéditeur, ville vide / inconnue / variante,
 * « 0 » en tête du CSV des bons, colis disparu, historique lié par code, notes aux trois formats.
 */
class RepriseLegacyTest extends TestCase
{
    use RefreshDatabase;

    private const T0 = 1762000000; // 01/11/2025

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['reprise.connexion' => 'legacy_test', 'reprise.comptes_partenaires' => [76]]);
        $this->creerLegacy();

        // Référentiel du nouveau projet (comptes repris avec les mêmes ids).
        $zone = Zone::create(['name' => 'Zone Témara', 'code' => 'TEM']);
        Ville::create(['name_fr' => 'Temara', 'zone_id' => $zone->id, 'code_zone' => 'TEM']);
        Ville::create(['name_fr' => 'Ain attig', 'zone_id' => $zone->id, 'code_zone' => 'TEM']);
        Ville::create(['name_fr' => 'Casablanca']); // sans zone
        foreach ([[1, 'Admin', 'admin'], [45, 'Speedo', 'client'], [53, 'Mbarek livreur', 'livreur'], [76, 'Abdelkrim livreur', 'livreur'], [42, 'Agence temara', 'livreur']] as [$id, $nom, $role]) {
            User::factory()->create(['id' => $id, 'nom_complet' => $nom, 'role' => $role, 'email' => "u{$id}@example.test"]);
        }
        DB::table('businesses')->insert(['user_id' => 45, 'name' => 'Anouar', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function creerLegacy(): void
    {
        $s = Schema::connection('legacy_test');
        $s->create('trackingstates', function (Blueprint $t) {
            $t->integer('id');
            $t->string('state');
            $t->string('color')->nullable();
            $t->string('agent')->nullable();
            $t->string('kpi')->nullable();
            $t->integer('trash');
        });
        $s->create('users', function (Blueprint $t) {
            $t->integer('id');
            $t->string('fullname');
            $t->string('type');
        });
        $s->create('commands', function (Blueprint $t) {
            $t->integer('id');
            foreach (['code', 'product', 'qty', 'clientname', 'clientphone', 'fullname', 'phone', 'address', 'city', 'state', 'datereported', 'collected', 'invoiced', 'invoiceddlm', 'treated', 'dateadd', 'dateupdate'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['dlm', 'subdlm', 'client', 'package', 'archived', 'echange', 'openpackage', 'trash'] as $c) {
                $t->integer($c)->default(0);
            }
            $t->float('price')->default(0);
            $t->float('extrafees')->default(0);
            $t->text('note')->nullable();
        });
        $s->create('commandshistory', function (Blueprint $t) {
            $t->integer('id');
            $t->string('command');
            $t->string('state');
            $t->string('agent');
            $t->string('dateadd');
        });
        $s->create('bls', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('dlm');
            $t->integer('subdlm')->default(0);
            $t->integer('client');
            $t->string('code');
            $t->text('cmds');
            $t->string('done');
            $t->string('dateadd');
            $t->integer('trash');
        });

        $L = DB::connection('legacy_test');
        $L->table('trackingstates')->insert([
            ['id' => 1, 'state' => 'Livré', 'color' => '#008000', 'agent' => 'Modérateur,Livreur', 'kpi' => 'Livrées', 'trash' => 1],
            ['id' => 2, 'state' => 'Ramassé', 'color' => '#FFAA00', 'agent' => 'Modérateur', 'kpi' => 'En cours', 'trash' => 1],
            ['id' => 16, 'state' => 'Manque de stock', 'color' => '#CC0000', 'agent' => null, 'kpi' => 'Echouées', 'trash' => 0],
            ['id' => 17, 'state' => 'Ajouté', 'color' => '#e5dd80', 'agent' => 'Modérateur', 'kpi' => null, 'trash' => 1],
            ['id' => 18, 'state' => 'Manque de stock', 'color' => '#000000', 'agent' => 'Modérateur', 'kpi' => 'Echouées', 'trash' => 1],
            ['id' => 28, 'state' => 'Retour client reçu', 'color' => '#CC0000', 'agent' => null, 'kpi' => null, 'trash' => 1],
            ['id' => 31, 'state' => 'Bizarre statut', 'color' => null, 'agent' => null, 'kpi' => null, 'trash' => 1],
        ]);
        $L->table('users')->insert([
            ['id' => 1, 'fullname' => 'Admin', 'type' => 'moderator'], ['id' => 45, 'fullname' => 'Speedo', 'type' => 'client'],
            ['id' => 53, 'fullname' => 'Mbarek livreur', 'type' => 'dlm'], ['id' => 76, 'fullname' => 'Abdelkrim livreur', 'type' => 'dlm'],
            ['id' => 42, 'fullname' => 'Agence temara', 'type' => 'dlm'],
        ]);

        $colis = fn (int $id, array $v) => $v + [
            'id' => $id, 'code' => "OIS-{$id}", 'product' => 'Produit', 'qty' => '1', 'fullname' => 'Dest', 'phone' => '0600000000',
            'address' => 'Adresse', 'city' => 'Temara', 'state' => 'Livré', 'client' => 45, 'dlm' => 53, 'price' => 200,
            'collected' => 'on', 'invoiced' => 'off', 'invoiceddlm' => '', 'treated' => 'off', 'dateadd' => (string) self::T0,
            'dateupdate' => (string) (self::T0 + 7200), 'trash' => 1, 'note' => '', 'datereported' => '',
        ];
        foreach ([
            $colis(100, ['note' => '<b>Livreur: </b>Client pas chez lui<br /><b>Modérateur: </b>Rappeler<br />']),
            $colis(101, ['code' => 'CODE1', 'city' => '', 'qty' => '', 'state' => 'pas de réponse 3 fois']),
            $colis(102, ['code' => 'CODE1', 'city' => 'Ville Fantome', 'client' => 0, 'dlm' => 0, 'state' => 'Ajouté', 'qty' => '(L - XL) taille']),
            $colis(103, ['city' => 'ain atig', 'state' => 'Retour client reçu', 'trash' => 0, 'price' => 672499672, 'openpackage' => 260,
                'note' => '&lt;p&gt;&lt;strong&gt;Hicham Rabat: &lt;/strong&gt;Tri9 3in 3awda&lt;ins&gt;01/04/26 10:50&lt;/ins&gt;&lt;/p&gt;<b>Livreur: </b>Livré a ain aouda<br />']),
            $colis(104, ['city' => 'Casablanca', 'note' => 'client intéressé', 'datereported' => '-55766448000']),
        ] as $ligne) {
            $L->table('commands')->insert($ligne);
        }
        $L->table('commandshistory')->insert([
            ['id' => 1, 'command' => 'OIS-100', 'state' => 'Ajouté', 'agent' => 'Speedo', 'dateadd' => (string) self::T0],
            ['id' => 2, 'command' => 'OIS-100', 'state' => 'Ramassé', 'agent' => 'Mbarek livreur', 'dateadd' => (string) (self::T0 + 600)],
            ['id' => 4, 'command' => 'OIS-100', 'state' => 'Livré', 'agent' => 'Mbarek livreur', 'dateadd' => (string) (self::T0 + 3600)],
            ['id' => 3, 'command' => 'OIS-100', 'state' => 'Livré', 'agent' => 'Arbi region', 'dateadd' => (string) (self::T0 + 1800)],
            ['id' => 5, 'command' => 'CODE1', 'state' => 'Ajouté', 'agent' => 'Admin', 'dateadd' => (string) self::T0],
            ['id' => 6, 'command' => 'OIS-999', 'state' => 'Livré', 'agent' => 'Admin', 'dateadd' => (string) self::T0],
            ['id' => 7, 'command' => 'OIS-103', 'state' => 'Retour client reçu', 'agent' => 'Admin', 'dateadd' => (string) (self::T0 + 5000)],
        ]);
        $L->table('bls')->insert([
            ['id' => 1, 'dlm' => 53, 'client' => 0, 'code' => 'BL-01', 'cmds' => '0,100,104,99999,100', 'done' => 'on', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 2, 'dlm' => 76, 'client' => 0, 'code' => 'BL-02', 'cmds' => '0,104', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 3, 'dlm' => 0, 'client' => 45, 'code' => 'BL-03', 'cmds' => '0,101,102', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 4, 'dlm' => 0, 'client' => 45, 'code' => 'BR-04', 'cmds' => '0,103', 'done' => 'on', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 5, 'dlm' => 53, 'client' => 0, 'code' => 'BRL-05', 'cmds' => '0,103', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 6, 'dlm' => 0, 'client' => 0, 'code' => 'XX-06', 'cmds' => '0,100', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 7, 'dlm' => 53, 'client' => 0, 'code' => 'BL-01', 'cmds' => '0', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
        ]);
    }

    private function anomalie(string $code): ?object
    {
        return DB::table('reprise_anomalies')->where('code', $code)->first();
    }

    public function test_simulation_ne_rien_ecrire(): void
    {
        $this->artisan('reprise:legacy --simulation')->assertSuccessful();

        $this->assertSame(0, DB::table('commands')->count());
        $this->assertSame(0, DB::table('statuts')->count());
        $this->assertSame(0, DB::table('reprise_anomalies')->count());
        $this->assertSame(0, DB::table('bons_livraison')->count());
    }

    public function test_reprise_complete_exacte_et_anomalies(): void
    {
        $this->artisan('reprise:legacy')->assertSuccessful();

        // Statuts : ids legacy conservés, doublon et libellé hors référentiel signalés.
        $this->assertSame('Manque de stock', DB::table('statuts')->where('id', 18)->value('libelle'));
        $this->assertNotNull($this->anomalie('statut_en_double'));
        $hors = DB::table('statuts')->where('libelle', 'pas de réponse 3 fois')->first();
        $this->assertSame('hors_referentiel', $hors->origine);
        $this->assertSame('en_cours', $hors->groupe);
        $this->assertNotNull(DB::table('reprise_anomalies')->where('code', 'groupe_inconnu')->where('valeur_legacy', 'Bizarre statut')->first());

        // Colis : même id, code en double gardé, valeurs d'origine conservées.
        $this->assertSame(5, DB::table('commands')->count());
        $this->assertSame(2, DB::table('commands')->where('code', 'CODE1')->count());
        $this->assertNotNull($this->anomalie('code_en_double'));

        $sansClient = DB::table('commands')->find(102);
        $this->assertNull($sansClient->client_id);
        $this->assertNull($sansClient->livreur_id);
        $this->assertSame('Ville Fantome', $sansClient->destinataire_ville);
        $uknow = DB::table('villes')->where('name_fr', 'Uknow-ville')->value('id');
        $this->assertSame($uknow, (int) $sansClient->destinataire_ville_id);
        $this->assertSame('(L - XL) taille', json_decode($sansClient->legacy_brut, true)['qty']);
        $this->assertNotNull($this->anomalie('colis_sans_expediteur'));
        $this->assertNotNull($this->anomalie('ville_introuvable'));
        $this->assertNotNull($this->anomalie('ville_vide'));
        $this->assertNotNull($this->anomalie('quantite_invalide'));

        $variante = DB::table('commands')->find(103);
        $this->assertSame('ain atig', $variante->destinataire_ville);
        $this->assertSame(DB::table('villes')->where('name_fr', 'Ain attig')->value('id'), (int) $variante->destinataire_ville_id);
        $this->assertNotNull($variante->deleted_at);
        $this->assertSame('672499672.00', number_format((float) $variante->prix_brut, 2, '.', ''));
        $this->assertFalse((bool) $variante->ouverture_autorisee);
        $this->assertNotNull($this->anomalie('ville_rapprochee'));
        $this->assertNotNull($this->anomalie('montant_suspect'));
        $this->assertNotNull($this->anomalie('ouverture_valeur_inattendue'));

        $casa = DB::table('commands')->find(104);
        $this->assertNull($casa->destinataire_zone_id);
        $this->assertNull($casa->datereported_at);
        $this->assertNotNull($this->anomalie('ville_sans_zone'));
        $this->assertNotNull($this->anomalie('date_report_invalide'));

        // Historique : rattaché par code, from_state reconstitué dans l'ordre des dates.
        $h = DB::table('command_status_histories')->where('command_id', 100)->orderBy('created_at')->orderBy('id')->get();
        $this->assertSame(['Ajouté', 'Ramassé', 'Livré', 'Livré'], $h->pluck('to_state')->all());
        $this->assertSame([null, 'Ajouté', 'Ramassé', 'Livré'], $h->pluck('from_state')->all());
        $this->assertSame(53, (int) $h[1]->changed_by_id);
        $this->assertNull($h[2]->changed_by_id); // « Arbi region » : aucun compte
        $this->assertNull(DB::table('command_status_histories')->where('legacy_history_id', 5)->value('command_id')); // CODE1 ambigu
        $this->assertNull(DB::table('command_status_histories')->where('legacy_history_id', 6)->value('command_id')); // OIS-999 inconnu
        $this->assertNotNull($this->anomalie('historique_code_ambigu'));
        $this->assertNotNull($this->anomalie('historique_sans_colis'));
        $this->assertNotNull($this->anomalie('auteur_inconnu'));

        // Jalons : uniquement depuis des événements réels.
        $livre = DB::table('commands')->find(100);
        $this->assertSame(date('Y-m-d H:i:s', self::T0 + 3600), substr((string) $livre->livre_at, 0, 19));
        $this->assertSame(date('Y-m-d H:i:s', self::T0 + 600), substr((string) $livre->ramasse_at, 0, 19));
        $this->assertNull(DB::table('commands')->find(104)->livre_at); // livré sans événement
        $this->assertNotNull($this->anomalie('livre_sans_evenement'));
        $this->assertNotNull(DB::table('commands')->find(103)->retour_recu_client_at);

        // Notes : trois formats, note d'origine intacte.
        $n100 = DB::table('note_messages')->where('command_id', 100)->orderBy('position')->get();
        $this->assertSame(['livreur', 'moderateur'], $n100->pluck('role')->all());
        $this->assertSame('Client pas chez lui', $n100[0]->message);
        $n103 = DB::table('note_messages')->where('command_id', 103)->orderBy('position')->get();
        $this->assertSame('externe', $n103[0]->role);
        $this->assertSame('Hicham Rabat', $n103[0]->auteur_nom);
        $this->assertTrue((bool) $n103[0]->date_exacte);
        $this->assertSame('inconnu', DB::table('note_messages')->where('command_id', 104)->value('role'));
        $this->assertStringContainsString('<b>Livreur: </b>', DB::table('commands')->find(100)->note);

        // Bons : quatre familles, « 0 » ignoré, colis disparu gardé, doublon dans le bon signalé.
        $bl = DB::table('bons_livraison')->where('source_bls_id', 1)->first();
        $this->assertSame('traite', $bl->status);
        $this->assertSame(3, (int) $bl->total_colis);
        $this->assertSame([100, 104, 99999], DB::table('bon_livraison_command')->where('bon_livraison_id', $bl->id)->orderBy('sequence')->pluck('legacy_command_id')->map(fn ($v) => (int) $v)->all());
        $this->assertNull(DB::table('bon_livraison_command')->where('legacy_command_id', 99999)->value('command_id'));
        $this->assertNotNull($this->anomalie('colis_du_bon_introuvable'));
        $this->assertNotNull($this->anomalie('colis_en_double_dans_bon'));
        $this->assertNotNull($this->anomalie('code_bon_en_double'));

        $be = DB::table('bons_envoi')->where('source_bls_id', 2)->first();
        $this->assertSame('expedie', $be->status);
        $partenaire = DB::table('partenaires')->where('user_id', 76)->value('id');
        $this->assertSame($partenaire, (int) $be->partenaire_id);
        $this->assertSame($partenaire, (int) DB::table('commands')->find(104)->partenaire_id);

        $this->assertSame('valide', DB::table('bon_ramassage')->where('source_bls_id', 3)->value('status'));
        $this->assertSame(2, DB::table('bon_ramassage_lines')->count());
        $this->assertSame('client', DB::table('bons_retour')->where('source_bls_id', 4)->value('type'));
        $this->assertSame('livreur', DB::table('bons_retour')->where('source_bls_id', 5)->value('type'));
        $this->assertSame(0, DB::table('bons_livraison')->where('source_bls_id', 6)->count());
        $this->assertNotNull($this->anomalie('bon_non_classe'));

        // Équipes : livreurs rattachés à l'agence principale, compte au nom d'agence signalé.
        $this->assertTrue(DB::table('agence_membres')->where('user_id', 53)->where('role', 'livreur')->exists());
        $this->assertFalse(DB::table('agence_membres')->where('user_id', 76)->exists());
        $this->assertNotNull($this->anomalie('compte_livreur_atypique'));
    }

    public function test_rejouable_sans_doublon_et_decisions_conservees(): void
    {
        $this->artisan('reprise:legacy')->assertSuccessful();
        $compte = fn () => [DB::table('commands')->count(), DB::table('command_status_histories')->count(), DB::table('note_messages')->count(),
            DB::table('bon_livraison_command')->count(), DB::table('reprise_anomalies')->count()];
        $avant = $compte();

        $id = DB::table('reprise_anomalies')->where('code', 'ville_introuvable')->value('id');
        DB::table('reprise_anomalies')->where('id', $id)->update(['statut' => 'resolue', 'decision' => 'Ville corrigée à la main']);

        $this->artisan('reprise:legacy')->assertSuccessful();
        $this->assertSame($avant, $compte());
        $this->assertSame('resolue', DB::table('reprise_anomalies')->where('id', $id)->value('statut'));

        // Une anomalie ouverte qui disparaît de la source est retirée au passage suivant.
        DB::connection('legacy_test')->table('commands')->where('id', 104)->update(['datereported' => '']);
        $this->artisan('reprise:legacy --etape=colis')->assertSuccessful();
        $this->assertNull($this->anomalie('date_report_invalide'));
    }

    public function test_frais_calcules_non_ecrases(): void
    {
        $this->artisan('reprise:legacy --etape=statuts --etape=colis')->assertSuccessful();
        DB::table('commands')->where('id', 100)->update(['frais_livraison_client' => 25, 'type_tarif_client' => 'client']);
        $this->artisan('reprise:legacy --etape=colis')->assertSuccessful();
        $this->assertSame(25.0, (float) DB::table('commands')->find(100)->frais_livraison_client);
    }

    public function test_rapport_markdown_et_csv(): void
    {
        $this->artisan('reprise:legacy')->assertSuccessful();
        $dossier = storage_path('framework/testing/reprise-'.uniqid());
        $this->artisan('reprise:rapport', ['--sortie' => $dossier])->assertSuccessful();

        $md = file_get_contents("{$dossier}/RAPPORT-REPRISE-ANOMALIES.md");
        $this->assertStringContainsString('ville_introuvable', $md);
        $this->assertStringContainsString('| Colis | 5 |', $md);
        $this->assertGreaterThan(5, count(file("{$dossier}/reprise-anomalies.csv")));
        array_map('unlink', glob("{$dossier}/*"));
        rmdir($dossier);
    }

    public function test_parseur_de_notes(): void
    {
        $r = NoteLegacyParser::analyser('[(1.5mm): 40cm]<b>Livreur: </b>Localisation<br />&amp;lt;b&amp;gt;Client: &amp;lt;/b&amp;gt;&amp;lt;br /&amp;gt;');
        $this->assertSame(['inconnu', 'livreur'], array_column($r['messages'], 'role'));
        $this->assertSame('[(1.5mm): 40cm]', $r['messages'][0]['message']);
        $this->assertSame([], $r['fragments_non_interpretes']);

        $r = NoteLegacyParser::analyser('<i>texte</i>');
        $this->assertCount(1, $r['fragments_non_interpretes']);
        $this->assertSame('texte', $r['messages'][0]['message']);
    }
}
