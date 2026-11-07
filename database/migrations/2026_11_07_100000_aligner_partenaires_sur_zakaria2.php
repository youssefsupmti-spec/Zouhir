<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau national (C132, rapport 63) — structure de Zakaria2 (`delivery_partners`), avec améliorations.
 *
 * Données CONSERVÉES : la table `partenaires` (comptes #76 et #102 repris) est RENOMMÉE, ses
 * colonnes aussi ; les liens des colis et des bons suivent (clés étrangères conservées).
 *
 *  · partenaires.raison_sociale → delivery_partners.name ; actif → is_active   (Zakaria2)
 *  · + api_endpoint, api_key_hash                                              (Zakaria2)
 *  · + type_partenaire, adresse, ville_id, commission_defaut, delai_transit_moyen_h,
 *    webhook_secret (chiffré par l'application), format_payload, last_sync_at  (rapport 63)
 *  · commands / bons_envoi / bons_retour : partenaire_id → delivery_partner_id (Zakaria2)
 *  · commands.partner_tracking_code : n° de suivi du colis chez le partenaire (Zakaria2)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partenaires') && ! Schema::hasTable('delivery_partners')) {
            Schema::rename('partenaires', 'delivery_partners');
        }

        Schema::table('delivery_partners', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_partners', 'raison_sociale')) {
                $table->renameColumn('raison_sociale', 'name');
            }
            if (Schema::hasColumn('delivery_partners', 'actif')) {
                $table->renameColumn('actif', 'is_active');
            }
        });

        Schema::table('delivery_partners', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_partners', 'api_endpoint')) {
                $table->string('api_endpoint')->nullable();
                $table->string('api_key_hash')->nullable()->index();
                // Amélioration : secret de signature distinct du jeton (Zakaria2 signe avec api_key_hash).
                $table->text('webhook_secret')->nullable();
                $table->string('format_payload', 30)->default('json_standard');
                $table->timestamp('last_sync_at')->nullable();
                // transporteur_ligne | agence_consoeur | plateforme_nationale
                $table->string('type_partenaire', 30)->default('transporteur_ligne')->index();
                $table->text('adresse')->nullable();
                $table->foreignId('ville_id')->nullable()->constrained('villes')->nullOnDelete();
                $table->decimal('commission_defaut', 10, 2)->nullable();
                $table->unsignedSmallInteger('delai_transit_moyen_h')->nullable();
            }
        });

        foreach (['commands', 'bons_envoi', 'bons_retour'] as $table) {
            if (Schema::hasColumn($table, 'partenaire_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->renameColumn('partenaire_id', 'delivery_partner_id'));
            }
        }

        if (! Schema::hasColumn('commands', 'partner_tracking_code')) {
            Schema::table('commands', function (Blueprint $table) {
                $table->string('partner_tracking_code', 100)->nullable()->after('delivery_partner_id');
                $table->index(['delivery_partner_id', 'partner_tracking_code'], 'commands_partenaire_suivi_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropIndex('commands_partenaire_suivi_index');
            $table->dropColumn('partner_tracking_code');
        });
        foreach (['commands', 'bons_envoi', 'bons_retour'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn('delivery_partner_id', 'partenaire_id'));
        }
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropIndex(['api_key_hash']);
            $table->dropIndex(['type_partenaire']);
            $table->dropConstrainedForeignId('ville_id');
            $table->dropColumn(['api_endpoint', 'api_key_hash', 'webhook_secret', 'format_payload', 'last_sync_at',
                'type_partenaire', 'adresse', 'commission_defaut', 'delai_transit_moyen_h']);
            $table->renameColumn('name', 'raison_sociale');
            $table->renameColumn('is_active', 'actif');
        });
        Schema::rename('delivery_partners', 'partenaires');
    }
};
