<?php

namespace Tests\Feature\Reprise;

use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copie miniature de l'ancienne base (SQLite en mémoire, connexion « legacy_test ») avec les cas
 * réels relevés en production, et comptes du nouveau projet repris avec les mêmes ids.
 */
trait LegacyFixture
{
    protected const T0 = 1762000000; // 01/11/2025

    protected function preparerLegacy(): void
    {
        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['reprise.connexion' => 'legacy_test', 'reprise.comptes_partenaires' => [76]]);
        $this->creerLegacy();

        // Référentiel du nouveau projet (comptes repris avec les mêmes ids).
        $zone = Zone::create(['name' => 'Zone Témara', 'code' => 'TEM']);
        Ville::create(['name_fr' => 'Temara', 'zone_id' => $zone->id, 'code_zone' => 'TEM']);
        Ville::create(['name_fr' => 'Ain attig', 'zone_id' => $zone->id, 'code_zone' => 'TEM']);
        Ville::create(['name_fr' => 'Casablanca']); // sans zone
        foreach ([[1, 'Admin', 'admin'], [45, 'Speedo', 'client'], [53, 'Mbarek livreur', 'livreur'], [76, 'Abdelkrim livreur', 'livreur'], [42, 'Agence temara', 'livreur']] as [$id, $nom, $role]) {
            User::factory()->create(['id' => $id, 'nom_complet' => $nom, 'role' => $role, 'email' => "u{$id}@example.test"]);
        }
        DB::table('businesses')->insert(['user_id' => 45, 'name' => 'Anouar', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function creerLegacy(): void
    {
        $s = Schema::connection('legacy_test');
        $s->create('trackingstates', function (Blueprint $t) {
            $t->integer('id');
            $t->string('state');
            $t->string('color')->nullable();
            $t->string('agent')->nullable();
            $t->string('kpi')->nullable();
            $t->integer('trash');
        });
        $s->create('users', function (Blueprint $t) {
            $t->integer('id');
            $t->string('fullname');
            $t->string('type');
        });
        $s->create('commands', function (Blueprint $t) {
            $t->integer('id');
            foreach (['code', 'product', 'qty', 'clientname', 'clientphone', 'fullname', 'phone', 'address', 'city', 'state', 'datereported', 'collected', 'invoiced', 'invoiceddlm', 'treated', 'dateadd', 'dateupdate'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['dlm', 'subdlm', 'client', 'package', 'archived', 'echange', 'openpackage', 'trash'] as $c) {
                $t->integer($c)->default(0);
            }
            $t->float('price')->default(0);
            $t->float('extrafees')->default(0);
            $t->text('note')->nullable();
        });
        $s->create('commandshistory', function (Blueprint $t) {
            $t->integer('id');
            $t->string('command');
            $t->string('state');
            $t->string('agent');
            $t->string('dateadd');
        });
        $s->create('bls', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('dlm');
            $t->integer('subdlm')->default(0);
            $t->integer('client');
            $t->string('code');
            $t->text('cmds');
            $t->string('done');
            $t->string('dateadd');
            $t->integer('trash');
        });

        $L = DB::connection('legacy_test');
        $L->table('trackingstates')->insert([
            ['id' => 1, 'state' => 'Livré', 'color' => '#008000', 'agent' => 'Modérateur,Livreur', 'kpi' => 'Livrées', 'trash' => 1],
            ['id' => 2, 'state' => 'Ramassé', 'color' => '#FFAA00', 'agent' => 'Modérateur', 'kpi' => 'En cours', 'trash' => 1],
            ['id' => 16, 'state' => 'Manque de stock', 'color' => '#CC0000', 'agent' => null, 'kpi' => 'Echouées', 'trash' => 0],
            ['id' => 17, 'state' => 'Ajouté', 'color' => '#e5dd80', 'agent' => 'Modérateur', 'kpi' => null, 'trash' => 1],
            ['id' => 18, 'state' => 'Manque de stock', 'color' => '#000000', 'agent' => 'Modérateur', 'kpi' => 'Echouées', 'trash' => 1],
            ['id' => 28, 'state' => 'Retour client reçu', 'color' => '#CC0000', 'agent' => null, 'kpi' => null, 'trash' => 1],
            ['id' => 31, 'state' => 'Bizarre statut', 'color' => null, 'agent' => null, 'kpi' => null, 'trash' => 1],
        ]);
        $L->table('users')->insert([
            ['id' => 1, 'fullname' => 'Admin', 'type' => 'moderator'], ['id' => 45, 'fullname' => 'Speedo', 'type' => 'client'],
            ['id' => 53, 'fullname' => 'Mbarek livreur', 'type' => 'dlm'], ['id' => 76, 'fullname' => 'Abdelkrim livreur', 'type' => 'dlm'],
            ['id' => 42, 'fullname' => 'Agence temara', 'type' => 'dlm'],
        ]);

        $colis = fn (int $id, array $v) => $v + [
            'id' => $id, 'code' => "OIS-{$id}", 'product' => 'Produit', 'qty' => '1', 'fullname' => 'Dest', 'phone' => '0600000000',
            'address' => 'Adresse', 'city' => 'Temara', 'state' => 'Livré', 'client' => 45, 'dlm' => 53, 'price' => 200,
            'collected' => 'on', 'invoiced' => 'off', 'invoiceddlm' => '', 'treated' => 'off', 'dateadd' => (string) self::T0,
            'dateupdate' => (string) (self::T0 + 7200), 'trash' => 1, 'note' => '', 'datereported' => '',
        ];
        foreach ([
            $colis(100, ['note' => '<b>Livreur: </b>Client pas chez lui<br /><b>Modérateur: </b>Rappeler<br />']),
            $colis(101, ['code' => 'CODE1', 'city' => '', 'qty' => '', 'state' => 'pas de réponse 3 fois']),
            $colis(102, ['code' => 'CODE1', 'city' => 'Ville Fantome', 'client' => 0, 'dlm' => 0, 'state' => 'Ajouté', 'qty' => '(L - XL) taille']),
            $colis(103, ['city' => 'ain atig', 'state' => 'Retour client reçu', 'trash' => 0, 'price' => 672499672, 'openpackage' => 260,
                'note' => '&lt;p&gt;&lt;strong&gt;Hicham Rabat: &lt;/strong&gt;Tri9 3in 3awda&lt;ins&gt;01/04/26 10:50&lt;/ins&gt;&lt;/p&gt;<b>Livreur: </b>Livré a ain aouda<br />']),
            $colis(104, ['city' => 'Casablanca', 'note' => 'client intéressé', 'datereported' => '-55766448000']),
        ] as $ligne) {
            $L->table('commands')->insert($ligne);
        }
        $L->table('commandshistory')->insert([
            ['id' => 1, 'command' => 'OIS-100', 'state' => 'Ajouté', 'agent' => 'Speedo', 'dateadd' => (string) self::T0],
            ['id' => 2, 'command' => 'OIS-100', 'state' => 'Ramassé', 'agent' => 'Mbarek livreur', 'dateadd' => (string) (self::T0 + 600)],
            ['id' => 4, 'command' => 'OIS-100', 'state' => 'Livré', 'agent' => 'Mbarek livreur', 'dateadd' => (string) (self::T0 + 3600)],
            ['id' => 3, 'command' => 'OIS-100', 'state' => 'Livré', 'agent' => 'Arbi region', 'dateadd' => (string) (self::T0 + 1800)],
            ['id' => 5, 'command' => 'CODE1', 'state' => 'Ajouté', 'agent' => 'Admin', 'dateadd' => (string) self::T0],
            ['id' => 6, 'command' => 'OIS-999', 'state' => 'Livré', 'agent' => 'Admin', 'dateadd' => (string) self::T0],
            ['id' => 7, 'command' => 'OIS-103', 'state' => 'Retour client reçu', 'agent' => 'Admin', 'dateadd' => (string) (self::T0 + 5000)],
        ]);
        $L->table('bls')->insert([
            ['id' => 1, 'dlm' => 53, 'client' => 0, 'code' => 'BL-01', 'cmds' => '0,100,104,99999,100', 'done' => 'on', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 2, 'dlm' => 76, 'client' => 0, 'code' => 'BL-02', 'cmds' => '0,104', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 3, 'dlm' => 0, 'client' => 45, 'code' => 'BL-03', 'cmds' => '0,101,102', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 4, 'dlm' => 0, 'client' => 45, 'code' => 'BR-04', 'cmds' => '0,103', 'done' => 'on', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 5, 'dlm' => 53, 'client' => 0, 'code' => 'BRL-05', 'cmds' => '0,103', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 6, 'dlm' => 0, 'client' => 0, 'code' => 'XX-06', 'cmds' => '0,100', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
            ['id' => 7, 'dlm' => 53, 'client' => 0, 'code' => 'BL-01', 'cmds' => '0', 'done' => 'off', 'dateadd' => (string) self::T0, 'trash' => 1],
        ]);
    }

}
