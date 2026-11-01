<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Tarifs\Backfill\RapprochementLivreurFees;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rapprochement des commissions livreurs legacy (stockout.shippingfees) vers
 * `livreur_tarifs` (dlm > 0) et `livreur_standard_tarifs` (dlm = 0).
 *
 * SIMULATION PAR DÉFAUT. --apply refusé en production sans --force. Les conflits ne
 * sont jamais écrasés. Ne touche JAMAIS `commands` (aucun UPDATE rétroactif de
 * `frais_livraison_livreur` : hors autorisation, audit séparé requis).
 */
class BackfillLivreurFeesCommand extends Command
{
    protected $signature = 'tarifs:backfill-livreur-fees
        {--connection=legacy_stockout : Connexion Laravel de la base legacy (lecture seule)}
        {--apply : Écrire réellement (sinon simulation)}
        {--restaurer : Avec --apply, restaurer les commissions supprimées dans Zouhir}
        {--details : Lister chaque ligne (ID legacy, livreur, ville, montants, statut, décision)}
        {--force : Autoriser --apply en environnement production}';

    protected $description = 'Rapproche les commissions livreurs legacy (shippingfees) — simulation par défaut';

    public function handle(RapprochementLivreurFees $rapprochement): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply && app()->environment('production') && ! $this->option('force')) {
            $this->error('--apply refusé en production sans --force (autorisation explicite du propriétaire requise).');

            return self::FAILURE;
        }

        $this->info('=== Commissions livreurs : rapprochement legacy shippingfees ===');
        $this->line($apply ? '<fg=red>MODE ÉCRITURE</>' : '<fg=yellow>MODE SIMULATION — aucune écriture</>');

        try {
            $a = $rapprochement->analyser(DB::connection((string) $this->option('connection')));
        } catch (Throwable $e) {
            $this->error('Analyse impossible : '.$e->getMessage());

            return self::FAILURE;
        }

        $s = $a['synthese'];
        $fmt = fn (array $d) => collect($d)->map(fn ($n, $m) => "{$n}×{$m}")->implode(', ') ?: '—';
        $this->line('Colonne legacy refusedfees : '.($a['colonne_refus'] ? 'présente' : 'ABSENTE'));
        $this->line('Contrôles (rapport #30, à revérifier) :');
        $this->table(['Contrôle', 'Valeur'], [
            ['Lignes legacy', $s['lignes']],
            ['  · actives / archivées', "{$s['actives']} / {$s['archivees']}"],
            ['PASS 1 — spécifiques (dlm > 0) actives / archivées', "{$s['specifiques_actives']} / {$s['specifiques_archivees']}"],
            ['  · livreurs legacy / identifiés / dont archivés', "{$s['livreurs_legacy']} / {$s['livreurs_identifies']} / {$s['livreurs_archives']}"],
            ['  · villes (libellés legacy / villes Zouhir)', "{$s['villes_specifiques_legacy']} / {$s['villes_specifiques_zouhir']}"],
            ['  · règles uniques', $s['regles_specifiques_uniques']],
            ['  · commission livraison min / max / moyenne', ($s['commission_min'] ?? '—').' / '.($s['commission_max'] ?? '—').' / '.($s['commission_moyenne'] ?? '—')],
            ['  · refus (lignes actives)', $fmt($s['refus_specifiques'])],
            ['PASS 2 — standards (dlm = 0) actives / archivées', "{$s['standards_actives']} / {$s['standards_archivees']}"],
            ['  · villes (libellés legacy / villes retenues)', "{$s['standards_villes_legacy']} / {$s['standards_villes_zouhir']}"],
            ['  · montants livraison (lignes)', $fmt($s['standards_montants'])],
            ['  · doublons identiques fusionnés', $s['standards_doublons']],
            ['  · villes en conflit de montants (non migrées)', $s['standards_conflits_villes']],
            ['Villes à libellés multiples (variantes)', $s['variantes_villes']],
            ['Conflits avec Zouhir (jamais écrasés)', $s['conflits_zouhir']],
        ]);

        $this->line('PASS 1 — statuts (spécifiques) :');
        $this->table(['Statut', 'Lignes'], collect($a['compte_specifiques'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->line('PASS 2/3 — statuts (standards) :');
        $this->table(['Statut', 'Lignes'], collect($a['compte_standards'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->line('Identité livreur (méthode) :');
        $this->table(['Méthode', 'Lignes'], collect($a['livreurs'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->line('Rapprochement ville (méthode) :');
        $this->table(['Méthode', 'Lignes'], collect($a['villes'])->map(fn ($n, $k) => [$k, $n])->values()->all());

        $restaurer = (bool) $this->option('restaurer');
        $aEcrire = count(array_filter($a['specifiques'], fn ($l) => in_array($l['statut'], ['a_creer', 'a_creer_corbeille'], true)))
            + ($restaurer ? count(array_filter($a['specifiques'], fn ($l) => $l['statut'] === 'a_restaurer')) : 0);
        $aEcrireStd = count(array_filter($a['standards'], fn ($l) => $l['statut'] === 'a_creer'));
        $this->info("Total qui serait écrit : {$aEcrire} commission(s) spécifique(s), {$aEcrireStd} standard(s).");

        if ($this->option('details')) {
            $ligne = fn ($l, string $type) => [
                implode('+', $l['legacy_ids']), $type,
                $type === 'standard' ? '—' : $l['legacy_dlm'].(($l['livreur_archive'] ?? false) ? ' (archivé)' : ''),
                $l['legacy_ville'], $l['ville_id'] ?? '—',
                $l['livraison'] ?? '—', $l['refus'] ?? '—',
                $l['corbeille'] ? 'corbeille' : 'active',
                $l['statut'], $l['detail'] ?? '',
            ];
            $this->table(
                ['Legacy #', 'Type', 'Livreur', 'Ville legacy', 'Ville Zouhir', 'Livraison', 'Refus', 'État', 'Décision', 'Détail'],
                array_merge(
                    array_map(fn ($l) => $ligne($l, 'specifique'), $a['specifiques']),
                    array_map(fn ($l) => $ligne($l, 'standard'), $a['standards']),
                ),
            );
        }

        if (! $apply) {
            $this->warn('Simulation terminée. Aucune écriture. --apply exige une autorisation distincte du propriétaire.');

            return self::SUCCESS;
        }

        $r = $rapprochement->appliquer($a['specifiques'], $a['standards'], $restaurer);
        $this->info(sprintf('Écrit : %d créée(s) actives, %d créée(s) en corbeille, %d restaurée(s), %d standard(s) créé(s).',
            $r['crees'], $r['crees_corbeille'], $r['restaurees'], $r['standards_crees']));

        return self::SUCCESS;
    }
}
