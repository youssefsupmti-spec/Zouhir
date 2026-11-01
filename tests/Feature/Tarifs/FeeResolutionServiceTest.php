<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Tarifs\Commun\Enums\TypeTarif;
use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Domain\Tarifs\Commun\Services\TarifIntrouvableException;
use App\Domain\Villes\Support\VilleRef;
use App\Domain\Villes\Support\VilleResolution;
use App\Models\ClientTarif;
use App\Models\Command;
use App\Models\Ville;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeeResolutionServiceTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
    }

    private function fees(): FeeResolutionService
    {
        return app(FeeResolutionService::class);
    }

    /* ─────────────── Modèle ─────────────── */

    public function test_relations_casts_et_soft_deletes(): void
    {
        $t = $this->tarif($this->clientA, $this->temara, '15');

        $this->assertTrue($t->client->is($this->clientA));
        $this->assertTrue($t->ville->is($this->temara));
        $this->assertSame('15.00', $t->fresh()->frais_livraison);
        $this->assertSame(1, $this->clientA->clientTarifs()->count());
        $this->assertSame(1, $this->temara->clientTarifs()->count());

        $t->delete();
        $this->assertSoftDeleted('client_tarifs', ['id' => $t->id]);
        $this->assertSame(0, $this->clientA->clientTarifs()->count());
    }

    public function test_unicite_client_ville_imposee_par_la_base_meme_apres_suppression_logique(): void
    {
        $t = $this->tarif($this->clientA, $this->temara, '15');
        $t->delete();

        $this->expectException(QueryException::class);
        $this->tarif($this->clientA, $this->temara, '18');
    }

    /* ─────────────── Cascade ─────────────── */

    public function test_tarif_negocie_prioritaire_sur_le_standard(): void
    {
        $this->standard($this->temara, '20');
        $t = $this->tarif($this->clientA, $this->temara, '15', '5', '7');

        $r = $this->fees()->clientFees($this->clientA->id, $this->temara->id);

        $this->assertSame(TypeTarif::Client, $r->type);
        $this->assertSame(['15.00', '5.00', '7.00'], [$r->livraison, $r->refus, $r->retour]);
        $this->assertSame($t->id, $r->sourceId);
    }

    public function test_client_sans_tarif_negocie_utilise_le_standard(): void
    {
        $s = $this->standard($this->temara, '20', '10', '10');

        $r = $this->fees()->clientFees($this->clientB->id, $this->temara->id);

        $this->assertSame(TypeTarif::Standard, $r->type);
        $this->assertSame('20.00', $r->livraison);
        $this->assertSame($s->id, $r->sourceId);
    }

    public function test_tarif_client_a_zero_est_une_gratuite_prioritaire(): void
    {
        $this->standard($this->temara, '20');
        $this->tarif($this->clientA, $this->temara, '0');

        $r = $this->fees()->clientFees($this->clientA->id, $this->temara->id);

        $this->assertSame(TypeTarif::Client, $r->type);
        $this->assertSame('0.00', $r->livraison);
    }

    public function test_standard_a_zero_est_valide(): void
    {
        $this->standard($this->temara, '0');

        $r = $this->fees()->clientFees($this->clientA->id, $this->temara->id);

        $this->assertSame(TypeTarif::Standard, $r->type);
        $this->assertSame('0.00', $r->livraison);
    }

    public function test_aucun_tarif_renvoie_une_anomalie_sans_montant_invente(): void
    {
        $r = $this->fees()->clientFees($this->clientA->id, $this->casablanca->id);

        $this->assertSame(TypeTarif::Missing, $r->type);
        $this->assertTrue($r->estManquant());
        $this->assertNull($r->livraison);

        $this->expectException(TarifIntrouvableException::class);
        $this->fees()->exiger($this->clientA->id, $this->casablanca->id);
    }

    public function test_tarif_supprime_logiquement_renvoie_au_standard(): void
    {
        $this->standard($this->temara, '20');
        $this->tarif($this->clientA, $this->temara, '15')->delete();

        $r = $this->fees()->clientFees($this->clientA->id, $this->temara->id);

        $this->assertSame(TypeTarif::Standard, $r->type);
        $this->assertSame('20.00', $r->livraison);
    }

    public function test_colis_au_tarif_manuel_n_est_jamais_resolu(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');
        $colis = new Command([
            'client_id' => $this->clientA->id,
            'destinataire_ville_id' => $this->temara->id,
            'frais_livraison_client' => '0.00',
            'type_tarif_client' => 'manual',
        ]);

        $r = $this->fees()->pourColis($colis);

        $this->assertSame(TypeTarif::Manuel, $r->type);
        $this->assertSame('0.00', $r->livraison);
    }

    public function test_promo_non_implementee_n_intervient_pas(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');

        $this->assertSame(TypeTarif::Client, $this->fees()->clientFees($this->clientA->id, $this->temara->id)->type);
        $this->assertSame(TypeTarif::Promo, TypeTarif::depuis('promo_tarifs'));
    }

    public function test_type_tarif_lit_les_vocabulaires_historiques(): void
    {
        $this->assertSame(TypeTarif::Client, TypeTarif::depuis('clientfees'));
        $this->assertSame(TypeTarif::Standard, TypeTarif::depuis('gshippingfees'));
        $this->assertSame(TypeTarif::Manuel, TypeTarif::depuis('custom'));
        $this->assertNull(TypeTarif::depuis('frozen'));
        $this->assertSame('—', TypeTarif::libelle(null));
    }

    /* ─────────────── Villes ─────────────── */

    public function test_ville_resolue_par_id_et_par_libelle(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');

        $this->assertSame(TypeTarif::Client, $this->fees()->clientFees($this->clientA->id, $this->temara->id)->type);
        $this->assertSame(TypeTarif::Client, $this->fees()->clientFees($this->clientA->id, '  temara ')->type);

        $colis = new Command(['client_id' => $this->clientA->id, 'destinataire_ville' => 'TEMARA']);
        $this->assertSame('15.00', $this->fees()->pourColis($colis)->livraison);
    }

    public function test_variantes_historiques_du_dictionnaire(): void
    {
        $tamesna = Ville::create(['name_fr' => 'Tamesna', 'zone_id' => $this->zoneRabat->id]);
        $ref = app(VilleRef::class);
        $ref->oublier();

        $r = $ref->resoudre('Tamessna');
        $this->assertSame($tamesna->id, $r->villeId);
        $this->assertSame(VilleResolution::ALIAS, $r->methode);

        $this->assertSame(VilleResolution::NORMALISEE, $ref->resoudre('Témara')->methode);
        $this->assertSame(VilleResolution::EXACTE, $ref->resoudre('skhirate')->methode);
        $this->assertSame(VilleResolution::INTROUVABLE, $ref->resoudre('Ville Inconnue')->methode);
    }

    public function test_homonymes_ville_active_au_plus_petit_id_regle_winningexpress(): void
    {
        $homonyme = Ville::create(['name_fr' => 'CASABLANCA', 'zone_id' => $this->zoneRabat->id]); // id plus grand
        app(VilleRef::class)->oublier();
        $this->tarif($this->clientA, $this->casablanca, '30');

        $res = app(VilleRef::class)->resoudre('Casablanca');
        $this->assertSame(VilleResolution::HOMONYMES, $res->methode);
        $this->assertSame($this->casablanca->id, $res->villeId, 'Plus petit id retenu.');
        $this->assertSame([$this->casablanca->id, $homonyme->id], $res->candidats);
        $this->assertSame(TypeTarif::Client, $this->fees()->clientFees($this->clientA->id, 'Casablanca')->type);

        // Une ville ACTIVE l'emporte sur une archivée plus ancienne.
        $this->casablanca->delete();
        app(VilleRef::class)->oublier();
        $this->assertSame($homonyme->id, app(VilleRef::class)->resoudre('Casablanca', historique: true)->villeId);
    }

    public function test_ville_archivee_seulement_en_mode_historique(): void
    {
        $zagora = Ville::create(['name_fr' => 'ZAGORA', 'zone_id' => $this->zoneRabat->id]);
        $zagora->delete();
        $ref = app(VilleRef::class);
        $ref->oublier();

        // Sélection opérationnelle : jamais une ville archivée.
        $this->assertNull($ref->resoudre('Zagora')->villeId);
        $this->assertNull($ref->resoudre($zagora->id)->villeId);
        // Reprise historique : seule correspondance → acceptée.
        $r = $ref->resoudre('Zagora', historique: true);
        $this->assertSame($zagora->id, $r->villeId);
        $this->assertSame(VilleResolution::ARCHIVEE, $r->methode);
    }

    /* ─────────────── Performance ─────────────── */

    public function test_warm_precharge_en_deux_requetes_puis_zero(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');
        $this->standard($this->skhirate, '20');
        $paires = [
            [$this->clientA->id, $this->temara->id],
            [$this->clientA->id, $this->skhirate->id],
            [$this->clientB->id, $this->temara->id],
            [$this->clientB->id, $this->casablanca->id],
        ];

        $fees = $this->fees();
        DB::enableQueryLog();
        $fees->warm($paires);
        $this->assertCount(2, DB::getQueryLog());

        DB::flushQueryLog();
        $types = array_map(fn ($p) => $fees->clientFees($p[0], $p[1])->type->value, $paires);
        $this->assertCount(0, DB::getQueryLog(), 'Après warm(), aucune requête (absences mémorisées comprises).');
        DB::disableQueryLog();

        $this->assertSame(['client', 'standard', 'missing', 'missing'], $types);
    }

    public function test_oublier_vide_le_cache_apres_ecriture(): void
    {
        $fees = $this->fees();
        $this->assertSame(TypeTarif::Missing, $fees->clientFees($this->clientA->id, $this->temara->id)->type);

        ClientTarif::create(['client_id' => $this->clientA->id, 'ville_id' => $this->temara->id, 'frais_livraison' => 12, 'frais_refus' => 0, 'frais_retour' => 0]);
        $this->assertSame(TypeTarif::Missing, $fees->clientFees($this->clientA->id, $this->temara->id)->type, 'Cache scoped : absence mémorisée.');

        $fees->oublier();
        $this->assertSame(TypeTarif::Client, $fees->clientFees($this->clientA->id, $this->temara->id)->type);
    }
}
