<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des statuts, messages de notes et journal des anomalies de reprise (C140) — additif.
 *
 *  · command_status_histories : une ligne par changement d'état. `legacy_history_id` = id de
 *    `commandshistory` (rejouable sans doublon). `command_id` nullable : une ligne legacy dont le
 *    code ne désigne aucun colis, ou plusieurs (code en double), est gardée telle quelle.
 *  · note_messages : `commands.note` découpée en messages (auteur, rôle, date si elle est écrite
 *    dans la note). La note d'origine reste intacte sur le colis.
 *  · reprise_anomalies : tout ce que la reprise n'a pas pu rattacher EXACTEMENT, pour décision.
 *    `empreinte` rend la reprise rejouable : une anomalie déjà tranchée garde sa décision.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('command_status_histories')) {
            Schema::create('command_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('command_id')->nullable()->constrained('commands')->nullOnDelete();
                $table->string('command_code')->nullable()->index();
                $table->string('from_state')->nullable();
                $table->string('to_state');
                $table->foreignId('to_statut_id')->nullable()->constrained('statuts')->nullOnDelete();
                // livreur | moderateur | client | systeme
                $table->string('changed_by_type', 30)->nullable();
                $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('changed_by_nom')->nullable();
                // legacy | application | api
                $table->string('change_source', 20)->default('application');
                $table->string('reason', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('legacy_history_id')->nullable()->unique();
                $table->timestamp('created_at')->nullable();

                $table->index(['command_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('note_messages')) {
            Schema::create('note_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('command_id')->constrained('commands')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                // livreur | moderateur | client | externe | inconnu
                $table->string('role', 30);
                $table->string('auteur_nom')->nullable();
                $table->text('message');
                $table->unsignedSmallInteger('position')->default(0);
                // legacy | application
                $table->string('source', 20)->default('application');
                // la date vient-elle de la note elle-même (et non d'une déduction) ?
                $table->boolean('date_exacte')->default(true);
                $table->timestamps();

                $table->index(['command_id', 'created_at']);
                $table->unique(['command_id', 'source', 'position'], 'note_messages_command_source_position_unique');
            });
        }

        if (! Schema::hasTable('reprise_anomalies')) {
            Schema::create('reprise_anomalies', function (Blueprint $table) {
                $table->id();
                // commandes | statuts | villes | zones | clients | livreurs | bons_livraison | bons_envoi
                // | bons_ramassage | bons_retour | historique | notes | banques
                $table->string('domaine', 30)->index();
                $table->string('code', 60)->index();
                // info | attention | bloquant | decision
                $table->string('gravite', 20)->default('attention')->index();
                $table->string('libelle');
                $table->string('legacy_table', 40)->nullable();
                $table->string('legacy_id', 64)->nullable()->index();
                $table->string('cible_table', 40)->nullable();
                $table->unsignedBigInteger('cible_id')->nullable();
                $table->text('valeur_legacy')->nullable();
                $table->json('details')->nullable();
                $table->string('empreinte', 64)->unique();
                // ouverte | resolue | ignoree
                $table->string('statut', 20)->default('ouverte')->index();
                $table->text('decision')->nullable();
                $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('traite_le')->nullable();
                $table->timestamp('vue_le')->nullable();
                $table->timestamps();

                $table->index(['domaine', 'statut']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reprise_anomalies');
        Schema::dropIfExists('note_messages');
        Schema::dropIfExists('command_status_histories');
    }
};
