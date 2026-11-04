<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bons du circuit colis (C140) — additif pur.
 *
 * L'ancien site rangeait TOUS les bons dans `bls` (CSV `cmds`). Ils sont répartis en quatre familles :
 *  · bons_livraison   (BL)  : tournée d'un livreur de l'agence           ← `BL-…`, dlm = livreur
 *  · bons_envoi       (BE)  : remise au réseau partenaire national       ← `BL-…`, dlm = compte partenaire
 *  · bon_ramassage    (BRA) : entrée au hub des colis d'un marchand      ← `BL-…`, dlm = 0, client > 0
 *  · bons_retour      (BRC/BRL) : restitution au marchand / décharge livreur ← `BR-…` / `BRL-…`
 *
 * Règles communes, pour ne rien perdre :
 *  · `code` indexé mais NON unique (2 codes legacy sont en double) ; l'identité est `source_bls_id`.
 *  · Lignes : `legacy_command_id` garde l'id lu dans le CSV, même si le colis n'existe plus
 *    (`command_id` = NULL, copie figée vide) — la ligne et l'anomalie restent visibles.
 *  · `legacy_brut` (JSON) : copie de la ligne `bls` d'origine.
 *  · Statuts en VARCHAR (validés par l'application) pour rester portables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bons_livraison', 'bons_envoi'] as $nom) {
            if (Schema::hasTable($nom)) {
                continue;
            }
            Schema::create($nom, function (Blueprint $table) use ($nom) {
                $table->id();
                $table->string('code')->index();
                $table->unsignedBigInteger('source_bls_id')->nullable()->unique();
                $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
                $table->foreignId('livreur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('livreur_nom')->nullable();
                $table->string('livreur_telephone')->nullable();
                if ($nom === 'bons_envoi') {
                    $table->foreignId('partenaire_id')->nullable()->constrained('partenaires')->nullOnDelete();
                    $table->string('hub')->nullable();
                }
                $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
                $table->string('zone_name')->nullable();
                $table->string('code_zone', 50)->nullable()->index();
                // brouillon | expedie | traite
                $table->string('status', 20)->default('brouillon')->index();
                $table->unsignedInteger('version')->default(0);
                $table->unsignedInteger('total_colis')->default(0);
                $table->unsignedInteger('checked_colis_count')->default(0);
                $table->unsignedInteger('anomalies_count')->default(0);
                $table->decimal('montant_total_brut', 14, 2)->default(0);
                $table->decimal('frais_total', 12, 2)->default(0);
                $table->timestamp('expedie_at')->nullable();
                $table->foreignId('expedie_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('traite_at')->nullable();
                $table->foreignId('traite_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('traite_sans_pointage_historique')->default(false);
                $table->timestamp('legacy_dateadd_at')->nullable();
                $table->json('legacy_brut')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['livreur_id', 'status']);
            });
        }

        foreach (['bon_livraison_command' => ['bons_livraison', 'bon_livraison_id'], 'bon_envoi_command' => ['bons_envoi', 'bon_envoi_id']] as $pivot => [$parent, $cle]) {
            if (Schema::hasTable($pivot)) {
                continue;
            }
            Schema::create($pivot, function (Blueprint $table) use ($parent, $cle, $pivot) {
                $table->id();
                $table->foreignId($cle)->constrained($parent)->cascadeOnDelete();
                $table->foreignId('command_id')->nullable()->constrained('commands')->nullOnDelete();
                $table->unsignedBigInteger('legacy_command_id')->nullable();
                $table->unsignedInteger('sequence')->default(0);
                $table->string('command_code')->nullable()->index();
                $table->string('destinataire_nom')->nullable();
                $table->string('destinataire_telephone', 64)->nullable();
                $table->string('destinataire_ville')->nullable();
                $table->foreignId('destinataire_ville_id')->nullable()->constrained('villes')->nullOnDelete();
                $table->string('destinataire_zone_code', 50)->nullable();
                $table->decimal('prix_brut', 14, 2)->nullable();
                $table->decimal('total', 14, 2)->nullable();
                $table->decimal('frais_livraison_client', 12, 2)->default(0);
                $table->decimal('montant_supplementaire', 12, 2)->default(0);
                $table->decimal('montant_emballage', 12, 2)->default(0);
                // scan | manuelle | camera | reprise
                $table->string('ajout_methode', 20)->default('scan');
                $table->boolean('is_checked')->default(false)->index();
                $table->timestamp('checked_at')->nullable();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('checked_methode', 20)->nullable();
                $table->boolean('is_anomalie')->default(false)->index();
                $table->string('anomalie_motif')->nullable();
                $table->timestamps();

                $table->unique([$cle, 'legacy_command_id'], "{$pivot}_bon_legacy_unique");
                $table->index('command_id', "{$pivot}_command_idx");
            });
        }

        if (! Schema::hasTable('bon_ramassage')) {
            Schema::create('bon_ramassage', function (Blueprint $table) {
                $table->id();
                $table->string('code')->index();
                $table->unsignedBigInteger('source_bls_id')->nullable()->unique();
                $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->string('business_name')->nullable();
                $table->string('nom_cliente')->nullable();
                // brouillon | valide | traite
                $table->string('status', 20)->default('brouillon')->index();
                $table->unsignedInteger('total_colis')->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->timestamp('client_validated_at')->nullable();
                $table->foreignId('client_validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('legacy_dateadd_at')->nullable();
                $table->json('legacy_brut')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bon_ramassage_lines')) {
            Schema::create('bon_ramassage_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bon_ramassage_id')->constrained('bon_ramassage')->cascadeOnDelete();
                $table->foreignId('command_id')->nullable()->constrained('commands')->nullOnDelete();
                $table->unsignedBigInteger('legacy_command_id')->nullable();
                $table->unsignedInteger('sequence')->default(0);
                $table->string('code')->nullable()->index();
                $table->string('nom_destinataire')->nullable();
                $table->string('telephone', 64)->nullable();
                $table->string('ville')->nullable();
                $table->text('produit')->nullable();
                $table->decimal('prix', 14, 2)->nullable();
                $table->timestamps();

                $table->unique(['bon_ramassage_id', 'legacy_command_id'], 'bon_ramassage_lines_bon_legacy_unique');
                $table->index('command_id');
            });
        }

        if (! Schema::hasTable('bons_retour')) {
            Schema::create('bons_retour', function (Blueprint $table) {
                $table->id();
                $table->string('code')->index();
                $table->unsignedBigInteger('source_bls_id')->nullable()->unique();
                // client (retour au marchand) | livreur (décharge d'un livreur)
                $table->string('type', 20)->index();
                $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->string('business_name')->nullable();
                $table->foreignId('livreur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('livreur_nom')->nullable();
                $table->foreignId('partenaire_id')->nullable()->constrained('partenaires')->nullOnDelete();
                // brouillon | enregistre | traite
                $table->string('status', 20)->default('brouillon')->index();
                $table->unsignedInteger('total_colis')->default(0);
                $table->decimal('montant_total_brut', 14, 2)->default(0);
                $table->timestamp('traite_at')->nullable();
                $table->foreignId('traite_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('traite_par', 20)->nullable();
                $table->timestamp('legacy_dateadd_at')->nullable();
                $table->json('legacy_brut')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['type', 'status']);
            });
        }

        if (! Schema::hasTable('bon_retour_command')) {
            Schema::create('bon_retour_command', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bon_retour_id')->constrained('bons_retour')->cascadeOnDelete();
                $table->foreignId('command_id')->nullable()->constrained('commands')->nullOnDelete();
                $table->unsignedBigInteger('legacy_command_id')->nullable();
                $table->unsignedInteger('sequence')->default(0);
                $table->string('command_code')->nullable()->index();
                $table->string('destinataire_nom')->nullable();
                $table->string('destinataire_telephone', 64)->nullable();
                $table->string('destinataire_ville')->nullable();
                $table->string('destinataire_zone_code', 50)->nullable();
                $table->decimal('prix_brut', 14, 2)->nullable();
                $table->decimal('total', 14, 2)->nullable();
                $table->string('etat_au_bon')->nullable();
                $table->timestamps();

                $table->unique(['bon_retour_id', 'legacy_command_id'], 'bon_retour_command_bon_legacy_unique');
                $table->index('command_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['bon_retour_command', 'bons_retour', 'bon_ramassage_lines', 'bon_ramassage',
            'bon_envoi_command', 'bon_livraison_command', 'bons_envoi', 'bons_livraison'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
