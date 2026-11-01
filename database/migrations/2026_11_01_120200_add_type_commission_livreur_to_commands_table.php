<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 29 — Provenance du snapshot `commands.frais_livraison_livreur`
 * (manual | specifique | standard | missing).
 *
 * NULLABLE, sans défaut et SANS backfill : les colis existants gardent NULL
 * (« provenance inconnue ») et leur montant actuel. Aucune ligne n'est réécrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('commands', 'type_commission_livreur')) {
            return;
        }

        Schema::table('commands', function (Blueprint $table) {
            $table->string('type_commission_livreur', 20)->nullable()->after('frais_livraison_livreur');
            $table->index('type_commission_livreur', 'commands_type_commission_livreur_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('commands', 'type_commission_livreur')) {
            return;
        }

        Schema::table('commands', function (Blueprint $table) {
            $table->dropIndex('commands_type_commission_livreur_index');
            $table->dropColumn('type_commission_livreur');
        });
    }
};
