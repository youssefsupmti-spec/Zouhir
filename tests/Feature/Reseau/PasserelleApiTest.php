<?php

namespace Tests\Feature\Reseau;

use App\Models\BonEnvoi;
use App\Models\BonEnvoiLigne;
use App\Models\Command;
use App\Models\DeliveryPartner;
use App\Models\Statut;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Cycle d'échange complet avec un partenaire national (jeton Bearer + signature HMAC-SHA256). */
class PasserelleApiTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryPartner $partenaire;

    private string $jeton;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $casa = Zone::create(['name' => 'Grand Casablanca', 'code' => 'CASA', 'type' => 'nationale']);
        Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $casa->id, 'code_zone' => 'CASA']);
        foreach (['Ajouté' => 'nouveau', 'Livré' => 'livre', 'Mise en distribution' => 'en_cours', 'Refusé' => 'echec'] as $libelle => $groupe) {
            Statut::create(['libelle' => $libelle, 'groupe' => $groupe, 'actif' => true, 'origine' => 'referentiel_legacy']);
        }
        $compte = User::factory()->create(['nom_complet' => 'Abdelkrim livreur', 'role' => 'livreur']);
        $this->partenaire = DeliveryPartner::create(['code' => 'ABDK', 'name' => 'Abdelkrim Transport', 'user_id' => $compte->id, 'is_active' => true]);
        $this->jeton = $this->partenaire->genererJeton();
        $this->secret = $this->partenaire->genererSecret();
        $this->partenaire->couvertures()->create(['zone_id' => $casa->id, 'tarif_transit_unitaire' => 22.5, 'delai_garanti_h' => 24]);
    }

    /** POST JSON signé (le corps signé est exactement celui envoyé). */
    private function envoyer(string $uri, array $corps, bool $signer = true, ?string $jeton = null): \Illuminate\Testing\TestResponse
    {
        $json = json_encode($corps);
        $entetes = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.($jeton ?? $this->jeton)];
        if ($signer) {
            $entetes['HTTP_X_SIGNATURE_SHA256'] = hash_hmac('sha256', $json, $this->secret);
        }

        return $this->call('POST', $uri, [], [], [], $entetes, $json);
    }

    private function colis(): array
    {
        return ['tracking_number' => 'WE-1001', 'recipient_name' => 'Karim Bennani', 'recipient_phone' => '0661223344',
            'destination_city' => 'Casablanca', 'crbt_amount' => 450, 'is_fragile' => true, 'instructions' => 'Appeler avant'];
    }

    public function test_jeton_et_signature_obligatoires(): void
    {
        $this->getJson('/api/v1/external/referentiel/zones-cities')->assertUnauthorized();
        $this->getJson('/api/v1/external/referentiel/zones-cities', ['Authorization' => 'Bearer faux'])->assertUnauthorized();
        $this->envoyer('/api/v1/external/parcels/import', ['parcels' => [$this->colis()]], signer: false)->assertUnauthorized();
        $this->assertSame(0, Command::count());
        $this->assertDatabaseHas('partner_webhook_logs', ['delivery_partner_id' => $this->partenaire->id, 'signature_valide' => false, 'http_status' => 401]);

        // Le jeton n'est jamais stocké en clair.
        $this->assertNotSame($this->jeton, $this->partenaire->fresh()->api_key_hash);
        $this->assertSame(hash('sha256', $this->jeton), $this->partenaire->fresh()->api_key_hash);

        $this->partenaire->update(['is_active' => false]);
        $this->getJson('/api/v1/external/referentiel/zones-cities', ['Authorization' => 'Bearer '.$this->jeton])->assertForbidden();
    }

    public function test_import_idempotent_et_ville_inconnue_refusee(): void
    {
        $inconnu = ['tracking_number' => 'WE-1002', 'recipient_name' => 'X', 'recipient_phone' => '0600000000', 'destination_city' => 'Atlantide', 'crbt_amount' => 10];
        $r = $this->envoyer('/api/v1/external/parcels/import', ['batch_reference' => 'B-1', 'parcels' => [$this->colis(), $inconnu]])->assertOk();
        $r->assertJsonCount(1, 'acceptes')->assertJsonCount(1, 'refuses')->assertJsonPath('refuses.0.tracking_number', 'WE-1002');

        $colis = Command::where('partner_tracking_code', 'WE-1001')->firstOrFail();
        $this->assertSame($this->partenaire->id, (int) $colis->delivery_partner_id);
        $this->assertSame('Ajouté', $colis->state);
        $this->assertSame('CASA', $colis->destinataire_zone_code);
        $this->assertTrue(DB::table('command_status_histories')->where('command_id', $colis->id)->where('change_source', 'api')->exists());
        $this->assertStringContainsString('Colis fragile', DB::table('note_messages')->where('command_id', $colis->id)->value('message'));

        $this->envoyer('/api/v1/external/parcels/import', ['parcels' => [$this->colis()]])->assertOk()
            ->assertJsonCount(0, 'acceptes')->assertJsonPath('doublons.0.code', $colis->code);
        $this->assertSame(1, Command::count());

        $this->envoyer('/api/v1/external/parcels/import', ['parcels' => []])->assertStatus(422);
    }

    public function test_suivi_et_changement_de_statut_aux_deux_formats(): void
    {
        $this->envoyer('/api/v1/external/parcels/import', ['parcels' => [$this->colis()]])->assertOk();
        $code = Command::firstOrFail()->code;

        $this->getJson('/api/v1/external/parcels/WE-1001/track', ['Authorization' => 'Bearer '.$this->jeton])
            ->assertOk()->assertJsonPath('data.code', $code)->assertJsonPath('data.status', 'Ajouté');
        $this->getJson('/api/v1/external/parcels/INCONNU/track', ['Authorization' => 'Bearer '.$this->jeton])->assertNotFound();

        // Format rapport 63 / WinningExpress.
        $this->envoyer('/api/v1/external/parcels/webhook/status', ['event' => 'parcel.status_changed',
            'data' => ['tracking_number' => 'WE-1001', 'status_code' => 'DELIVERED', 'delivered_at' => '2026-11-06 14:32:00', 'notes' => 'Remis en mains propres']])
            ->assertOk()->assertJsonPath('status', 'Livré');
        $colis = Command::firstOrFail();
        $this->assertSame('livre', $colis->state_group);
        $this->assertSame('2026-11-06 14:32:00', $colis->livre_at->format('Y-m-d H:i:s'));
        $this->assertTrue((bool) $colis->is_ramassee);

        // Statut inconnu : refusé et journalisé, rien n'est appliqué.
        $this->envoyer('/api/v1/external/parcels/webhook/status', ['tracking_number' => 'WE-1001', 'status_code' => 'TELEPORTED'])->assertStatus(422);
        $this->assertSame('Livré', $colis->fresh()->state);

        // Correspondance propre au partenaire, puis format Zakaria2 sur /webhooks/{code} (signature seule).
        $this->partenaire->statusMappings()->create(['partner_status' => 'REFUS_CLIENT', 'colis_status' => 'Refusé']);
        $json = json_encode(['tracking_code' => 'WE-1001', 'status' => 'refus_client']);
        $this->call('POST', '/api/v1/webhooks/ABDK', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PARTNER_SIGNATURE' => hash_hmac('sha256', $json, $this->secret)], $json)->assertOk();
        $this->assertSame('Refusé', $colis->fresh()->state);
        $this->assertSame(3, DB::table('partner_webhook_logs')->where('processed', true)->count());
    }

    public function test_reception_contradictoire_d_un_bon_d_envoi(): void
    {
        $bon = BonEnvoi::create(['code' => 'BE-77', 'delivery_partner_id' => $this->partenaire->id, 'status' => 'expedie', 'total_colis' => 2]);
        foreach (['OEX-A', 'OEX-B'] as $i => $c) {
            BonEnvoiLigne::create(['bon_envoi_id' => $bon->id, 'legacy_command_id' => $i + 1, 'command_code' => $c, 'sequence' => $i + 1]);
        }

        $this->envoyer('/api/v1/external/bons-envoi/BE-77/receive', ['parcels' => ['OEX-A', 'OEX-Z']])->assertOk()
            ->assertJsonPath('received', 1)->assertJsonPath('missing', ['OEX-B'])->assertJsonPath('unexpected', ['OEX-Z'])->assertJsonPath('status', 'expedie');
        $this->envoyer('/api/v1/external/bons-envoi/BE-77/receive', [])->assertOk()->assertJsonPath('status', 'traite')->assertJsonPath('missing', []);
        $this->envoyer('/api/v1/external/bons-envoi/BE-AUTRE/receive', [])->assertNotFound();
    }

    public function test_referentiel_et_estimation(): void
    {
        $h = ['Authorization' => 'Bearer '.$this->jeton];
        $this->getJson('/api/v1/external/referentiel/zones-cities', $h)->assertOk()->assertJsonPath('data.0.code', 'CASA')->assertJsonPath('data.0.cities.0.name', 'Casablanca');
        $this->getJson('/api/v1/external/rates/estimate?destination_city=Casablanca', $h)->assertOk()
            ->assertJsonPath('data.transit_rate', 22.5)->assertJsonPath('data.guaranteed_delay_h', 24)->assertJsonPath('data.source', 'zone');
        $this->getJson('/api/v1/external/rates/estimate?destination_city=Atlantide', $h)->assertStatus(422);
    }
}
