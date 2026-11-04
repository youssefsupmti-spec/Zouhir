<?php

namespace Tests\Feature\Reprise;

use App\Domain\Reprise\NoteLegacyParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reprise legacy sur une copie miniature de l'ancienne base (SQLite en mémoire), avec les cas réels
 * relevés en production : codes en double, colis sans expéditeur, ville vide / inconnue / variante,
 * « 0 » en tête du CSV des bons, colis disparu, historique lié par code, notes aux trois formats.
 */
class RepriseLegacyTest extends TestCase
{
    use RefreshDatabase;

    use LegacyFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerLegacy();
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
