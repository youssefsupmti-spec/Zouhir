<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 29 — Retrait du barème standard des commissions livreurs.
 *
 * Décision du propriétaire (01/11/2026), comme WinningExpress : la commission d'un livreur est
 * uniquement spécifique (livreur × ville). La table `livreur_standard_tarifs` n'est plus lue par
 * le code. Elle a été vidée après sauvegarde serveur (backup-livreur_standard_tarifs-20261101.json).
 *
 * Garde-fou : la table n'est supprimée QUE si elle est vide. Si elle contient des lignes, la
 * migration échoue sans rien supprimer (aucune perte de données possible).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('livreur_standard_tarifs')) {
            return;
        }

        $lignes = DB::table('livreur_standard_tarifs')->count();
        if ($lignes > 0) {
            throw new RuntimeException("livreur_standard_tarifs contient {$lignes} ligne(s) : suppression refusée (sauvegarder puis vider d'abord).");
        }

        Schema::drop('livreur_standard_tarifs');
    }

    public function down(): void
    {
        if (Schema::hasTable('livreur_standard_tarifs')) {
            return;
        }

        Schema::create('livreur_standard_tarifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ville_id')->unique()->constrained('villes')->restrictOnDelete();
            $table->decimal('commission_livraison', 10, 2);
            $table->decimal('commission_refus', 10, 2);
            $table->json('legacy_ids')->nullable();
            $table->timestamps();
        });
    }
};
