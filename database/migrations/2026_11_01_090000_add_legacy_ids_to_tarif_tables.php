<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 28 — Traçabilité des règles reprises du legacy (rapport #35, décision 5).
 *
 * `legacy_ids` conserve TOUS les identifiants legacy dont une règle est issue :
 * une règle saisie deux fois à l'identique dans `clientfees` (ex. #64 et #66) devient
 * une seule ligne `client_tarifs` portant [64, 66]. NULL = règle créée dans Zouhir.
 * Colonne additive, nullable, sans réécriture de données.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['client_tarifs', 'standard_tarifs'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'legacy_ids')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->json('legacy_ids')->nullable()->after('frais_retour');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['client_tarifs', 'standard_tarifs'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'legacy_ids')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('legacy_ids');
                });
            }
        }
    }
};
