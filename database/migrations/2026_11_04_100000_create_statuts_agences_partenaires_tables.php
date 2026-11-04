<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiels du circuit colis (C140) — additif pur.
 *
 *  · statuts         : référentiel des statuts colis. Les identifiants des 29 statuts legacy
 *                      (`trackingstates`) sont conservés à l'identique ; un libellé rencontré sur
 *                      un colis mais absent du référentiel est créé « hors référentiel » (inactif)
 *                      par la reprise, jamais fusionné d'office avec un autre.
 *  · agences         : agences OUAISS EXPRESS (Agence Témara = agence principale).
 *  · agence_membres  : rattachement des livreurs / agents / ramasseurs à une agence.
 *  · partenaires     : transporteurs du réseau national ; `user_id` = compte utilisé
 *                      dans l'ancien site pour enregistrer leurs bons.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statuts')) {
            Schema::create('statuts', function (Blueprint $table) {
                $table->id();
                // Pas unique : l'ancien référentiel a deux « Manque de stock » (ids 16 et 18).
                $table->string('libelle', 120)->index();
                $table->string('couleur', 30)->nullable();
                // nouveau | en_cours | livre | retour | annule — même vocabulaire que commands.state_group
                $table->string('groupe', 20)->default('en_cours')->index();
                $table->string('kpi_legacy', 50)->nullable();
                $table->string('acteurs_legacy')->nullable(); // trackingstates.agent (« Modérateur,Livreur »)
                $table->boolean('actif')->default(true)->index();
                // referentiel_legacy | hors_referentiel | application
                $table->string('origine', 30)->default('application')->index();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('agences')) {
            Schema::create('agences', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('nom')->unique();
                $table->foreignId('ville_id')->nullable()->constrained('villes')->nullOnDelete();
                $table->string('telephone', 30)->nullable();
                $table->string('adresse')->nullable();
                $table->boolean('est_principale')->default(false);
                $table->boolean('actif')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('agence_membres')) {
            Schema::create('agence_membres', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                // manager | suivi | agent | livreur | ramasseur
                $table->string('role', 20);
                $table->boolean('actif')->default(true);
                $table->date('depuis')->nullable();
                $table->date('jusqua')->nullable();
                $table->timestamps();

                $table->unique(['agence_id', 'user_id', 'role'], 'agence_membres_agence_user_role_unique');
                $table->index(['agence_id', 'role', 'actif']);
            });
        }

        if (! Schema::hasTable('partenaires')) {
            Schema::create('partenaires', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('raison_sociale');
                $table->string('ice', 32)->nullable();
                $table->string('telephone', 64)->nullable();
                $table->string('email')->nullable();
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->boolean('actif')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Agence principale : l'activité historique de OUAISS EXPRESS part de Témara.
        if (DB::table('agences')->count() === 0) {
            $temara = Schema::hasTable('villes')
                ? DB::table('villes')->whereNull('deleted_at')->whereRaw('LOWER(name_fr) IN (?, ?)', ['temara', 'témara'])->orderBy('id')->value('id')
                : null;
            DB::table('agences')->insert([
                'code' => 'TEM',
                'nom' => 'Agence Témara',
                'ville_id' => $temara,
                'est_principale' => true,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agence_membres');
        Schema::dropIfExists('partenaires');
        Schema::dropIfExists('agences');
        Schema::dropIfExists('statuts');
    }
};
