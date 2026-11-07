<?php

namespace Tests\Feature\Reseau;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** SQLite accepte des noms d'index que MySQL refuse (> 64 caractères) : on les contrôle ici. */
class NomsIndexMysqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucun_nom_d_index_ne_depasse_la_limite_mysql(): void
    {
        $tropLongs = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                if (strlen($index['name']) > 64) {
                    $tropLongs[] = "{$table}.{$index['name']}";
                }
            }
        }

        $this->assertSame([], $tropLongs, 'Noms d’index trop longs pour MySQL : '.implode(', ', $tropLongs));
    }
}
