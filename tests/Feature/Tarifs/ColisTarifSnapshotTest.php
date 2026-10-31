<?php

namespace Tests\Feature\Tarifs;

use App\Models\Command;
use App\Models\Ville;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Intégration du moteur tarifaire aux workflows de colis (création / modification). */
class ColisTarifSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'client_id' => $this->clientA->id,
            'destinataire_nom' => 'Sara',
            'destinataire_telephone' => '0661122334',
            'destinataire_ville_id' => $this->temara->id,
            'prix_brut' => 300,
        ];
    }

    private function dernier(): Command
    {
        return Command::latest('id')->firstOrFail();
    }

    private function colisExistant(array $attrs): Command
    {
        return Command::create($attrs + [
            'code' => 'OUA-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'client_id' => $this->clientA->id,
            'destinataire_nom' => 'Y',
            'destinataire_telephone' => '0600000000',
            'destinataire_ville' => 'Temara',
            'destinataire_ville_id' => $this->temara->id,
            'state' => 'Nouveau Colis',
            'state_group' => 'nouveau',
        ]);
    }

    private function majPayload(Command $c, array $extra = []): array
    {
        return $extra + [
            'destinataire_nom' => $c->destinataire_nom,
            'destinataire_telephone' => $c->destinataire_telephone,
            'destinataire_ville_id' => $c->destinataire_ville_id,
            'prix_brut' => 300,
        ];
    }

    public function test_creation_par_le_client_fige_le_tarif_negocie(): void
    {
        $this->standard($this->temara, '20');
        $this->tarif($this->clientA, $this->temara, '15');

        // Le marchand tente d'imposer 0 : ignoré (seule l'administration fixe un montant manuel).
        $this->actingAs($this->clientA)->post('/colis', $this->payload(['frais_livraison_client' => 0]))->assertSessionHasNoErrors();

        $this->assertSame('15.00', $this->dernier()->frais_livraison_client);
        $this->assertSame('client', $this->dernier()->type_tarif_client);
    }

    public function test_creation_sans_negocie_fige_le_standard(): void
    {
        $this->standard($this->temara, '20');

        $this->actingAs($this->admin)->post('/colis', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame('20.00', $this->dernier()->frais_livraison_client);
        $this->assertSame('standard', $this->dernier()->type_tarif_client);
    }

    public function test_creation_sans_aucun_tarif_signale_l_anomalie_sans_inventer_35(): void
    {
        $this->actingAs($this->admin)->post('/colis', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error');

        $this->assertSame('missing', $this->dernier()->type_tarif_client);
        $this->assertSame('0.00', $this->dernier()->frais_livraison_client);
    }

    public function test_tarif_negocie_a_zero_preserve_a_la_creation(): void
    {
        $this->standard($this->temara, '20');
        $this->tarif($this->clientA, $this->temara, '0');

        $this->actingAs($this->clientA)->post('/colis', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame('0.00', $this->dernier()->frais_livraison_client);
        $this->assertSame('client', $this->dernier()->type_tarif_client);
    }

    public function test_montant_manuel_de_l_administration_y_compris_zero(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');

        $this->actingAs($this->admin)->post('/colis', $this->payload(['frais_livraison_client' => '0']))->assertSessionHasNoErrors();

        $this->assertSame('0.00', $this->dernier()->frais_livraison_client);
        $this->assertSame('manual', $this->dernier()->type_tarif_client);
    }

    public function test_modification_sans_incidence_tarifaire_ne_recalcule_rien(): void
    {
        $c = $this->colisExistant(['frais_livraison_client' => '15.00', 'type_tarif_client' => 'client']);
        $this->tarif($this->clientA, $this->temara, '40'); // la grille a changé depuis

        $this->actingAs($this->admin)->put("/colis/{$c->id}", $this->majPayload($c, ['frais_livraison_client' => '15.00', 'note' => 'appel']))
            ->assertSessionHasNoErrors();

        $this->assertSame('15.00', $c->fresh()->frais_livraison_client);
        $this->assertSame('client', $c->fresh()->type_tarif_client);
    }

    public function test_changement_de_ville_avant_finalisation_recalcule(): void
    {
        $this->tarif($this->clientA, $this->skhirate, '22');
        $c = $this->colisExistant(['frais_livraison_client' => '15.00', 'type_tarif_client' => 'client']);

        $this->actingAs($this->clientA)->put("/colis/{$c->id}", $this->majPayload($c, ['destinataire_ville_id' => $this->skhirate->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('22.00', $c->fresh()->frais_livraison_client);
        $this->assertSame('client', $c->fresh()->type_tarif_client);
        $this->assertSame('Skhirate', $c->fresh()->destinataire_ville);
    }

    public function test_colis_livre_protege_meme_si_la_ville_change(): void
    {
        $this->tarif($this->clientA, $this->skhirate, '22');
        $c = $this->colisExistant(['frais_livraison_client' => '15.00', 'type_tarif_client' => 'client', 'state' => 'Livré', 'state_group' => 'livre']);

        $this->actingAs($this->admin)->put("/colis/{$c->id}", $this->majPayload($c, ['destinataire_ville_id' => $this->skhirate->id, 'frais_livraison_client' => '15.00']))
            ->assertSessionHasNoErrors();
        $this->assertSame('15.00', $c->fresh()->frais_livraison_client);

        // Tentative de modification du montant d'un colis finalisé : refusée explicitement.
        $this->actingAs($this->admin)->put("/colis/{$c->id}", $this->majPayload($c->fresh(), ['frais_livraison_client' => '5']))
            ->assertSessionHasErrors('frais_livraison_client');
        $this->assertSame('15.00', $c->fresh()->frais_livraison_client);
    }

    public function test_tarif_manuel_preserve_au_changement_de_ville(): void
    {
        $this->tarif($this->clientA, $this->skhirate, '22');
        $c = $this->colisExistant(['frais_livraison_client' => '9.00', 'type_tarif_client' => 'manual']);

        $this->actingAs($this->clientA)->put("/colis/{$c->id}", $this->majPayload($c, ['destinataire_ville_id' => $this->skhirate->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('9.00', $c->fresh()->frais_livraison_client);
        $this->assertSame('manual', $c->fresh()->type_tarif_client);
    }

    public function test_snapshot_historique_a_zero_jamais_reecrit_sans_changement_tarifaire(): void
    {
        // Colis importé du legacy : 0.00 et provenance inconnue (NULL).
        $this->tarif($this->clientA, $this->temara, '15');
        $c = $this->colisExistant(['frais_livraison_client' => '0.00', 'type_tarif_client' => null]);

        $this->actingAs($this->clientA)->put("/colis/{$c->id}", $this->majPayload($c, ['note' => 'x']))->assertSessionHasNoErrors();

        $this->assertSame('0.00', $c->fresh()->frais_livraison_client);
        $this->assertNull($c->fresh()->type_tarif_client);
    }

    public function test_changement_de_statut_ne_touche_pas_au_tarif(): void
    {
        $this->tarif($this->clientA, $this->temara, '40');
        $c = $this->colisExistant(['frais_livraison_client' => '15.00', 'type_tarif_client' => 'client']);

        $this->actingAs($this->admin)->patch("/colis/{$c->id}/state", ['state_group' => 'livre'])->assertSessionHasNoErrors();

        $this->assertSame('15.00', $c->fresh()->frais_livraison_client);
        $this->assertSame('livre', $c->fresh()->state_group);
    }

    public function test_page_colis_expose_la_capacite_de_saisie_manuelle(): void
    {
        $this->actingAs($this->admin)->get('/colis')->assertInertia(fn ($p) => $p->where('canSetManualFee', true));
        $this->actingAs($this->clientA)->get('/colis')->assertInertia(fn ($p) => $p->where('canSetManualFee', false));
    }
}
