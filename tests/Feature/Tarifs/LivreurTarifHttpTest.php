<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Enums\UserRole;
use App\Models\LivreurStandardTarif;
use App\Models\LivreurTarif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Page « Tarifs livreurs » (/tarifs-livreurs) : commissions spécifiques et barème standard livreur. */
class LivreurTarifHttpTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    private User $hamza;
    private User $jalal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
        $this->hamza = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Hamza temara']);
        $this->jalal = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Jalal livreur']);
    }

    private function commission(User $l, $ville, string $liv, string $refus = '0'): LivreurTarif
    {
        return LivreurTarif::create(['livreur_id' => $l->id, 'ville_id' => $ville->id, 'commission_livraison' => $liv, 'commission_refus' => $refus]);
    }

    public function test_selecteur_kpi_et_grille_du_livreur_avec_comparaison(): void
    {
        LivreurStandardTarif::create(['ville_id' => $this->temara->id, 'commission_livraison' => '10', 'commission_refus' => '0']);
        $this->commission($this->hamza, $this->temara, '12');
        $this->commission($this->hamza, $this->casablanca, '0');

        $this->actingAs($this->admin)->get('/tarifs-livreurs?livreur_id='.$this->hamza->id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('TarifsLivreurs/Index')
                ->has('livreurs', 2)
                ->where('livreurs.0.nom', 'Hamza temara')
                ->where('livreurs.0.nb_villes', 2)
                ->where('stats.livreurs', 1)
                ->where('stats.regles', 2)
                ->where('stats.villes', 2)
                ->where('stats.moyenne', '6.00')
                ->where('stats.villes_sans_standard', 2)
                ->has('grille.zones', 2)
                ->where('grille.zones.0.lignes.0.ville_nom', 'Casablanca')
                ->where('grille.zones.0.lignes.0.zero', true)
                ->where('grille.zones.0.lignes.0.comparaison', 'indisponible')
                ->where('grille.zones.1.lignes.0.ecart', '+2.00')
                ->where('grille.zones.1.lignes.0.comparaison', 'superieur'));
    }

    public function test_filtre_zone_sur_la_grille(): void
    {
        $this->commission($this->hamza, $this->temara, '12');
        $this->commission($this->hamza, $this->casablanca, '15');

        $this->actingAs($this->admin)->get('/tarifs-livreurs?livreur_id='.$this->hamza->id.'&zone_id='.$this->zoneCasa->id)
            ->assertInertia(fn (Assert $p) => $p->has('grille.zones', 1)->where('grille.zones.0.zone_code', 'CS'));
    }

    public function test_affectation_sans_ecrasement_puis_mise_a_jour_explicite(): void
    {
        $this->commission($this->hamza, $this->temara, '12');
        $base = ['livreur_id' => $this->hamza->id, 'ville_ids' => [$this->temara->id, $this->skhirate->id], 'livraison' => '11', 'refus' => '0', 'restaurer' => true];

        $this->actingAs($this->admin)->postJson('/tarifs-livreurs/apercu', $base)->assertOk()
            ->assertJsonPath('compte.actif', 1)->assertJsonPath('compte.nouveau', 1);

        $this->actingAs($this->admin)->post('/tarifs-livreurs', $base + ['existants' => 'ignorer'])->assertSessionHasNoErrors();
        $this->assertSame('12.00', LivreurTarif::pour($this->hamza->id, $this->temara->id)->value('commission_livraison'));
        $this->assertSame('11.00', LivreurTarif::pour($this->hamza->id, $this->skhirate->id)->value('commission_livraison'));

        $this->actingAs($this->admin)->post('/tarifs-livreurs', $base + ['existants' => 'mettre_a_jour'])->assertSessionHasNoErrors();
        $this->assertSame('11.00', LivreurTarif::pour($this->hamza->id, $this->temara->id)->value('commission_livraison'));
        $this->assertSame(2, LivreurTarif::withTrashed()->count(), 'Aucun doublon.');
    }

    public function test_modification_en_lot_suppression_et_restauration(): void
    {
        $a = $this->commission($this->hamza, $this->temara, '12');
        $b = $this->commission($this->hamza, $this->skhirate, '10');

        $this->actingAs($this->admin)->put('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'lignes' => [
            ['id' => $a->id, 'livraison' => '13', 'refus' => '2'],
            ['id' => $b->id, 'livraison' => '0', 'refus' => '0'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['13.00', '0.00'], [$a->fresh()->commission_livraison, $b->fresh()->commission_livraison]);

        $this->actingAs($this->admin)->delete('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->assertSoftDeleted('livreur_tarifs', ['id' => $b->id]);
        $this->actingAs($this->admin)->get('/tarifs-livreurs?livreur_id='.$this->hamza->id)
            ->assertInertia(fn (Assert $p) => $p->has('grille.supprimees', 1)->where('grille.supprimees.0.id', $b->id));

        $this->actingAs($this->admin)->post('/tarifs-livreurs/restaurer', ['livreur_id' => $this->hamza->id, 'ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->assertNull($b->fresh()->deleted_at);

        // Réaffectation d'une ville supprimée : restaurée, jamais doublée.
        $b->delete();
        $this->actingAs($this->admin)->post('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'ville_ids' => [$this->skhirate->id], 'livraison' => '9', 'refus' => '0', 'existants' => 'ignorer', 'restaurer' => true]);
        $this->assertSame('9.00', $b->fresh()->commission_livraison);
        $this->assertSame(2, LivreurTarif::withTrashed()->count());
    }

    public function test_une_commission_d_un_autre_livreur_est_refusee(): void
    {
        $a = $this->commission($this->hamza, $this->temara, '12');

        $this->actingAs($this->admin)->put('/tarifs-livreurs', ['livreur_id' => $this->jalal->id, 'lignes' => [['id' => $a->id, 'livraison' => '1', 'refus' => '0']]])
            ->assertSessionHasErrors('lignes');
        $this->assertSame('12.00', $a->fresh()->commission_livraison);
    }

    public function test_montants_invalides_et_compte_non_livreur(): void
    {
        $this->actingAs($this->admin)->post('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'ville_ids' => [$this->temara->id], 'livraison' => '-1', 'refus' => '', 'existants' => 'ignorer', 'restaurer' => true])
            ->assertSessionHasErrors(['livraison', 'refus']);
        $this->actingAs($this->admin)->post('/tarifs-livreurs', ['livreur_id' => $this->clientA->id, 'ville_ids' => [$this->temara->id], 'livraison' => '10', 'refus' => '0', 'existants' => 'ignorer', 'restaurer' => true])
            ->assertSessionHasErrors('livreur_id');
        $this->assertSame(0, LivreurTarif::count());
    }

    public function test_bareme_standard_livreur_onglet_lot_et_zone(): void
    {
        $this->commission($this->hamza, $this->temara, '12');

        $this->actingAs($this->admin)->put('/tarifs-livreurs/standard', ['lignes' => [['ville_id' => $this->temara->id, 'livraison' => '10', 'refus' => '0']]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/tarifs-livreurs/standard', ['zone_id' => $this->zoneRabat->id, 'livraison' => '11', 'refus' => '0', 'existants' => 'ignorer'])->assertSessionHasNoErrors();

        $this->assertSame('10.00', LivreurStandardTarif::where('ville_id', $this->temara->id)->value('commission_livraison'), 'Existant non écrasé.');
        $this->assertSame('11.00', LivreurStandardTarif::where('ville_id', $this->skhirate->id)->value('commission_livraison'));
        $this->assertSame(0, \App\Models\StandardTarif::count(), 'Le barème client n\'est jamais touché.');

        $this->actingAs($this->admin)->get('/tarifs-livreurs?onglet=standard&statut=tarifees')->assertInertia(fn (Assert $p) => $p
            ->has('bareme.data', 2)
            ->where('bareme.data.0.ville_nom', 'Skhirate')
            ->where('bareme.data.1.nb_specifiques', 1)
            ->where('baremeStats.tarifees', 2));
    }

    public function test_permissions_livreur_client_et_delegation(): void
    {
        $this->actingAs($this->clientA)->get('/tarifs-livreurs')->assertForbidden();
        $this->actingAs($this->hamza)->get('/tarifs-livreurs')->assertForbidden();

        // Même avec une permission déléguée par erreur, un livreur ne modifie jamais une commission.
        $this->hamza->givePermissionTo([Permission::TARIFS_LIVREUR_VOIR->value, Permission::TARIFS_LIVREUR_CREER->value]);
        $this->actingAs($this->hamza)->post('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'ville_ids' => [$this->temara->id], 'livraison' => '50', 'refus' => '0', 'existants' => 'ignorer', 'restaurer' => true])
            ->assertForbidden();

        $lecteur = $this->delegue([Permission::TARIFS_LIVREUR_VOIR->value]);
        $this->actingAs($lecteur)->get('/tarifs-livreurs')->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.create', false)->where('can.standard', false));
        $this->actingAs($lecteur)->put('/tarifs-livreurs/standard', ['lignes' => [['ville_id' => $this->temara->id, 'livraison' => '1', 'refus' => '0']]])->assertForbidden();
        $this->assertSame(0, LivreurTarif::count() + LivreurStandardTarif::count());
    }
}
