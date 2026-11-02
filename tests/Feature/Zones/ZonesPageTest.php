<?php

namespace Tests\Feature\Zones;

use App\Models\Command;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tarifs\TarifsFixtures;
use Tests\TestCase;

class ZonesPageTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
        $this->zoneRabat->update(['type' => 'locale']);
    }

    public function test_liste_compteurs_onglets_et_apercu(): void
    {
        Zone::create(['name' => 'Zone vide', 'code' => 'VID']);
        Ville::create(['name_fr' => 'Douar sans zone']);
        Zone::create(['name' => 'Ancienne', 'code' => 'OLD'])->delete();

        $this->actingAs($this->admin)->get('/zones')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Zones/Index')
            ->where('stats.zones', 3)
            ->where('stats.locales', 1)
            ->where('stats.nationales', 2)
            ->where('stats.vides', 1)
            ->where('stats.corbeille', 1)
            ->where('stats.villes', 4)
            ->where('stats.villes_couvertes', 3)
            ->where('stats.sans_zone', 1)
            ->has('zones.data', 3)
            ->where('zones.data.0.code', 'RSK') // locales d'abord
            ->where('zones.data.0.type', 'locale')
            ->where('zones.data.0.villes_count', 2)
            ->where('zones.data.0.apercu.0.nom', 'Skhirate')
            ->where('can.manage', true));

        $this->actingAs($this->admin)->get('/zones?onglet=vides')->assertInertia(fn (Assert $p) => $p->has('zones.data', 1)->where('zones.data.0.code', 'VID'));
        $this->actingAs($this->admin)->get('/zones?onglet=corbeille')->assertInertia(fn (Assert $p) => $p->has('zones.data', 1)->where('zones.data.0.code', 'OLD'));
        $this->actingAs($this->admin)->get('/zones?onglet=locale')->assertInertia(fn (Assert $p) => $p->has('zones.data', 1));
        // La recherche trouve une zone par le nom d'une de ses villes.
        $this->actingAs($this->admin)->get('/zones?search=casab')->assertInertia(fn (Assert $p) => $p->has('zones.data', 1)->where('zones.data.0.code', 'CS'));
    }

    public function test_acces_reserve_a_l_administration(): void
    {
        $this->actingAs($this->clientA)->get('/zones')->assertForbidden();
        $this->actingAs($this->clientA)->post('/zones', ['name' => 'X', 'code' => 'X', 'type' => 'locale'])->assertForbidden();
        $this->actingAs($this->clientA)->getJson("/zones/{$this->zoneRabat->id}/villes")->assertForbidden();
        $this->actingAs($this->clientA)->postJson("/zones/{$this->zoneRabat->id}/villes", ['ids' => [$this->casablanca->id]])->assertForbidden();
        $this->assertSame($this->zoneCasa->id, $this->casablanca->fresh()->zone_id);
    }

    public function test_creation_code_en_majuscules_et_unicite_corbeille_comprise(): void
    {
        $this->actingAs($this->admin)->post('/zones', ['name' => 'Zone Agadir', 'code' => ' ag ', 'type' => 'nationale'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('zones', ['code' => 'AG', 'type' => 'nationale']);

        Zone::where('code', 'AG')->first()->delete();
        $this->actingAs($this->admin)->post('/zones', ['name' => 'Autre', 'code' => 'AG', 'type' => 'locale'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/zones', ['name' => 'Zone Agadir', 'code' => 'AG2', 'type' => 'locale'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post('/zones', ['name' => 'Y', 'code' => 'A B', 'type' => 'locale'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/zones', ['name' => 'Y', 'code' => 'YY', 'type' => 'autre'])->assertSessionHasErrors('type');
    }

    public function test_changement_de_code_propage_aux_villes_mais_jamais_aux_colis(): void
    {
        $colis = Command::create(['code' => 'CZ1', 'client_id' => $this->clientA->id, 'destinataire_nom' => 'X', 'destinataire_telephone' => '0600000000', 'destinataire_ville' => 'Temara', 'prix_brut' => 10, 'state' => 'Nouveau', 'state_group' => 'nouveau', 'destinataire_ville_id' => $this->temara->id, 'destinataire_zone_code' => 'RSK']);

        $this->actingAs($this->admin)->put("/zones/{$this->zoneRabat->id}", ['name' => 'Zone Témara', 'code' => 'tm', 'type' => 'locale'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['TM', 'TM'], Ville::where('zone_id', $this->zoneRabat->id)->orderBy('id')->pluck('code_zone')->all());
        $this->assertSame('RSK', $colis->fresh()->destinataire_zone_code, 'Le code de zone du colis est figé.');
    }

    public function test_transfert_de_villes_entre_panneaux(): void
    {
        $douar = Ville::create(['name_fr' => 'Douar Lhjar', 'name_ar' => 'دوار الحجر']);
        $url = "/zones/{$this->zoneRabat->id}/villes";

        $this->actingAs($this->admin)->getJson("$url?panneau=disponibles")->assertOk()->assertJsonPath('total', 2);
        $this->actingAs($this->admin)->getJson("$url?panneau=disponibles&sans_zone=1")->assertJsonPath('total', 1)->assertJsonPath('data.0.zone_code', null);
        $this->actingAs($this->admin)->getJson("$url?panneau=disponibles&search=الحجر")->assertJsonPath('total', 1);
        $this->actingAs($this->admin)->getJson("$url?panneau=zone")->assertJsonPath('total', 2);

        // Ajout : une ville sans zone et une ville d'une autre zone (déplacée).
        $this->actingAs($this->admin)->postJson($url, ['ids' => [$douar->id, $this->casablanca->id]])->assertOk()->assertJsonPath('modifiees', 2);
        $this->assertSame('RSK', $douar->fresh()->code_zone);
        $this->assertSame($this->zoneRabat->id, $this->casablanca->fresh()->zone_id);
        $this->assertSame('RSK', $this->casablanca->fresh()->code_zone);

        // Retrait : seules les villes de CETTE zone sont touchées.
        $autre = Ville::create(['name_fr' => 'Settat', 'zone_id' => $this->zoneCasa->id]);
        $this->actingAs($this->admin)->deleteJson($url, ['ids' => [$douar->id, $autre->id]])->assertJsonPath('modifiees', 1);
        $this->assertNull($douar->fresh()->zone_id);
        $this->assertNull($douar->fresh()->code_zone);
        $this->assertSame($this->zoneCasa->id, $autre->fresh()->zone_id);

        $this->actingAs($this->admin)->postJson($url, ['ids' => []])->assertUnprocessable();
    }

    public function test_corbeille_detache_les_villes_puis_restauration(): void
    {
        $this->actingAs($this->admin)->delete("/zones/{$this->zoneRabat->id}")->assertSessionHasNoErrors();

        $this->assertSoftDeleted('zones', ['id' => $this->zoneRabat->id]);
        $this->assertNull($this->temara->fresh()->zone_id);
        $this->assertNotNull(Ville::find($this->temara->id), 'La ville n’est jamais supprimée.');

        $this->actingAs($this->admin)->post("/zones/{$this->zoneRabat->id}/restaurer")->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted('zones', ['id' => $this->zoneRabat->id]);
    }

    public function test_ville_code_zone_derive_et_ville_sans_zone(): void
    {
        $v = Ville::create(['name_fr' => 'Bouznika', 'zone_id' => $this->zoneCasa->id, 'code_zone' => 'FAUX']);
        $this->assertSame('CS', $v->code_zone);

        $this->actingAs($this->admin)->post('/villes', ['name_fr' => 'Douar Neuf'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('villes', ['name_fr' => 'Douar Neuf', 'zone_id' => null, 'code_zone' => null]);
    }

    public function test_page_edition_et_requetes_constantes(): void
    {
        $this->actingAs($this->admin)->get("/zones/{$this->zoneRabat->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Zones/Edit')->where('zone.code', 'RSK')->where('zone.type', 'locale')->where('stats.villes', 2));

        $mesurer = function (): int {
            $this->actingAs($this->admin)->get('/zones');
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get('/zones')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $avant = $mesurer();
        for ($i = 0; $i < 6; $i++) {
            $z = Zone::create(['name' => "Zone $i", 'code' => "Z$i"]);
            foreach (range(1, 8) as $j) {
                Ville::create(['name_fr' => "Ville $i-$j", 'zone_id' => $z->id]);
            }
        }
        $this->assertSame($avant, $mesurer(), 'Aucune requête N+1 sur la liste des zones.');
    }
}
