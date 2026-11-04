<?php

use App\Domain\Reprise\CarteStatuts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Groupes d'état alignés sur « Tous les colis » de WinningExpress / Zakaria2 :
 * nouveau · en cours · PLANIFIÉ (Programmé, Reporté) · livré · retour · ÉCHEC (ex-« annule » :
 * annulé, refusé, pas de réponse, injoignable, hors zone…).
 *
 *  1. statuts repris de l'ancien site : groupe recalculé avec la table explicite CarteStatuts ;
 *     statuts créés dans la plateforme : « annule » → « echec » seulement.
 *  2. colis : groupe recopié depuis leur statut (statut_id) ; sans statut, « annule » → « echec ».
 *
 * Données de classement uniquement (aucune valeur d'origine modifiée), rejouable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statuts')) {
            return;
        }

        foreach (DB::table('statuts')->get(['id', 'libelle', 'groupe', 'origine']) as $s) {
            $groupe = in_array($s->origine, ['referentiel_legacy', 'hors_referentiel'], true)
                ? (CarteStatuts::groupe((string) $s->libelle) ?? ($s->groupe === 'annule' ? 'echec' : $s->groupe))
                : ($s->groupe === 'annule' ? 'echec' : $s->groupe);
            if ($groupe !== $s->groupe) {
                DB::table('statuts')->where('id', $s->id)->update(['groupe' => $groupe, 'updated_at' => now()]);
            }
        }

        foreach (DB::table('statuts')->get(['id', 'groupe']) as $s) {
            DB::table('commands')->where('statut_id', $s->id)->where('state_group', '<>', $s->groupe)->update(['state_group' => $s->groupe]);
        }

        DB::table('commands')->where('state_group', 'annule')->update(['state_group' => 'echec']);
    }

    public function down(): void
    {
        DB::table('commands')->whereIn('state_group', ['echec'])->update(['state_group' => 'annule']);
        DB::table('commands')->where('state_group', 'planifie')->update(['state_group' => 'en_cours']);
        if (Schema::hasTable('statuts')) {
            DB::table('statuts')->where('groupe', 'echec')->update(['groupe' => 'annule']);
            DB::table('statuts')->where('groupe', 'planifie')->update(['groupe' => 'en_cours']);
        }
    }
};
