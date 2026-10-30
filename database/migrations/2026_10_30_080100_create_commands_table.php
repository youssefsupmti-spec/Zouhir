<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aligne la table commands (colis express) sur le standard WinningExpress / Zakaria2.
     */
    public function up(): void
    {
        if (! Schema::hasTable('commands')) {
            Schema::create('commands', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('produit')->nullable();
                $table->integer('quantite')->default(1);

                // Rattachement Client & Enseigne (Business)
                $table->foreignId('client_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->foreignId('business_id')
                    ->nullable()
                    ->constrained('businesses')
                    ->nullOnDelete();
                $table->string('business_name')->nullable()->index();
                $table->string('nom_cliente')->nullable();

                // Rattachement Livreur (DLM)
                $table->foreignId('livreur_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('livreur_nom')->nullable();
                $table->string('livreur_telephone')->nullable();

                // Destinataire Final
                $table->string('destinataire_nom');
                $table->string('destinataire_telephone')->index();
                $table->text('destinataire_adresse')->nullable();
                $table->string('destinataire_ville')->index();
                $table->foreignId('destinataire_ville_id')
                    ->nullable()
                    ->constrained('villes')
                    ->nullOnDelete();
                $table->string('destinataire_zone_code', 50)->nullable()->index();

                // Montants financiers (CRBT & Tarifs de transport)
                $table->decimal('prix_brut', 10, 2)->default(0)->comment('Montant CRBT à encaisser');
                $table->decimal('frais_livraison_client', 10, 2)->default(0);
                $table->decimal('frais_livraison_livreur', 10, 2)->default(0);

                // Statuts & Groupes d'état logistiques
                $table->string('state')->index()->comment('Statut textuel');
                $table->string('state_group', 50)->default('nouveau')->index()->comment('Groupe: nouveau, en_cours, livre, retour, annule');

                // Options du colis
                $table->boolean('is_ramassee')->default(false)->index();
                $table->boolean('ouverture_autorisee')->default(false);
                $table->boolean('echange')->default(false);
                $table->text('note')->nullable();

                // Jalons temporels d'états
                $table->timestamp('livre_at')->nullable()->index();
                $table->timestamp('expedie_at')->nullable();
                $table->timestamp('ramasse_at')->nullable();
                $table->timestamp('datereported_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Index composites pour performances des requêtes marchands et livreurs
                $table->index(['client_id', 'state_group']);
                $table->index(['livreur_id', 'state_group']);
                $table->index(['created_at', 'state_group']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
