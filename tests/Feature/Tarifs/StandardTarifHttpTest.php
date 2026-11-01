<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Enums\UserRole;
use App\Models\StandardTarif;
use App\Models\Ville;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Page « Tarifs de livraison » (/tarifs-standard) : barème standard facturé aux marchands. */
class StandardTarifHttpTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
    }

    public function test_grille_kpi_et_villes_sans_tarif(): void
    {
        $this->standard($this->temara, '15');
        $archivee = Ville::create(['name_fr' => 'Zagora', 'zone_id' => $this->zoneRabat->id]);
        $this->standard($archivee, '50');
        $archivee->delete();

        $this->actingAs($this->admin)->get('/tarifs-standard')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('TarifsStandard/Index')
            ->where('stats.villes', 3)
            ->where('stats.tarifees', 1)
            ->where('stats.sans_tarif', 2)
            ->where('stats.moyenne', '15.00')
            ->has('villes.data', 3)
            ->where('villes.data.0.zone_nom', 'Casablanca-Settat')
            ->where('villes.data.0.tarif', null)
            ->where('can.create', true));
    }

    public function test_recherche_filtres_statut_zone_et_pagination(): void
    {
        $this->standard($this->temara, '15');
        Ville::whereKey($this->skhirate->id)->update(['name_ar' => 'الصخيرات']);

        $this->actingAs($this->admin)->get('/tarifs-standard?statut=tarifees')
            ->assertInertia(fn (Assert $p) => $p->has('villes.data', 1)->where('villes.data.0.ville_nom', 'Temara'));
        $this->actingAs($this->admin)->get('/tarifs-standard?statut=sans&zone_id='.$this->zoneRabat->id)
            ->assertInertia(fn (Assert $p) => $p->has('villes.data', 1)->where('villes.data.0.ville_nom', 'Skhirate'));
        $this->actingAs($this->admin)->get('/tarifs-standard?search='.urlencode('الصخيرات'))
            ->assertInertia(fn (Assert $p) => $p->has('villes.data', 1)->where('villes.data.0.ville_nom', 'Skhirate'));
        $this->actingAs($this->admin)->get('/tarifs-standard?per_page=10&page=1')
            ->assertInertia(fn (Assert $p) => $p->where('villes.per_page', 10)->where('villes.total', 3));
    }

    public function test_ajout_en_lot_sans_ecraser_les_existants_par_defaut(): void
    {
        $this->standard($this->temara, '15');
        $payload = ['ville_ids' => [$this->temara->id, $this->skhirate->id], 'livraison' => '20', 'refus' => '0', 'retour' => '0', 'delai' => '24H', 'existants' => 'ignorer'];

        $this->actingAs($this->admin)->postJson('/tarifs-standard/apercu', $payload)
            ->assertOk()->assertJsonPath('compte.nouveau', 1)->assertJsonPath('compte.existant', 1);

        $this->actingAs($this->admin)->post('/tarifs-standard', $payload)->assertSessionHasNoErrors();
        $this->assertSame('15.00', StandardTarif::where('ville_id', $this->temara->id)->value('frais_livraison'), 'Existant non écrasé.');
        $this->assertSame('24H', StandardTarif::where('ville_id', $this->skhirate->id)->value('delai'));

        $this->actingAs($this->admin)->post('/tarifs-standard', ['existants' => 'mettre_a_jour'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('20.00', StandardTarif::where('ville_id', $this->temara->id)->value('frais_livraison'));
        $this->assertSame(2, StandardTarif::count(), 'Aucun doublon (UNIQUE ville_id).');
    }

    public function test_application_a_une_zone_avec_exclusion(): void
    {
        $this->actingAs($this->admin)->post('/tarifs-standard', [
            'zone_id' => $this->zoneRabat->id, 'exclusions' => [$this->skhirate->id],
            'livraison' => '0', 'refus' => '0', 'retour' => '0', 'existants' => 'ignorer',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['0.00'], StandardTarif::pluck('frais_livraison')->all(), 'Zéro accepté ; Skhirate exclue.');
        $this->assertSame($this->temara->id, StandardTarif::firstOrFail()->ville_id);
    }

    public function test_edition_inline_en_lot_et_erreurs_par_ligne(): void
    {
        $this->standard($this->temara, '15');

        $this->actingAs($this->admin)->put('/tarifs-standard', ['lignes' => [
            ['ville_id' => $this->temara->id, 'livraison' => '16.5', 'refus' => '0', 'retour' => '0', 'delai' => '48H'],
            ['ville_id' => $this->skhirate->id, 'livraison' => '-3', 'refus' => '0', 'retour' => '0'],
            ['ville_id' => $this->casablanca->id, 'livraison' => '', 'refus' => '0', 'retour' => '0'],
        ]])->assertSessionHasErrors(['lignes.1.livraison', 'lignes.2.livraison']);
        $this->assertSame('15.00', StandardTarif::firstOrFail()->frais_livraison, 'Rien écrit si une ligne est invalide.');

        $this->actingAs($this->admin)->put('/tarifs-standard', ['lignes' => [
            ['ville_id' => $this->temara->id, 'livraison' => '16.5', 'refus' => '0', 'retour' => '0', 'delai' => '48H'],
            ['ville_id' => $this->skhirate->id, 'livraison' => '20', 'refus' => '5', 'retour' => '5'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('16.50', StandardTarif::where('ville_id', $this->temara->id)->value('frais_livraison'));
        $this->assertSame('48H', StandardTarif::where('ville_id', $this->temara->id)->value('delai'));
        $this->assertSame('5.00', StandardTarif::where('ville_id', $this->skhirate->id)->value('frais_refus'));
    }

    public function test_ville_archivee_refusee_en_ecriture(): void
    {
        $this->skhirate->delete();

        $this->actingAs($this->admin)->post('/tarifs-standard', ['ville_ids' => [$this->skhirate->id], 'livraison' => '20', 'refus' => '0', 'retour' => '0', 'existants' => 'ignorer'])
            ->assertSessionHasErrors('ville_ids');
        $this->assertSame(0, StandardTarif::count());
    }

    public function test_permissions_refusees_et_deleguees(): void
    {
        $this->actingAs($this->clientA)->get('/tarifs-standard')->assertForbidden();
        $livreur = $this->compte('livreur', UserRole::LIVREUR);
        $this->actingAs($livreur)->put('/tarifs-standard', ['lignes' => [['ville_id' => $this->temara->id, 'livraison' => '1', 'refus' => '0', 'retour' => '0']]])->assertForbidden();

        $lecteur = $this->delegue([Permission::TARIFS_STANDARD_VOIR->value]);
        $this->actingAs($lecteur)->get('/tarifs-standard')->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.update', false));
        $this->actingAs($lecteur)->post('/tarifs-standard', ['ville_ids' => [$this->temara->id], 'livraison' => '9', 'refus' => '0', 'retour' => '0', 'existants' => 'ignorer'])->assertForbidden();

        $gestionnaire = $this->delegue([Permission::TARIFS_STANDARD_VOIR->value, Permission::TARIFS_STANDARD_GERER->value]);
        $this->actingAs($gestionnaire)->post('/tarifs-standard', ['ville_ids' => [$this->temara->id], 'livraison' => '9', 'refus' => '0', 'retour' => '0', 'existants' => 'ignorer'])->assertSessionHasNoErrors();
        $this->assertSame(1, StandardTarif::count());
    }

    public function test_modification_du_standard_ne_touche_aucun_colis(): void
    {
        $this->standard($this->temara, '20');
        $this->actingAs($this->admin)->post('/colis', [
            'client_id' => $this->clientA->id, 'destinataire_nom' => 'S', 'destinataire_telephone' => '0661',
            'destinataire_ville_id' => $this->temara->id, 'prix_brut' => 100,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->put('/tarifs-standard', ['lignes' => [['ville_id' => $this->temara->id, 'livraison' => '30', 'refus' => '0', 'retour' => '0']]]);

        $this->assertSame('20.00', \App\Models\Command::firstOrFail()->frais_livraison_client, 'Snapshot figé.');
    }
}
