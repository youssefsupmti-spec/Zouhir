<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 28 — Tarifs négociés client × ville.
 *
 * Cycle de vie (ACTIF → SUPPRIMÉ → RESTAURÉ) : l'unicité (client_id, ville_id)
 * porte sur TOUTES les lignes, corbeille comprise. Une renégociation d'un couple
 * supprimé RESTAURE la ligne existante (ClientTarifEcriture) : elle n'en crée
 * jamais une seconde. Il ne peut donc exister ni doublon actif, ni collision
 * d'unicité au moment de la restauration.
 *
 * Clés étrangères en RESTRICT (pas de CASCADE) : un tarif est une donnée
 * financière ; la suppression physique d'un client ou d'une ville ne doit pas
 * effacer silencieusement sa grille (les deux modèles sont en SoftDeletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_tarifs')) {
            return;
        }

        Schema::create('client_tarifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ville_id')->constrained('villes')->restrictOnDelete();

            // Aucun défaut : un montant absent est refusé ; 0.00 est une valeur explicite (gratuité négociée).
            $table->decimal('frais_livraison', 10, 2);
            $table->decimal('frais_refus', 10, 2);
            $table->decimal('frais_retour', 10, 2);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'ville_id'], 'client_tarifs_client_ville_unique');
            $table->index(['ville_id', 'deleted_at'], 'client_tarifs_ville_deleted_index');
        });

        // Montants non négatifs garantis par la base (MySQL 8.0.16+ / MariaDB 10.2+).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE client_tarifs ADD CONSTRAINT client_tarifs_montants_positifs CHECK (frais_livraison >= 0 AND frais_refus >= 0 AND frais_retour >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tarifs');
    }
};
