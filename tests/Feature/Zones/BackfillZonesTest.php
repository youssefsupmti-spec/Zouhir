<?php

namespace Tests\Feature\Zones;

use App\Application\Zones\Backfill\DecoupageZones;
use App\Domain\Zones\Support\ReferentielZones;
use App\Models\Command;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillZonesTest extends TestCase
{
    use RefreshDatabase;

    /** Reproduit l'état legacy : 6 macro-zones, villes en MAJUSCULES et libellés « ville-region ». */
    private function legacy(): void
    {
        $z = [];
        foreach (['RBT', 'CAS', 'NORD', 'CENTRE', 'SUD', 'ORIENTAL'] as $code) {
            // Noms réels de production : CAS et ORIENTAL portent déjà les noms des nouvelles zones.
            $nom = ['CAS' => 'Zone Grand Casablanca', 'ORIENTAL' => 'Zone Oriental'][$code] ?? "Legacy $code";
            $z[$code] = Zone::create(['name' => $nom, 'code' => $code])->id;
        }
        foreach ([
            ['TEMARA', 'RBT'], ['SKHIRATE', 'RBT'], ['Skhirat', 'RBT'], ['AIN EL AOUDA', 'RBT'], ['SIDI YAHIA ZAERS', 'RBT'],
            ['BOUZNIKA', 'RBT'], ['RABAT', 'RBT'], ['SALE', 'CENTRE'], ['BOUKNADEL', 'CENTRE'], ['MARRAKECH', 'CENTRE'],
            ['Ait melloul-agadir', 'CENTRE'], ['Tinghir-errachidia', 'CENTRE'], ['ASILAH', 'NORD'], ['Martil', 'NORD'],
            ['Douar Inconnu', 'CENTRE'], ['Smara', 'SUD'], ['Village du sud', 'SUD'], ['CASABLANCA', 'CAS'],
        ] as [$nom, $code]) {
            DB::table('villes')->insert(['name_fr' => $nom, 'zone_id' => $z[$code], 'code_zone' => $code, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('villes')->where('name_fr', 'Douar Inconnu')->update(['deleted_at' => now()]);
    }

    private function zoneDe(string $nom): ?string
    {
        return Ville::withTrashed()->where('name_fr', $nom)->first()?->code_zone;
    }

    public function test_regles_de_classement(): void
    {
        $this->assertSame(['code' => 'TM', 'regle' => 'nom'], ReferentielZones::zonePour('TEMARA', 'RBT'));
        $this->assertSame('SKH', ReferentielZones::zonePour('Skhirat', 'RBT')['code']);
        $this->assertSame('KECH', ReferentielZones::zonePour('Tahanaout-marrakeche', 'CENTRE')['code']);
        $this->assertSame(['code' => 'BM', 'regle' => 'suffixe'], ReferentielZones::zonePour('Boumia-errachidia', 'CENTRE'));
        $this->assertSame(['code' => 'TG', 'regle' => 'ancienne_zone'], ReferentielZones::zonePour('Martil Plage', 'NORD'));
        $this->assertSame(['code' => null, 'regle' => 'sans_zone'], ReferentielZones::zonePour('Douar Inconnu', 'CENTRE'));
        $this->assertSame(['code' => null, 'regle' => 'sans_zone'], ReferentielZones::zonePour('Ville RBT inconnue', 'RBT'));
    }

    public function test_simulation_n_ecrit_rien(): void
    {
        $this->legacy();
        $avant = DB::table('villes')->orderBy('id')->get()->toArray();

        $this->artisan('zones:backfill')->assertSuccessful();

        $this->assertSame(6, Zone::count());
        $this->assertEquals($avant, DB::table('villes')->orderBy('id')->get()->toArray());
    }

    public function test_application_idempotente_sans_toucher_les_colis(): void
    {
        $this->legacy();
        $colis = Command::create(['code' => 'CZ1', 'client_id' => \App\Models\User::factory()->create()->id, 'destinataire_nom' => 'X', 'destinataire_telephone' => '0600000000', 'destinataire_ville' => 'Temara', 'prix_brut' => 10, 'state' => 'Nouveau', 'state_group' => 'nouveau', 'destinataire_zone_code' => 'RBT']);

        $this->artisan('zones:backfill --apply')->assertSuccessful();

        $this->assertSame(19, Zone::count(), '10 zones locales + 9 nationales actives');
        $this->assertSame(6, Zone::onlyTrashed()->count(), 'Les 6 macro-zones legacy vidées puis archivées');
        $this->assertSame(10, Zone::where('type', 'locale')->count());
        $this->assertSame('Zone Grand Casablanca', Zone::where('code', 'CASA')->value('name'));
        $this->assertSame('Zone Oriental (ancienne ORIENTAL)', Zone::onlyTrashed()->where('code', 'ORIENTAL')->value('name'));
        foreach ([
            'TEMARA' => 'TM', 'SKHIRATE' => 'SKH', 'Skhirat' => 'SKH', 'AIN EL AOUDA' => 'AOU', 'SIDI YAHIA ZAERS' => 'SYZ',
            'BOUZNIKA' => 'CASA', 'RABAT' => 'RB', 'SALE' => 'SALE', 'BOUKNADEL' => 'SALE', 'MARRAKECH' => 'KECH',
            'Ait melloul-agadir' => 'AG', 'Tinghir-errachidia' => 'BM', 'ASILAH' => 'TG', 'Martil' => 'TG',
            'Smara' => 'AG', 'Village du sud' => 'AG', 'CASABLANCA' => 'CASA', 'Douar Inconnu' => null,
        ] as $nom => $code) {
            $this->assertSame($code, $this->zoneDe($nom), $nom);
        }
        $this->assertSame(0, DB::table('villes')->join('zones', 'zones.id', '=', 'villes.zone_id')->whereColumn('villes.code_zone', '!=', 'zones.code')->count());
        $this->assertSame('RBT', $colis->fresh()->destinataire_zone_code, 'Les colis ne sont jamais réécrits.');

        // Un classement manuel est respecté, et une 2e exécution n'a plus rien à faire.
        Ville::where('name_fr', 'MARRAKECH')->first()->update(['zone_id' => Zone::where('code', 'AG')->value('id')]);
        $analyse = app(DecoupageZones::class)->analyser();
        $this->assertSame([], $analyse['zones_a_creer']);
        $this->assertSame([], $analyse['anciennes_a_archiver']);
        $this->assertCount(1, $analyse['villes'], 'Seule la ville sans zone reste à classer');

        $this->artisan('zones:backfill --apply')->assertSuccessful();
        $this->assertSame('AG', $this->zoneDe('MARRAKECH'));
        $this->assertSame(19, Zone::count());
    }

    public function test_apply_refuse_en_production_sans_force(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('zones:backfill --apply')->assertFailed();
    }
}
