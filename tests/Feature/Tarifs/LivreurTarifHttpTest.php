<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Enums\UserRole;
use App\Models\LivreurTarif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Page « Tarifs livreurs » (/tarifs-livreurs) : commissions spécifiques (pas de barème standard, comme WinningExpress). */
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

    public function test_selecteur_kpi_et_grille_du_livreur(): void
    {
        $this->commission($this->hamza, $this->temara, '12');
        $this->commission($this->hamza, $this->casablanca, '0');

        // Compte archivé : ses commissions restent dans le total (comme les 148 du legacy).
        $archive = $this->compte('livreur', UserRole::LIVREUR, ['nom_complet' => 'Ancien livreur']);
        $this->commission($archive, $this->skhirate, '10');
        $archive->delete();
        $this->commission($this->jalal, $this->temara, '9')->delete();

        $this->actingAs($this->admin)->get('/tarifs-livreurs')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('TarifsLivreurs/Index')
                ->where('stats.commissions', 3)
                ->where('stats.livreurs', 2)
                ->where('stats.actifs', 1)
                ->where('stats.archives', 1)
                ->where('stats.villes', 3)
                ->where('stats.zero', 1)
                ->where('stats.corbeille', 1)
                ->where('stats.sans_commission', 1)
                ->has('livreurs.data', 2)
                ->where('livreurs.data.0.nom', 'Hamza temara')
                ->where('livreurs.data.0.nb_villes', 2)
                ->where('livreurs.data.0.min', '0.00')
                ->where('livreurs.data.0.max', '12.00')
                ->where('livreurs.data.1.nom', 'Ancien livreur')
                ->where('livreurs.data.1.archive', true)
                ->where('corbeille', null));

        $this->actingAs($this->admin)->get('/tarifs-livreurs?statut=actifs')
            ->assertInertia(fn (Assert $p) => $p->has('livreurs.data', 2)->where('livreurs.data.1.nom', 'Jalal livreur')->where('livreurs.data.1.nb_villes', 0));
        $this->actingAs($this->admin)->get('/tarifs-livreurs?statut=archives')
            ->assertInertia(fn (Assert $p) => $p->has('livreurs.data', 1)->where('livreurs.data.0.archive', true));
        $this->actingAs($this->admin)->get('/tarifs-livreurs?statut=corbeille')
            ->assertInertia(fn (Assert $p) => $p->where('livreurs', null)->has('corbeille', 1)->where('corbeille.0.titulaire', 'Jalal livreur')->where('corbeille.0.montants', ['9.00', '0.00']));

        $this->actingAs($this->admin)->getJson('/tarifs-livreurs/'.$this->hamza->id.'/grille')->assertOk()
            ->assertJsonCount(2, 'zones')
            ->assertJsonPath('zones.0.lignes.0.ville_nom', 'Casablanca')
            ->assertJsonPath('zones.0.lignes.0.zero', true)
            ->assertJsonPath('zones.1.lignes.0.livraison', '12.00');
        $this->actingAs($this->admin)->getJson('/tarifs-livreurs/'.$archive->id.'/grille')->assertOk()->assertJsonCount(1, 'zones');
    }

    public function test_filtre_zone_sur_la_grille(): void
    {
        $this->commission($this->hamza, $this->temara, '12');
        $this->commission($this->hamza, $this->casablanca, '15');

        $this->actingAs($this->admin)->getJson('/tarifs-livreurs/'.$this->hamza->id.'/grille?zone_id='.$this->zoneCasa->id)
            ->assertJsonCount(1, 'zones')->assertJsonPath('zones.0.zone_code', 'CS');
        $this->actingAs($this->admin)->get('/tarifs-livreurs?zone_id='.$this->zoneRabat->id)
            ->assertInertia(fn (Assert $p) => $p->has('livreurs.data', 1));
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
        $this->actingAs($this->admin)->getJson('/tarifs-livreurs/'.$this->hamza->id.'/grille')->assertJsonCount(1, 'zones.0.lignes');

        $this->actingAs($this->admin)->post('/tarifs-livreurs/restaurer', ['livreur_id' => $this->hamza->id, 'ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->assertNull($b->fresh()->deleted_at);

        // Réaffectation d'une ville supprimée : restaurée, jamais doublée.
        $b->delete();
        $this->actingAs($this->admin)->post('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'ville_ids' => [$this->skhirate->id], 'livraison' => '9', 'refus' => '0', 'existants' => 'ignorer', 'restaurer' => true]);
        $this->assertSame('9.00', $b->fresh()->commission_livraison);
        $this->assertSame(2, LivreurTarif::withTrashed()->count());
    }

    public function test_corbeille_restauration_et_suppression_definitive(): void
    {
        $a = $this->commission($this->hamza, $this->temara, '12');
        $b = $this->commission($this->jalal, $this->skhirate, '10');
        $actif = $this->commission($this->jalal, $this->temara, '11');
        $a->delete();
        $b->delete();

        // Une commission ACTIVE ne peut jamais être supprimée définitivement.
        $this->actingAs($this->admin)->delete('/tarifs-livreurs/corbeille', ['ids' => [$actif->id]])->assertSessionHasErrors('ids');
        $this->assertNotNull($actif->fresh());

        $this->actingAs($this->admin)->post('/tarifs-livreurs/corbeille/restaurer', ['ids' => [$a->id]])->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->deleted_at);

        $this->actingAs($this->admin)->delete('/tarifs-livreurs/corbeille', ['ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->assertNull(LivreurTarif::withTrashed()->find($b->id), 'Supprimée définitivement.');
        $this->assertSame(2, LivreurTarif::count());
    }

    public function test_commissions_d_un_livreur_archive_restent_modifiables(): void
    {
        $t = $this->commission($this->hamza, $this->temara, '12');
        $this->hamza->delete();

        $this->actingAs($this->admin)->put('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'lignes' => [['id' => $t->id, 'livraison' => '14', 'refus' => '0']]])
            ->assertSessionHasNoErrors();
        $this->assertSame('14.00', $t->fresh()->commission_livraison);

        // Nouvelle affectation : réservée aux comptes actifs.
        $this->actingAs($this->admin)->post('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'ville_ids' => [$this->skhirate->id], 'livraison' => '9', 'refus' => '0', 'existants' => 'ignorer', 'restaurer' => true])
            ->assertSessionHasErrors('livreur_id');
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

    public function test_aucun_bareme_standard_livreur(): void
    {
        $this->actingAs($this->admin)->put('/tarifs-livreurs/standard', ['lignes' => [['ville_id' => $this->temara->id, 'livraison' => '10', 'refus' => '0']]])
            ->assertNotFound();
        $this->actingAs($this->admin)->post('/tarifs-livreurs/standard', ['zone_id' => $this->zoneRabat->id, 'livraison' => '11', 'refus' => '0', 'existants' => 'ignorer'])
            ->assertNotFound();
        $this->assertSame(0, \App\Models\StandardTarif::count(), 'Le barème client n\'est jamais touché.');
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
        $this->actingAs($lecteur)->get('/tarifs-livreurs')->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.create', false)->where('can.update', false));
        $this->actingAs($lecteur)->post('/tarifs-livreurs', ['livreur_id' => $this->hamza->id, 'ville_ids' => [$this->temara->id], 'livraison' => '5', 'refus' => '0', 'existants' => 'ignorer', 'restaurer' => true])->assertForbidden();
        $this->assertSame(0, LivreurTarif::count());
    }
}
