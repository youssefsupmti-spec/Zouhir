<?php

namespace Tests\Feature\Reseau;

use App\Models\Command;
use App\Models\DeliveryPartner;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneCouverture;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** reseau:affecter-couverture : affectation déduite de l'historique, jamais devinée ni écrasée. */
class AffectationCouvertureTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function livreur(string $nom, bool $actif = true): User
    {
        $u = User::factory()->create(['nom_complet' => $nom, 'role' => 'livreur', 'active' => $actif]);
        $u->assignRole('livreur');

        return $u;
    }

    /** Crée $n colis livrés dans la zone, $parJour par jour à partir du 01/10/2026. */
    private function livres(Zone $z, ?User $l, int $n, int $parJour = 1, ?int $partenaire = null): void
    {
        for ($i = 0; $i < $n; $i++) {
            $date = Carbon::parse('2026-10-01 10:00')->addDays(intdiv($i, $parJour));
            $cmd = Command::create(['code' => 'OEX-A'.(++$this->seq), 'destinataire_zone_id' => $z->id, 'livreur_id' => $l?->id, 'delivery_partner_id' => $partenaire,
                'state' => 'Livré', 'state_group' => 'livre', 'livre_at' => $date, 'created_at' => $date, 'prix_brut' => 100, 'destinataire_nom' => 'Client test',
                'destinataire_telephone' => '0661000000', 'destinataire_adresse' => 'Adresse', 'destinataire_ville' => 'Ville']);
            $cmd->forceFill(['created_at' => $date])->saveQuietly();
        }
    }

    public function test_simulation_puis_application(): void
    {
        $tem = Zone::create(['name' => 'Témara', 'code' => 'TM', 'type' => 'locale']);
        $sale = Zone::create(['name' => 'Salé', 'code' => 'SALE', 'type' => 'locale']);
        $casa = Zone::create(['name' => 'Casablanca', 'code' => 'CASA', 'type' => 'nationale']);
        $ukn = Zone::create(['name' => 'Uknow-zone', 'code' => 'UKN', 'type' => 'nationale']);
        $manuelle = Zone::create(['name' => 'Manuelle', 'code' => 'MAN', 'type' => 'locale']);

        $a = $this->livreur('Premier');
        $b = $this->livreur('Deuxième');
        $c = $this->livreur('Sous le seuil');
        $inactif = $this->livreur('Inactif', false);
        $compte = $this->livreur('Compte partenaire');
        $part = DeliveryPartner::create(['code' => 'PART-1', 'name' => 'Partenaire', 'user_id' => $compte->id, 'is_active' => true]);

        $this->livres($tem, $a, 30, 3);          // 10 jours à 3 colis
        $this->livres($tem, $b, 12, 12);         // 1 jour à 12 colis (s'ajoute aux 3 du 01/10 : 15)
        $this->livres($tem, $c, 5);
        $this->livres($tem, $inactif, 50, 5);    // plus gros volume mais inactif : exclu
        $this->livres($sale, $c, 9);             // sous le seuil : Salé reste non couverte
        $this->livres($casa, $compte, 20, 2, $part->id);
        $this->livres($ukn, $a, 20, 2);
        $this->livres($manuelle, $a, 20, 2);
        ZoneCouverture::create(['zone_id' => $manuelle->id, 'type_couverture' => 'regional_flotte', 'titulaire_user_id' => $b->id, 'statut_couverture' => 'normal']);

        // Simulation : aucune écriture.
        $this->artisan('reseau:affecter-couverture')->assertSuccessful();
        $this->assertSame(0, ZoneCouverture::query()->whereNotNull('capacite_journaliere')->count());

        $this->artisan('reseau:affecter-couverture', ['--apply' => true])->assertSuccessful();

        $t = ZoneCouverture::query()->where('zone_id', $tem->id)->first();
        $this->assertSame($a->id, (int) $t->titulaire_user_id);
        $this->assertSame($b->id, (int) $t->backup_user_id);
        $this->assertSame('optimal', $t->statut_couverture);
        // Jours : 10 jours (3 colis, 15 le premier jour avec B, + inactif 5/j sur 10 j, + C 1/j sur 5 j) ; 90e centile = 9e valeur triée.
        $this->assertGreaterThan(0, (int) $t->capacite_journaliere);
        $this->assertDatabaseHas('livreur_zones', ['user_id' => $a->id, 'zone_id' => $tem->id, 'zone_type' => 'livraison']);
        $this->assertDatabaseHas('livreur_zones', ['user_id' => $b->id, 'zone_id' => $tem->id]);

        $this->assertSame('non_couvert', ZoneCouverture::query()->where('zone_id', $sale->id)->value('statut_couverture'));

        $cs = ZoneCouverture::query()->where('zone_id', $casa->id)->first();
        $this->assertSame($part->id, (int) $cs->delivery_partner_id);
        $this->assertSame('normal', $cs->statut_couverture);
        $this->assertDatabaseHas('delivery_partner_zones', ['delivery_partner_id' => $part->id, 'zone_id' => $casa->id]);

        $u = ZoneCouverture::query()->where('zone_id', $ukn->id)->first();
        $this->assertNull($u->titulaire_user_id);
        $this->assertNull($u->delivery_partner_id);

        // Saisie manuelle conservée.
        $m = ZoneCouverture::query()->where('zone_id', $manuelle->id)->first();
        $this->assertSame($b->id, (int) $m->titulaire_user_id);
        $this->assertNull($m->capacite_journaliere);

        // Idempotent : une seconde passe ne change rien.
        $avant = ZoneCouverture::query()->orderBy('id')->get()->toArray();
        $this->artisan('reseau:affecter-couverture', ['--apply' => true])->assertSuccessful();
        $this->assertEquals($avant, ZoneCouverture::query()->orderBy('id')->get()->toArray());
    }

    public function test_capacite_est_le_90e_centile_journalier(): void
    {
        $z = Zone::create(['name' => 'Zone', 'code' => 'Z1', 'type' => 'locale']);
        $l = $this->livreur('Seul');
        // 10 jours : 1,2,...,10 colis -> 90e centile = 9.
        for ($j = 1; $j <= 10; $j++) {
            for ($k = 0; $k < $j; $k++) {
                $date = Carbon::parse('2026-10-01 10:00')->addDays($j);
                Command::create(['code' => 'OEX-C'.(++$this->seq), 'destinataire_zone_id' => $z->id, 'livreur_id' => $l->id, 'state' => 'Livré', 'state_group' => 'livre',
                    'livre_at' => $date, 'prix_brut' => 100, 'destinataire_nom' => 'C', 'destinataire_telephone' => '0661000000', 'destinataire_adresse' => 'A', 'destinataire_ville' => 'V']);
            }
        }
        $this->artisan('reseau:affecter-couverture', ['--apply' => true])->assertSuccessful();
        $c = ZoneCouverture::query()->where('zone_id', $z->id)->first();
        $this->assertSame(9, (int) $c->capacite_journaliere);
        $this->assertSame('normal', $c->statut_couverture);
        $this->assertNull($c->backup_user_id);
    }

    public function test_apply_refuse_en_production_sans_force(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('reseau:affecter-couverture', ['--apply' => true])->assertFailed();
    }
}
