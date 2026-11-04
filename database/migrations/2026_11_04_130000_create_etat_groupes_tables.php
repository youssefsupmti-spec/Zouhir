<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classification des états (même structure que WinningExpress / Zakaria2) :
 *
 *  · etat_groupes      : groupes d'état par axe (« flux » = onglets de la page Colis) — code, nom,
 *                        description, couleur, ordre, actif, suivi.
 *  · etat_groupe_etats : rattachement d'un état (statuts.id, équivalent de trackingstates) à UN groupe
 *                        par axe (unique axe + etat_id).
 *
 * Reprise : les 8 groupes système (nouveau, en cours, planifié, livré, échec, retour agence,
 * retour stock, retour client), puis chaque statut rattaché à son groupe : table explicite
 * CarteStatuts pour les statuts de l'ancien site, groupe actuel sinon. Les copies dénormalisées
 * (statuts.groupe, commands.state_group) sont recalculées. Rejouable, additif.
 */
return new class extends Migration
{
    private const AXE = 'flux';

    /** code, nom, couleur, description */
    private const GROUPES = [
        ['nouveau', 'Nouveau', '#F59E0B', 'Colis enregistrés, pas encore pris en charge'],
        ['en_cours', 'En cours', '#0EA5E9', 'Ramassés, en distribution ou en voyage'],
        ['planifie', 'Planifié', '#7C3AED', 'Programmés / reportés à une date convenue'],
        ['livre', 'Livré', '#10B981', 'Livrés au destinataire'],
        ['echec', 'Échouées', '#CC0000', 'Annulés, refusés, injoignables, hors zone…'],
        ['retour_agence', 'Retour agence', '#F97316', 'En retour vers l’agence / le hub'],
        ['retour_stock', 'Retour stock', '#A16207', 'Remis en stock'],
        ['retour_client', 'Retour client', '#8B5CF6', 'Rendus au marchand'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('etat_groupes')) {
            Schema::create('etat_groupes', function (Blueprint $table) {
                $table->id();
                $table->string('axe', 40)->default(self::AXE);
                $table->string('code', 40);
                $table->string('nom', 120);
                $table->text('description')->nullable();
                $table->string('couleur', 32)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('actif')->default(true);
                $table->boolean('suivi_actif')->default(false);
                $table->boolean('suivi_planifie')->default(false);
                $table->timestamps();

                $table->unique(['axe', 'code']);
                $table->index(['axe', 'sort_order']);
            });
        }

        if (! Schema::hasTable('etat_groupe_etats')) {
            Schema::create('etat_groupe_etats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('groupe_id')->constrained('etat_groupes')->cascadeOnDelete();
                $table->string('axe', 40)->default(self::AXE);
                $table->foreignId('etat_id')->constrained('statuts')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['axe', 'etat_id']);
                $table->index('groupe_id');
            });
        }

        $now = now();
        $ids = [];
        foreach (self::GROUPES as $rang => [$code, $nom, $couleur, $description]) {
            $id = DB::table('etat_groupes')->where('axe', self::AXE)->where('code', $code)->value('id');
            if ($id === null) {
                $id = DB::table('etat_groupes')->insertGetId([
                    'axe' => self::AXE, 'code' => $code, 'nom' => $nom, 'description' => $description, 'couleur' => $couleur,
                    'sort_order' => $rang + 1, 'actif' => true,
                    'suivi_actif' => in_array($code, ['en_cours', 'planifie'], true), 'suivi_planifie' => $code === 'planifie',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $ids[$code] = (int) $id;
        }

        if (! Schema::hasTable('statuts')) {
            return;
        }

        foreach (DB::table('statuts')->get(['id', 'libelle', 'groupe', 'origine']) as $s) {
            if (DB::table('etat_groupe_etats')->where('axe', self::AXE)->where('etat_id', $s->id)->exists()) {
                continue;
            }
            $code = in_array($s->origine, ['referentiel_legacy', 'hors_referentiel'], true)
                ? (\App\Domain\Reprise\CarteStatuts::groupe((string) $s->libelle) ?? $s->groupe)
                : $s->groupe;
            $code = match ($code) { 'annule' => 'echec', 'retour' => 'retour_client', default => $code };
            if (! isset($ids[$code])) {
                continue;
            }
            DB::table('etat_groupe_etats')->insert(['groupe_id' => $ids[$code], 'axe' => self::AXE, 'etat_id' => $s->id, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('statuts')->where('id', $s->id)->update(['groupe' => $code]);
        }

        // Colis : groupe recopié depuis leur statut ; sans statut, ancien code converti.
        foreach (DB::table('statuts')->get(['id', 'groupe']) as $s) {
            DB::table('commands')->where('statut_id', $s->id)->where('state_group', '<>', $s->groupe)->update(['state_group' => $s->groupe]);
        }
        DB::table('commands')->where('state_group', 'retour')->update(['state_group' => 'retour_client']);
        DB::table('commands')->where('state_group', 'annule')->update(['state_group' => 'echec']);
    }

    public function down(): void
    {
        Schema::dropIfExists('etat_groupe_etats');
        Schema::dropIfExists('etat_groupes');
    }
};
