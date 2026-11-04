<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use App\Domain\Reprise\CarteStatuts;
use Illuminate\Support\Facades\DB;

/**
 * Référentiel des statuts : `trackingstates` (ids conservés) + libellés trouvés sur les colis ou dans
 * l'historique mais absents du référentiel (créés « hors référentiel », inactifs, jamais fusionnés).
 */
final class RepriseStatuts extends EtapeReprise
{
    public static function nom(): string
    {
        return 'statuts';
    }

    public static function domaines(): array
    {
        return ['statuts'];
    }

    public function executer(): array
    {
        $lignes = $this->legacy->table('trackingstates')->orderBy('id')->get();
        $parLibelle = [];
        $referentiel = 0;

        foreach ($lignes as $t) {
            $libelle = trim((string) $t->state);
            $groupe = CarteStatuts::groupe($libelle);
            if ($groupe === null) {
                $this->journal->signaler('statuts', 'groupe_inconnu', 'decision', "Statut « {$libelle} » : groupe à confirmer (classé « en cours »)", 'trackingstates', $t->id, $libelle);
            }
            $parLibelle[$libelle][] = (int) $t->id;
            $referentiel++;

            if (! $this->simulation) {
                DB::table('statuts')->upsert([[
                    'id' => (int) $t->id,
                    'libelle' => $libelle,
                    'couleur' => self::texte($t->color),
                    'groupe' => $groupe ?? 'en_cours',
                    'kpi_legacy' => self::texte($t->kpi),
                    'acteurs_legacy' => self::texte($t->agent),
                    'actif' => (int) $t->trash === 1,
                    'origine' => 'referentiel_legacy',
                    'ordre' => (int) $t->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]], ['id'], ['libelle', 'couleur', 'kpi_legacy', 'acteurs_legacy', 'origine', 'ordre', 'updated_at']);
                // Groupe et activation : posés à la création seulement — un choix fait dans la page
                // « Statuts colis » n'est jamais écrasé par un nouveau passage.
            }
        }

        foreach ($parLibelle as $libelle => $ids) {
            $libelle = (string) $libelle;
            if (count($ids) > 1) {
                $this->journal->signaler('statuts', 'statut_en_double', 'decision', "Le statut « {$libelle} » existe ".count($ids).' fois dans l’ancien référentiel (ids '.implode(', ', $ids).')', 'trackingstates', null, $libelle, ['ids' => $ids]);
            }
        }

        // Libellés réellement utilisés (colis + historique), comparés À L'IDENTIQUE au référentiel.
        // Regroupement SENSIBLE À LA CASSE : sous MySQL, « Pas de réponse 2 fois » et « pas de réponse
        // 2 fois » seraient fusionnés par la collation et l'un des deux ne serait jamais créé.
        $cle = $this->legacy->getDriverName() === 'mysql' ? 'BINARY TRIM(state)' : 'TRIM(state)';
        $usages = [];
        foreach ($this->legacy->table('commands')->selectRaw('TRIM(state) AS s, COUNT(*) AS n')->groupByRaw($cle)->get() as $r) {
            $usages[(string) $r->s]['colis'] = (int) $r->n;
        }
        foreach ($this->legacy->table('commandshistory')->selectRaw('TRIM(state) AS s, COUNT(*) AS n')->groupByRaw($cle)->get() as $r) {
            $usages[(string) $r->s]['historique'] = (int) $r->n;
        }

        $horsReferentiel = 0;
        foreach ($usages as $libelle => $n) {
            $libelle = (string) $libelle;
            if ($libelle === '' || isset($parLibelle[$libelle])) {
                continue;
            }
            $horsReferentiel++;
            $groupe = CarteStatuts::groupe($libelle);
            $colis = $n['colis'] ?? 0;
            $hist = $n['historique'] ?? 0;
            $this->journal->signaler('statuts', 'hors_referentiel', 'decision',
                "Libellé « {$libelle} » absent de l’ancien référentiel ({$colis} colis, {$hist} lignes d’historique) — à fusionner avec un statut existant ?",
                'commands', null, $libelle, ['colis' => $colis, 'historique' => $hist, 'groupe_retenu' => $groupe ?? 'en_cours']);
            if ($groupe === null) {
                $this->journal->signaler('statuts', 'groupe_inconnu', 'decision', "Statut « {$libelle} » : groupe à confirmer (classé « en cours »)", 'commands', null, $libelle);
            }

            $existe = DB::table('statuts')->whereNull('deleted_at')->pluck('libelle')->contains(fn ($l) => $l === $libelle);
            if (! $this->simulation && ! $existe) {
                DB::table('statuts')->insert([
                    'libelle' => $libelle,
                    'groupe' => $groupe ?? 'en_cours',
                    'actif' => false,
                    'origine' => 'hors_referentiel',
                    'ordre' => 1000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach ($usages as $libelle => $n) {
            if ((string) $libelle === '') {
                $this->journal->signaler('statuts', 'statut_vide', 'bloquant', 'Statut vide sur '.($n['colis'] ?? 0).' colis / '.($n['historique'] ?? 0).' lignes d’historique', 'commands', null, '', $n);
            }
        }

        if (! $this->simulation && DB::getSchemaBuilder()->hasTable('etat_groupe_etats')) {
            $rattaches = DB::table('etat_groupe_etats')->where('axe', 'flux')->pluck('etat_id')->all();
            foreach (DB::table('statuts')->whereNotIn('id', $rattaches)->get(['id', 'groupe']) as $s) {
                \App\Domain\StatutsColis\ClassificationEtats::rattacherParCode((string) $s->groupe, (int) $s->id);
            }
        }

        $this->journal->cloturer(self::domaines());

        return ['referentiel' => $referentiel, 'hors_referentiel' => $horsReferentiel];
    }

    /**
     * Libellé → id de statut : correspondance EXACTE ; à libellé égal, statut actif puis plus petit id.
     *
     * @return array<string, int>
     */
    public static function carte(): array
    {
        $carte = [];
        foreach (DB::table('statuts')->orderByDesc('actif')->orderBy('id')->get(['id', 'libelle']) as $s) {
            $carte[$s->libelle] ??= (int) $s->id;
        }

        return $carte;
    }
}
