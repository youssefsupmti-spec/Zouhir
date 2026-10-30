<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration d'alignement complet du schéma `users` sur WinningExpress / Zakaria2.
 * 
 * - Renommage et normalisation :
 *     `name`        -> `nom_complet`
 *     `phone`       -> `telephone`
 *     `city`        -> `ville_name`
 *     `is_active`   -> `active`
 * - Ajout des colonnes de parité :
 *     `ville_id`, `civilite`, `picture`, `cin`, `sav`, `bank`, `rib`,
 *     `stockout`, `emailstockout`, `api_abilities`, `affiliate`, `maps`,
 *     `affictaion`, `emailsend`, `deleted_at` (SoftDeletes)
 * - Circuit d'inscription client :
 *     `inscription_statut`, `inscription_traitee_par`, `inscription_traitee_le`,
 *     `inscription_motif`, `inscription_refus_nombre`, `inscription_dernier_refus_le`
 * - Création des tables satellites du modèle WinningExpress :
 *     `businesses` (enseignes marchandes des clients)
 *     `comptes_bancaires` (coordonnées bancaires RIB multi-comptes)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Renommages sécurisés
            if (Schema::hasColumn('users', 'name') && ! Schema::hasColumn('users', 'nom_complet')) {
                $table->renameColumn('name', 'nom_complet');
            }
            if (Schema::hasColumn('users', 'phone') && ! Schema::hasColumn('users', 'telephone')) {
                $table->renameColumn('phone', 'telephone');
            }
            if (Schema::hasColumn('users', 'city') && ! Schema::hasColumn('users', 'ville_name')) {
                $table->renameColumn('city', 'ville_name');
            }
            if (Schema::hasColumn('users', 'is_active') && ! Schema::hasColumn('users', 'active')) {
                $table->renameColumn('is_active', 'active');
            }

            // 2. Colonnes d'identité et profil WinningExpress
            if (! Schema::hasColumn('users', 'civilite')) {
                $table->string('civilite', 20)->nullable()->after('nom_complet');
            }
            if (! Schema::hasColumn('users', 'picture')) {
                $table->string('picture')->nullable()->after('civilite');
            }
            if (! Schema::hasColumn('users', 'cin')) {
                $table->string('cin', 50)->nullable()->after('telephone');
            }
            if (! Schema::hasColumn('users', 'sav')) {
                $table->string('sav', 100)->nullable()->after('cin');
            }
            if (! Schema::hasColumn('users', 'ville_id')) {
                $table->integer('ville_id')->nullable()->after('ville_name')->index();
            }

            // 3. Coordonnées bancaires & stockout historiques
            if (! Schema::hasColumn('users', 'bank')) {
                $table->string('bank', 100)->nullable()->after('ville_id');
            }
            if (! Schema::hasColumn('users', 'rib')) {
                $table->string('rib', 50)->nullable()->after('bank');
            }
            if (! Schema::hasColumn('users', 'stockout')) {
                $table->string('stockout')->nullable()->after('rib');
            }
            if (! Schema::hasColumn('users', 'emailstockout')) {
                $table->string('emailstockout')->nullable()->after('stockout');
            }

            // 4. Droits API & Métadonnées
            if (! Schema::hasColumn('users', 'api_abilities')) {
                $table->json('api_abilities')->nullable()->after('emailstockout');
            }
            if (! Schema::hasColumn('users', 'affiliate')) {
                $table->string('affiliate')->nullable()->after('api_abilities');
            }
            if (! Schema::hasColumn('users', 'maps')) {
                $table->string('maps', 500)->nullable()->after('affiliate');
            }
            if (! Schema::hasColumn('users', 'affictaion')) {
                $table->string('affictaion')->nullable()->after('maps');
            }
            if (! Schema::hasColumn('users', 'emailsend')) {
                $table->string('emailsend', 50)->default('non')->after('affictaion');
            }

            // 5. Circuit de validation d'inscription (C120 WinningExpress)
            if (! Schema::hasColumn('users', 'inscription_statut')) {
                $table->string('inscription_statut', 20)->nullable()->after('active')->index();
            }
            if (! Schema::hasColumn('users', 'inscription_traitee_par')) {
                $table->unsignedBigInteger('inscription_traitee_par')->nullable()->after('inscription_statut');
            }
            if (! Schema::hasColumn('users', 'inscription_traitee_le')) {
                $table->timestamp('inscription_traitee_le')->nullable()->after('inscription_traitee_par');
            }
            if (! Schema::hasColumn('users', 'inscription_motif')) {
                $table->string('inscription_motif', 500)->nullable()->after('inscription_traitee_le');
            }
            if (! Schema::hasColumn('users', 'inscription_refus_nombre')) {
                $table->unsignedSmallInteger('inscription_refus_nombre')->default(0)->after('inscription_motif');
            }
            if (! Schema::hasColumn('users', 'inscription_dernier_refus_le')) {
                $table->timestamp('inscription_dernier_refus_le')->nullable()->after('inscription_refus_nombre');
            }

            // 6. SoftDeletes
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 7. Table des Enseignes / Boutiques marchandes (Businesses)
        if (! Schema::hasTable('businesses')) {
            Schema::create('businesses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name');
                $table->string('phone', 50)->nullable();
                $table->string('city', 100)->nullable();
                $table->text('address')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 8. Table des Comptes Bancaires (ComptesBancaires)
        if (! Schema::hasTable('comptes_bancaires')) {
            Schema::create('comptes_bancaires', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->string('owner_type', 30)->default('client'); // client, livreur, agence
                $table->string('titulaire_nom');
                $table->string('titulaire_prenom')->nullable();
                $table->unsignedBigInteger('bank_id')->nullable()->index();
                $table->string('bank_name')->nullable();
                $table->string('rib', 40);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['owner_id', 'owner_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes_bancaires');
        Schema::dropIfExists('businesses');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('users', 'nom_complet') && ! Schema::hasColumn('users', 'name')) {
                $table->renameColumn('nom_complet', 'name');
            }
            if (Schema::hasColumn('users', 'telephone') && ! Schema::hasColumn('users', 'phone')) {
                $table->renameColumn('telephone', 'phone');
            }
            if (Schema::hasColumn('users', 'ville_name') && ! Schema::hasColumn('users', 'city')) {
                $table->renameColumn('ville_name', 'city');
            }
            if (Schema::hasColumn('users', 'active') && ! Schema::hasColumn('users', 'is_active')) {
                $table->renameColumn('active', 'is_active');
            }
        });
    }
};
