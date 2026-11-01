<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Tarifs\Backfill\RapprochementClientFees;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rapprochement des tarifs clients legacy (stockout.clientfees) vers `client_tarifs`.
 *
 * SIMULATION PAR DÉFAUT : sans --apply, aucune écriture. Avec --apply, seules les
 * lignes `a_creer` (et `a_restaurer` avec --restaurer) sont écrites, en une
 * transaction ; les conflits ne sont JAMAIS écrasés. Ne touche jamais `commands`.
 */
class BackfillClientFeesCommand extends Command
{
    protected $signature = 'tarifs:backfill-client-fees
        {--connection=legacy_stockout : Connexion Laravel de la base legacy (lecture seule)}
        {--standard : Analyser aussi le barème général legacy (gshippingfees vers standard_tarifs)}
        {--apply : Écrire réellement (sinon simulation)}
        {--restaurer : Avec --apply, restaurer les tarifs supprimés dans Zouhir}
        {--details : Lister chaque ligne non migrable ou en conflit}
        {--force : Autoriser --apply en environnement production}';

    protected $description = 'Rapproche les tarifs legacy (clientfees) avec client_tarifs — simulation par défaut';

    public function handle(RapprochementClientFees $rapprochement): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply && app()->environment('production') && ! $this->option('force')) {
            $this->error('--apply refusé en production sans --force (autorisation explicite du propriétaire requise).');

            return self::FAILURE;
        }

        $this->info('=== Tarifs clients : rapprochement legacy → client_tarifs ===');
        $this->line($apply ? '<fg=red>MODE ÉCRITURE</>' : '<fg=yellow>MODE SIMULATION — aucune écriture</>');

        try {
            $legacy = DB::connection((string) $this->option('connection'));
            $analyse = $rapprochement->analyserClientFees($legacy);
            $standards = $this->option('standard') ? $rapprochement->analyserStandards($legacy) : null;
        } catch (Throwable $e) {
            $this->error('Analyse impossible : '.$e->getMessage());
            $this->line('Vérifier les variables LEGACY_DB_HOST / LEGACY_DB_DATABASE / LEGACY_DB_USERNAME / LEGACY_DB_PASSWORD du .env (lecture seule).');

            return self::FAILURE;
        }

        $lignes = $analyse['lignes'];
        $this->line(sprintf('Colonnes legacy : refusedfees %s, returnedfees %s.',
            $analyse['colonnes_frais']['refus'] ? 'présente' : 'ABSENTE',
            $analyse['colonnes_frais']['retour'] ? 'présente' : 'ABSENTE'));

        $sy = $analyse['synthese'];
        $cat = $sy['categories'];
        $fmt = fn (array $d) => collect($d)->map(fn ($n, $m) => "{$n} à {$m}")->implode(', ') ?: '—';

        $this->line('Contrôles (rapport #35, décision 8) :');
        $this->table(['Contrôle', 'Valeur'], [
            ['Lignes legacy examinées', $sy['lignes_examinees']],
            ['Règles uniques attendues', $sy['regles_uniques']],
            ['  · simples', $cat['simples']],
            ['  · villes homonymes (plus petit id)', $cat['homonymes']],
            ['  · villes archivées', $cat['villes_archivees']],
            ['  · clients archivés', $cat['clients_archives']],
            ['Doublons identiques neutralisés', $sy['neutralisees']],
            ['Clients legacy / identifiés / dont archivés', "{$sy['clients_legacy']} / {$sy['clients_identifies']} / {$sy['clients_archives']}"],
            ['Refus (lignes legacy)', $fmt($sy['refus_lignes_legacy'])],
            ['Retours (lignes legacy)', $fmt($sy['retour_lignes_legacy'])],
            ['Conflits avec Zouhir (jamais écrasés)', $sy['conflits']],
        ]);

        $this->line('Statuts :');
        $this->table(['Statut', 'Lignes'], collect($analyse['compte'])->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->line('Identité client (méthode) :');
        $this->table(['Méthode', 'Lignes'], collect($analyse['clients'])->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->line('Rapprochement ville (méthode) :');
        $this->table(['Méthode', 'Lignes'], collect($analyse['villes'])->map(fn ($n, $s) => [$s, $n])->values()->all());

        $aEcrire = count(array_filter($lignes, fn ($l) => in_array($l['statut'], ['a_creer', 'a_creer_corbeille'], true)))
            + ($this->option('restaurer') ? count(array_filter($lignes, fn ($l) => $l['statut'] === 'a_restaurer')) : 0);
        $this->info("Total qui serait écrit : {$aEcrire} ligne(s).");

        if ($standards !== null) {
            $this->line('Barème général (gshippingfees vers standard_tarifs) :');
            $this->table(['Statut', 'Lignes'], collect($standards['compte'])->map(fn ($n, $s) => [$s, $n])->values()->all());
        }

        if ($this->option('details')) {
            // Lignes non migrées, en conflit, neutralisées, ou migrées avec une note (homonymes, archives).
            $notables = array_filter($lignes, fn ($l) => $l['statut'] !== 'identique'
                && ($l['statut'] !== 'a_creer' || ! empty($l['detail']) || $l['client_archive'] || count($l['legacy_ids']) > 1));
            $this->table(
                ['Legacy #', 'Client', 'Ville legacy', 'Ville Zouhir', 'Statut', 'Détail'],
                array_map(fn ($l) => [
                    implode('+', $l['legacy_ids']),
                    $l['legacy_client'].($l['client_archive'] ? ' (archivé)' : ''),
                    $l['legacy_ville'],
                    $l['ville_id'] ?? '—',
                    $l['statut'],
                    $l['detail'] ?? '',
                ], $notables),
            );
        }

        if (! $apply) {
            $this->warn('Simulation terminée. Relancer avec --apply (après validation) pour écrire.');

            return self::SUCCESS;
        }

        $r = $rapprochement->appliquer($lignes, (bool) $this->option('restaurer'), $standards['lignes'] ?? []);
        $this->info(sprintf('Écrit : %d créé(s) actifs, %d créé(s) en corbeille, %d restauré(s), %d standard(s) créé(s).', $r['crees'], $r['crees_corbeille'], $r['restaurees'], $r['standards_crees']));

        return self::SUCCESS;
    }
}
