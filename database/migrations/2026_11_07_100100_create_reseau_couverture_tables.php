<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau national, flotte régionale et couverture des zones (C132, rapport 63) — additif.
 *
 * Structure de Zakaria2, avec améliorations :
 *  · delivery_partner_zones   (Zakaria2) + tarif_transit_unitaire, delai_garanti_h, is_exclusif
 *  · partner_status_mappings  (Zakaria2) + statut_id (statut du référentiel), notes
 *  · partner_webhook_logs     (Zakaria2, colis_id → command_id) + sens, route, code HTTP,
 *                             signature valide, IP
 *  · livreur_zones            (Zakaria2) : zones d'un coursier (livraison | ramassage)
 *  · zone_couvertures         (rapport 63) : titulaire, remplaçant, partenaire et état par zone
 *  · livreur_tarifs           (existante, 148 lignes conservées) + zone, échange, reprise,
 *                             suppléments, actif, auteurs (Zakaria2 created_by / updated_by)
 *
 * zone_couvertures est créée VIDE d'affectations (« non couvert ») : rien n'est inventé ;
 * les titulaires se choisissent dans le tableau de couverture.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_partner_zones')) {
            Schema::create('delivery_partner_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_partner_id')->constrained('delivery_partners')->cascadeOnDelete();
                $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
                $table->decimal('tarif_transit_unitaire', 10, 2)->nullable();
                $table->unsignedSmallInteger('delai_garanti_h')->nullable();
                $table->boolean('is_exclusif')->default(false);
                $table->timestamps();

                $table->unique(['delivery_partner_id', 'zone_id']);
            });
        }

        if (! Schema::hasTable('partner_status_mappings')) {
            Schema::create('partner_status_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_partner_id')->constrained('delivery_partners')->cascadeOnDelete();
                $table->string('partner_status', 50);
                $table->string('colis_status', 50);
                $table->foreignId('statut_id')->nullable()->constrained('statuts')->nullOnDelete();
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['delivery_partner_id', 'partner_status']);
            });
        }

        if (! Schema::hasTable('partner_webhook_logs')) {
            Schema::create('partner_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_partner_id')->nullable()->constrained('delivery_partners')->nullOnDelete();
                // entrant (le partenaire nous appelle) | sortant (nous l'appelons)
                $table->string('direction', 10)->default('entrant');
                $table->string('endpoint', 120)->nullable();
                $table->json('payload');
                $table->foreignId('command_id')->nullable()->constrained('commands')->nullOnDelete();
                $table->boolean('processed')->default(false);
                $table->boolean('signature_valide')->nullable();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->text('error_message')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['delivery_partner_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('livreur_zones')) {
            Schema::create('livreur_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
                $table->string('zone_type', 20)->default('livraison')->index();
                $table->timestamps();

                $table->unique(['user_id', 'zone_id', 'zone_type']);
                $table->index('zone_id');
            });
        }

        if (! Schema::hasTable('zone_couvertures')) {
            Schema::create('zone_couvertures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zone_id')->unique()->constrained('zones')->cascadeOnDelete();
                // regional_flotte | national_partenaire
                $table->string('type_couverture', 30)->default('regional_flotte');
                $table->foreignId('titulaire_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('backup_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('delivery_partner_id')->nullable()->constrained('delivery_partners')->nullOnDelete();
                // optimal | normal | degrade | non_couvert
                $table->string('statut_couverture', 20)->default('non_couvert');
                $table->string('notes')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('livreur_tarifs', function (Blueprint $table) {
            if (! Schema::hasColumn('livreur_tarifs', 'zone_id')) {
                $table->foreignId('zone_id')->nullable()->after('ville_id')->constrained('zones')->nullOnDelete();
                $table->decimal('tarif_echange', 10, 2)->nullable();
                $table->decimal('tarif_reprise', 10, 2)->nullable();
                $table->decimal('supplement_grand', 10, 2)->nullable();
                $table->decimal('supplement_fragile', 10, 2)->nullable();
                $table->boolean('actif')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // Couverture : une ligne par zone, régime repris du type de la zone, sans affectation.
        $now = now();
        foreach (DB::table('zones')->whereNull('deleted_at')->get(['id', 'type']) as $z) {
            if (DB::table('zone_couvertures')->where('zone_id', $z->id)->exists()) {
                continue;
            }
            DB::table('zone_couvertures')->insert([
                'zone_id' => $z->id,
                'type_couverture' => ($z->type ?? 'nationale') === 'locale' ? 'regional_flotte' : 'national_partenaire',
                'statut_couverture' => 'non_couvert',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Zone de chaque tarif livreur : celle de sa ville (déterministe, aucune déduction).
        foreach (DB::table('livreur_tarifs')->whereNull('livreur_tarifs.zone_id')->join('villes', 'villes.id', '=', 'livreur_tarifs.ville_id')
            ->whereNotNull('villes.zone_id')->get(['livreur_tarifs.id', 'villes.zone_id']) as $t) {
            DB::table('livreur_tarifs')->where('id', $t->id)->update(['zone_id' => $t->zone_id]);
        }
    }

    public function down(): void
    {
        Schema::table('livreur_tarifs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['tarif_echange', 'tarif_reprise', 'supplement_grand', 'supplement_fragile', 'actif']);
        });
        foreach (['zone_couvertures', 'livreur_zones', 'partner_webhook_logs', 'partner_status_mappings', 'delivery_partner_zones'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
