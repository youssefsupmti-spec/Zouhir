<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alignement de `commands` pour une reprise EXACTE de l'ancienne base (C140).
 *
 *  · `code` n'est plus unique : l'ancienne base contient 366 codes portés par plusieurs colis
 *    (ex. « CODE1 » ×14). Les supprimer ou les renommer serait une perte de données ; l'unicité
 *    reste garantie par `id` (= id legacy) et les doublons sont listés dans les anomalies de reprise.
 *  · `client_id` devient nullable : 68 colis legacy n'ont aucun expéditeur (`client = 0`). Ils
 *    étaient rattachés d'office au compte #1, ce qui faussait les chiffres de ce compte.
 *  · Colonnes du modèle de référence : statut, zone, montants extra / emballage, jalons de retour,
 *    agence, partenaire, facturation.
 *  · `prix_brut` passe en DECIMAL(14,2) : un montant legacy atteint 672 499 672.
 *  · `legacy_brut` (JSON) : copie des champs legacy sans équivalent direct (qty brut, collected,
 *    invoiced, treated, openpackage…) — aucune valeur d'origine n'est perdue.
 *
 * Additif : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        $index = collect(Schema::getIndexes('commands'))->keyBy('name');
        if ($index->has('commands_code_unique')) {
            Schema::table('commands', fn (Blueprint $table) => $table->dropUnique('commands_code_unique'));
        }
        if (! $index->has('commands_code_index')) {
            Schema::table('commands', fn (Blueprint $table) => $table->index('code', 'commands_code_index'));
        }

        $fkClient = collect(Schema::getForeignKeys('commands'))->first(fn (array $fk) => $fk['columns'] === ['client_id']);
        if ($fkClient !== null && strtolower((string) $fkClient['on_delete']) !== 'set null') {
            Schema::table('commands', fn (Blueprint $table) => $table->dropForeign(['client_id']));
            $fkClient = null;
        }
        Schema::table('commands', function (Blueprint $table) use ($fkClient) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
            if ($fkClient === null) {
                $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        // Montants legacy jusqu'à 672 499 672 (saisie erronée, conservée et signalée) : 10,2 ne suffit pas.
        Schema::table('commands', function (Blueprint $table) {
            $table->decimal('prix_brut', 14, 2)->default(0)->comment('Montant CRBT à encaisser')->change();
        });

        Schema::table('commands', function (Blueprint $table) {
            if (! Schema::hasColumn('commands', 'statut_id')) {
                $table->foreignId('statut_id')->nullable()->after('state_group')->constrained('statuts')->nullOnDelete();
            }
            if (! Schema::hasColumn('commands', 'destinataire_zone_id')) {
                $table->foreignId('destinataire_zone_id')->nullable()->after('destinataire_zone_code')->constrained('zones')->nullOnDelete();
            }
            if (! Schema::hasColumn('commands', 'montant_supplementaire')) {
                $table->decimal('montant_supplementaire', 12, 2)->default(0)->after('prix_brut');
                $table->decimal('montant_emballage', 12, 2)->default(0)->after('montant_supplementaire');
            }
            if (! Schema::hasColumn('commands', 'retour_agence_at')) {
                $table->timestamp('retour_agence_at')->nullable()->after('datereported_at');
                $table->timestamp('retour_recu_client_at')->nullable()->after('retour_agence_at');
            }
            if (! Schema::hasColumn('commands', 'agence_id')) {
                $table->foreignId('agence_id')->nullable()->after('livreur_telephone')->constrained('agences')->nullOnDelete();
                $table->foreignId('partenaire_id')->nullable()->after('agence_id')->constrained('partenaires')->nullOnDelete();
            }
            if (! Schema::hasColumn('commands', 'facture_client')) {
                $table->boolean('facture_client')->default(false)->after('echange');
                $table->boolean('facture_livreur')->default(false)->after('facture_client');
            }
            if (! Schema::hasColumn('commands', 'legacy_brut')) {
                $table->json('legacy_brut')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('statut_id');
            $table->dropConstrainedForeignId('destinataire_zone_id');
            $table->dropConstrainedForeignId('agence_id');
            $table->dropConstrainedForeignId('partenaire_id');
            $table->dropColumn(['montant_supplementaire', 'montant_emballage', 'retour_agence_at', 'retour_recu_client_at',
                'facture_client', 'facture_livreur', 'legacy_brut']);
            $table->dropIndex('commands_code_index');
        });
    }
};
