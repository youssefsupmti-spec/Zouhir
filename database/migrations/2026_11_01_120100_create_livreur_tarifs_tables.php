<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 29 — Commissions LIVREURS (domaine distinct des frais facturés au client).
 *
 * `livreur_tarifs` : commission SPÉCIFIQUE d'un livreur pour une ville
 *   (WinningExpress `livreur_tarifs`, legacy `shippingfees` dlm > 0).
 *   UNIQUE (livreur_id, ville_id) corbeille comprise : cycle actif → supprimé → RESTAURÉ.
 *   Pas d'`agence_id` : Zouhir ne possède aucune table `agences` (aucune valeur arbitraire).
 *
 * `livreur_standard_tarifs` : barème STANDARD des commissions par ville
 *   (legacy `shippingfees` dlm = 0). Une ville porte au plus une ligne (UNIQUE ville_id).
 *   Jamais mélangé avec `standard_tarifs` (frais facturés au marchand).
 *
 * Clés étrangères en RESTRICT : supprimer un compte ou une ville ne doit jamais
 * effacer en cascade l'historique des commissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('livreur_tarifs')) {
            Schema::create('livreur_tarifs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('livreur_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('ville_id')->constrained('villes')->restrictOnDelete();
                $table->decimal('commission_livraison', 10, 2);
                $table->decimal('commission_refus', 10, 2);
                $table->json('legacy_ids')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['livreur_id', 'ville_id'], 'livreur_tarifs_livreur_ville_unique');
                $table->index('ville_id', 'livreur_tarifs_ville_index');
            });

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE livreur_tarifs ADD CONSTRAINT livreur_tarifs_montants_positifs CHECK (commission_livraison >= 0 AND commission_refus >= 0)');
            }
        }

        if (! Schema::hasTable('livreur_standard_tarifs')) {
            Schema::create('livreur_standard_tarifs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ville_id')->unique()->constrained('villes')->restrictOnDelete();
                $table->decimal('commission_livraison', 10, 2);
                $table->decimal('commission_refus', 10, 2);
                $table->json('legacy_ids')->nullable();
                $table->timestamps();
            });

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE livreur_standard_tarifs ADD CONSTRAINT livreur_standard_tarifs_montants_positifs CHECK (commission_livraison >= 0 AND commission_refus >= 0)');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('livreur_standard_tarifs');
        Schema::dropIfExists('livreur_tarifs');
    }
};
