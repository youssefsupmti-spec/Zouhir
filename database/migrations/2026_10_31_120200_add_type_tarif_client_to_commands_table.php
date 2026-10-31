<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 28 — Provenance du snapshot `commands.frais_livraison_client`.
 *
 * Colonne NULLABLE, sans défaut et sans backfill : les colis existants (dont les
 * colis historiques importés à 0.00) gardent NULL = « provenance inconnue ».
 * Aucune ligne existante n'est réécrite par cette migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('commands', 'type_tarif_client')) {
            return;
        }

        Schema::table('commands', function (Blueprint $table) {
            $table->string('type_tarif_client', 50)->nullable()->after('frais_livraison_client');
            // Recherche des anomalies (missing) et des tarifs manuels.
            $table->index('type_tarif_client', 'commands_type_tarif_client_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('commands', 'type_tarif_client')) {
            return;
        }

        Schema::table('commands', function (Blueprint $table) {
            $table->dropIndex('commands_type_tarif_client_index');
            $table->dropColumn('type_tarif_client');
        });
    }
};
