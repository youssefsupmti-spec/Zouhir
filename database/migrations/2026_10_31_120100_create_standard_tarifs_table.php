<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 28 — Barème standard par ville (source de vérité unique du tarif standard).
 *
 * Zouhir ne possédait AUCUN tarif standard (ni table, ni colonne sur `villes`).
 * Une ville porte au plus une ligne (UNIQUE ville_id). La relation inversée de
 * WinningExpress (`villes.standard_tarif_id` + attribut virtuel) n'est pas reprise :
 * elle impose deux écritures par geste sans bénéfice pour Zouhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('standard_tarifs')) {
            return;
        }

        Schema::create('standard_tarifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ville_id')->unique()->constrained('villes')->restrictOnDelete();
            $table->decimal('frais_livraison', 10, 2);
            $table->decimal('frais_refus', 10, 2);
            $table->decimal('frais_retour', 10, 2);
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE standard_tarifs ADD CONSTRAINT standard_tarifs_montants_positifs CHECK (frais_livraison >= 0 AND frais_refus >= 0 AND frais_retour >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_tarifs');
    }
};
