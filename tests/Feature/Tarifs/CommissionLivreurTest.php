<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Domain\Tarifs\Livreurs\Enums\TypeCommission;
use App\Models\Command;
use App\Models\LivreurTarif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Moteur de commission livreur et intégration aux workflows colis (snapshots séparés). */
class CommissionLivreurTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    private User $hamza;
    private User $jalal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
        $this->hamza = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Hamza']);
        $this->jalal = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Jalal']);
    }

    private function fees(): FeeResolutionService
    {
        $f = app(FeeResolutionService::class);
        $f->oublier();

        return $f;
    }

    private function specifique(User $l, $ville, string $liv, string $refus = '0'): LivreurTarif
    {
        return LivreurTarif::create(['livreur_id' => $l->id, 'ville_id' => $ville->id, 'commission_livraison' => $liv, 'commission_refus' => $refus]);
    }

    private function creerColis(array $extra = []): Command
    {
        $this->actingAs($this->admin)->post('/colis', $extra + [
            'client_id' => $this->clientA->id, 'destinataire_nom' => 'Sara', 'destinataire_telephone' => '0661122334',
            'destinataire_ville_id' => $this->temara->id, 'prix_brut' => 200, 'livreur_id' => $this->hamza->id,
        ])->assertSessionHasNoErrors();

        return Command::latest('id')->firstOrFail();
    }

    /* ─────────── Résolution ─────────── */

    public function test_specifique_sinon_missing_sans_bareme_standard(): void
    {
        $this->specifique($this->hamza, $this->temara, '12', '2');

        $r = $this->fees()->driverFees($this->hamza->id, $this->temara->id);
        $this->assertSame([TypeCommission::Specifique, '12.00', '2.00'], [$r->type, $r->livraison, $r->refus]);
        $this->assertSame('12.00', $this->fees()->driverFees($this->hamza->id, 'temara')->livraison, 'Ville par libellé (VilleRef).');

        // Un autre livreur sans commission dans cette ville : manquante (comme WinningExpress), jamais un standard.
        $this->assertTrue($this->fees()->driverFees($this->jalal->id, $this->temara->id)->estManquante());

        $m = $this->fees()->driverFees($this->hamza->id, $this->casablanca->id);
        $this->assertTrue($m->estManquante());
        $this->assertNull($m->livraison, 'Absence ≠ 0.');
        $this->assertTrue($this->fees()->driverFees(null, $this->temara->id)->estManquante());
    }

    public function test_commission_zero_est_valide(): void
    {
        $this->specifique($this->hamza, $this->temara, '0');

        $r = $this->fees()->driverFees($this->hamza->id, $this->temara->id);
        $this->assertSame(TypeCommission::Specifique, $r->type);
        $this->assertSame('0.00', $r->livraison);
    }

    public function test_commission_supprimee_devient_manquante(): void
    {
        $this->specifique($this->hamza, $this->temara, '12')->delete();

        $this->assertTrue($this->fees()->driverFees($this->hamza->id, $this->temara->id)->estManquante());
    }

    public function test_domaines_separes_le_standard_client_n_est_jamais_une_commission(): void
    {
        $this->standard($this->temara, '20');

        $this->assertTrue($this->fees()->driverFees($this->hamza->id, $this->temara->id)->estManquante());
    }

    /* ─────────── Workflows colis ─────────── */

    public function test_creation_fige_tarif_client_et_commission_separement_sans_valeur_inventee(): void
    {
        $this->tarif($this->clientA, $this->temara, '35');
        $this->specifique($this->hamza, $this->temara, '11');

        $c = $this->creerColis();
        $this->assertSame(['35.00', 'client'], [$c->frais_livraison_client, $c->type_tarif_client]);
        $this->assertSame(['11.00', 'specifique'], [$c->frais_livraison_livreur, $c->type_commission_livreur]);

        // CRBT 200 - frais client 35 = 165 net marchand ; marge 35 - 11 = 24 : trois valeurs distinctes.
        $this->assertSame(165.0, (float) $c->prix_brut - (float) $c->frais_livraison_client);
        $this->assertSame(24.0, (float) $c->frais_livraison_client - (float) $c->frais_livraison_livreur);

        $sans = $this->creerColis(['livreur_id' => null]);
        $this->assertSame(['0.00', null], [$sans->frais_livraison_livreur, $sans->type_commission_livreur], 'Plus de 15 MAD inventés.');

        $missing = $this->creerColis(['destinataire_ville_id' => $this->casablanca->id]);
        $this->assertSame(['0.00', 'missing'], [$missing->frais_livraison_livreur, $missing->type_commission_livreur]);
    }

    public function test_commission_manuelle_de_l_administration(): void
    {
        $this->specifique($this->hamza, $this->temara, '11');

        $c = $this->creerColis(['frais_livraison_livreur' => '0']);
        $this->assertSame(['0.00', 'manual'], [$c->frais_livraison_livreur, $c->type_commission_livreur]);
    }

    public function test_changement_de_livreur_avant_finalisation_realigne_la_commission(): void
    {
        $this->specifique($this->hamza, $this->temara, '11');
        $this->specifique($this->jalal, $this->temara, '14');
        $c = $this->creerColis();

        $this->actingAs($this->admin)->patch("/colis/{$c->id}/state", ['state_group' => 'en_cours', 'livreur_id' => $this->jalal->id])->assertSessionHasNoErrors();
        $this->assertSame('14.00', $c->fresh()->frais_livraison_livreur);

        // Livraison : la commission reste celle du livreur en charge.
        $this->actingAs($this->admin)->patch("/colis/{$c->id}/state", ['state_group' => 'livre'])->assertSessionHasNoErrors();
        $this->assertSame(['14.00', 'specifique'], [$c->fresh()->frais_livraison_livreur, $c->fresh()->type_commission_livreur]);
    }

    public function test_colis_livre_protege_aucun_recalcul_ni_saisie(): void
    {
        $this->specifique($this->hamza, $this->temara, '11');
        $this->specifique($this->jalal, $this->temara, '14');
        $c = $this->creerColis();
        $this->actingAs($this->admin)->patch("/colis/{$c->id}/state", ['state_group' => 'livre']);

        // Changement de livreur après livraison : la commission due reste figée.
        $this->actingAs($this->admin)->patch("/colis/{$c->id}/state", ['state_group' => 'livre', 'livreur_id' => $this->jalal->id]);
        $this->assertSame('11.00', $c->fresh()->frais_livraison_livreur);

        // Saisie manuelle différente sur colis finalisé : refusée.
        $this->actingAs($this->admin)->put("/colis/{$c->id}", [
            'destinataire_nom' => 'Sara', 'destinataire_telephone' => '0661122334', 'destinataire_ville_id' => $this->temara->id,
            'prix_brut' => 200, 'livreur_id' => $this->hamza->id, 'frais_livraison_livreur' => '30',
        ])->assertSessionHasErrors('frais_livraison_livreur');
        $this->assertSame('11.00', $c->fresh()->frais_livraison_livreur);
    }

    public function test_modifier_la_grille_ne_recalcule_aucune_commission_existante(): void
    {
        $t = $this->specifique($this->hamza, $this->temara, '11');
        $c = $this->creerColis();

        $this->actingAs($this->admin)->put('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'lignes' => [['id' => $t->id, 'livraison' => '20', 'refus' => '0']]])->assertSessionHasNoErrors();

        $this->assertSame('11.00', $c->fresh()->frais_livraison_livreur, 'Aucune modification rétroactive.');
    }

    public function test_changement_de_ville_avant_finalisation(): void
    {
        $this->specifique($this->hamza, $this->temara, '11');
        $this->specifique($this->hamza, $this->skhirate, '9');
        $c = $this->creerColis();

        $this->actingAs($this->admin)->put("/colis/{$c->id}", [
            'destinataire_nom' => 'Sara', 'destinataire_telephone' => '0661122334', 'destinataire_ville_id' => $this->skhirate->id,
            'prix_brut' => 200, 'livreur_id' => $this->hamza->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['9.00', 'specifique'], [$c->fresh()->frais_livraison_livreur, $c->fresh()->type_commission_livreur]);
    }
}
