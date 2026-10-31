<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Tarifs\Clients\Services\ClientTarifEcriture;
use App\Domain\Tarifs\Commun\Enums\TypeTarif;
use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Models\ClientTarif;
use App\Models\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientTarifEcritureTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    private ClientTarifEcriture $ecriture;

    private array $m15 = ['livraison' => '15', 'refus' => '0', 'retour' => '0'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
        $this->ecriture = app(ClientTarifEcriture::class);
    }

    public function test_creation_individuelle_et_multiple(): void
    {
        $r = $this->ecriture->ajouter($this->clientA->id, [$this->temara->id], null, $this->m15);
        $this->assertSame(1, $r['crees']);

        $r = $this->ecriture->ajouter($this->clientA->id, [$this->skhirate->id, $this->casablanca->id], null, ['livraison' => '30.5', 'refus' => '5', 'retour' => '5']);
        $this->assertSame(2, $r['crees']);
        $this->assertSame('30.50', ClientTarif::pour($this->clientA->id, $this->casablanca->id)->first()->frais_livraison);
        $this->assertSame(3, ClientTarif::count());
    }

    public function test_creation_par_zone_entiere(): void
    {
        $r = $this->ecriture->ajouter($this->clientA->id, [], $this->zoneRabat->id, $this->m15);

        $this->assertSame(['crees' => 2, 'mises_a_jour' => 0, 'restaurees' => 0, 'ignorees' => 0, 'villes' => 2], $r);
        $this->assertEqualsCanonicalizing([$this->temara->id, $this->skhirate->id], ClientTarif::pluck('ville_id')->all());
    }

    public function test_doublon_ignore_par_defaut_jamais_ecrase(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');

        $r = $this->ecriture->ajouter($this->clientA->id, [$this->temara->id, $this->skhirate->id], null, ['livraison' => '99', 'refus' => '0', 'retour' => '0']);

        $this->assertSame(1, $r['ignorees']);
        $this->assertSame(1, $r['crees']);
        $this->assertSame('15.00', ClientTarif::pour($this->clientA->id, $this->temara->id)->first()->frais_livraison);
        $this->assertSame(1, ClientTarif::withTrashed()->where('client_id', $this->clientA->id)->where('ville_id', $this->temara->id)->count());
    }

    public function test_mise_a_jour_explicite_des_existants(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');

        $r = $this->ecriture->ajouter($this->clientA->id, [$this->temara->id], null, ['livraison' => '18', 'refus' => '2', 'retour' => '3'], ClientTarifEcriture::EXISTANTS_METTRE_A_JOUR);

        $this->assertSame(1, $r['mises_a_jour']);
        $t = ClientTarif::pour($this->clientA->id, $this->temara->id)->first();
        $this->assertSame(['18.00', '2.00', '3.00'], [$t->frais_livraison, $t->frais_refus, $t->frais_retour]);
    }

    public function test_cycle_actif_supprime_restaure_sans_doublon(): void
    {
        $t = $this->tarif($this->clientA, $this->temara, '15');
        $this->ecriture->supprimerLot($this->clientA->id, [$t->id]);
        $this->assertSoftDeleted($t);

        // Restauration refusée → ignorée, aucune ligne créée.
        $r = $this->ecriture->ajouter($this->clientA->id, [$this->temara->id], null, $this->m15, restaurer: false);
        $this->assertSame(1, $r['ignorees']);
        $this->assertSoftDeleted($t);

        $r = $this->ecriture->ajouter($this->clientA->id, [$this->temara->id], null, ['livraison' => '17', 'refus' => '0', 'retour' => '0']);
        $this->assertSame(1, $r['restaurees']);
        $this->assertSame(1, ClientTarif::withTrashed()->count());
        $this->assertSame('17.00', $t->fresh()->frais_livraison);
        $this->assertNull($t->fresh()->deleted_at);
    }

    public function test_suppression_logique_ne_modifie_aucun_colis(): void
    {
        $t = $this->tarif($this->clientA, $this->temara, '15');
        $colis = Command::create([
            'code' => 'OUA-HIST0001', 'client_id' => $this->clientA->id, 'destinataire_nom' => 'X', 'destinataire_telephone' => '0600000000',
            'destinataire_ville' => 'Temara', 'destinataire_ville_id' => $this->temara->id, 'frais_livraison_client' => '15.00',
            'type_tarif_client' => 'client', 'state' => 'Livré', 'state_group' => 'livre',
        ]);
        $avant = $colis->fresh()->updated_at;

        $this->ecriture->supprimerLot($this->clientA->id, [$t->id]);

        $this->assertSame('15.00', $colis->fresh()->frais_livraison_client);
        $this->assertSame('client', $colis->fresh()->type_tarif_client);
        $this->assertEquals($avant, $colis->fresh()->updated_at);
        $this->assertSame(TypeTarif::Missing, app(FeeResolutionService::class)->clientFees($this->clientA->id, $this->temara->id)->type);
    }

    public function test_modification_en_lot_limitee_aux_lignes_choisies(): void
    {
        $a = $this->tarif($this->clientA, $this->temara, '15');
        $b = $this->tarif($this->clientA, $this->skhirate, '20');

        $n = $this->ecriture->modifierLot($this->clientA->id, [['id' => $a->id, 'livraison' => '0', 'refus' => '0', 'retour' => '0']]);

        $this->assertSame(1, $n);
        $this->assertSame('0.00', $a->fresh()->frais_livraison, 'Zéro accepté.');
        $this->assertSame('20.00', $b->fresh()->frais_livraison, 'Ligne non sélectionnée intacte.');
    }

    public function test_lot_transactionnel_aucune_ecriture_partielle(): void
    {
        $a = $this->tarif($this->clientA, $this->temara, '15');
        $autre = $this->tarif($this->clientB, $this->temara, '25');

        try {
            $this->ecriture->modifierLot($this->clientA->id, [
                ['id' => $a->id, 'livraison' => '11', 'refus' => '0', 'retour' => '0'],
                ['id' => $autre->id, 'livraison' => '1', 'refus' => '0', 'retour' => '0'],
            ]);
            $this->fail('Une ligne d\'un autre client devait être refusée.');
        } catch (ValidationException) {
        }

        $this->assertSame('15.00', $a->fresh()->frais_livraison);
        $this->assertSame('25.00', $autre->fresh()->frais_livraison);

        $this->expectException(ValidationException::class);
        $this->ecriture->supprimerLot($this->clientA->id, [$a->id, $autre->id]);
    }

    public function test_montant_negatif_ou_vide_refuse(): void
    {
        foreach ([['livraison' => '-1', 'refus' => '0', 'retour' => '0'], ['livraison' => '', 'refus' => '0', 'retour' => '0'], ['livraison' => '10', 'refus' => '0'], ['livraison' => '1.234', 'refus' => '0', 'retour' => '0']] as $m) {
            try {
                $this->ecriture->ajouter($this->clientA->id, [$this->temara->id], null, $m);
                $this->fail('Montant invalide accepté : '.json_encode($m));
            } catch (ValidationException) {
            }
        }

        $this->assertSame(0, ClientTarif::withTrashed()->count());
    }

    public function test_compte_non_client_refuse_meme_pour_l_administration(): void
    {
        $this->expectException(ValidationException::class);
        $this->ecriture->ajouter($this->admin->id, [$this->temara->id], null, $this->m15);
    }

    public function test_apercu_detecte_nouveau_actif_supprime_sans_ecrire(): void
    {
        $this->tarif($this->clientA, $this->temara, '15');
        $this->tarif($this->clientA, $this->skhirate, '20')->delete();

        $etat = $this->ecriture->apercu($this->clientA->id, [$this->temara->id, $this->skhirate->id, $this->casablanca->id]);

        $this->assertSame('actif', $etat[$this->temara->id]['statut']);
        $this->assertSame('supprime', $etat[$this->skhirate->id]['statut']);
        $this->assertSame('nouveau', $etat[$this->casablanca->id]['statut']);
        $this->assertSame(2, ClientTarif::withTrashed()->count());
    }
}
