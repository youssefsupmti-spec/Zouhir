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
        {--standard : Analyser aussi le barème général legacy (gshippingfees → standard_tarifs)}
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

        $actives = array_filter($lignes, fn ($l) => $l['statut'] !== 'archivee_legacy');
        $this->table(['Indicateur', 'Valeur'], [
            ['Lignes legacy lues', count($lignes)],
            ['Règles actives (trash = 1)', count($actives)],
            ['Clients legacy distincts (actifs)', count(array_unique(array_column($actives, 'legacy_client')))],
            ['Clients identifiés', count(array_unique(array_filter(array_column($actives, 'client_id'))))],
            ['Villes legacy distinctes (actives)', count(array_unique(array_column($actives, 'legacy_ville')))],
            ['Villes identifiées', count(array_unique(array_filter(array_column($actives, 'ville_id'))))],
        ]);

        $this->line('Statuts :');
        $this->table(['Statut', 'Lignes'], collect($analyse['compte'])->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->line('Identité client (méthode) :');
        $this->table(['Méthode', 'Lignes'], collect($analyse['clients'])->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->line('Rapprochement ville (méthode) :');
        $this->table(['Méthode', 'Lignes'], collect($analyse['villes'])->map(fn ($n, $s) => [$s, $n])->values()->all());

        $aEcrire = count(array_filter($lignes, fn ($l) => $l['statut'] === 'a_creer'))
            + ($this->option('restaurer') ? count(array_filter($lignes, fn ($l) => $l['statut'] === 'a_restaurer')) : 0);
        $this->info("Total qui serait écrit : {$aEcrire} ligne(s).");

        if ($standards !== null) {
            $this->line('Barème général (gshippingfees → standard_tarifs) :');
            $this->table(['Statut', 'Lignes'], collect($standards['compte'])->map(fn ($n, $s) => [$s, $n])->values()->all());
        }

        if ($this->option('details')) {
            $problemes = array_filter($lignes, fn ($l) => ! in_array($l['statut'], ['a_creer', 'identique', 'doublon_identique'], true));
            $this->table(
                ['Legacy #', 'Client', 'Ville legacy', 'Statut', 'Détail'],
                array_map(fn ($l) => [$l['legacy_id'], $l['legacy_client'], $l['legacy_ville'], $l['statut'], $l['detail'] ?? ''], $problemes),
            );
        }

        if (! $apply) {
            $this->warn('Simulation terminée. Relancer avec --apply (après validation) pour écrire.');

            return self::SUCCESS;
        }

        $r = $rapprochement->appliquer($lignes, (bool) $this->option('restaurer'), $standards['lignes'] ?? []);
        $this->info(sprintf('Écrit : %d créé(s), %d restauré(s), %d standard(s) créé(s).', $r['crees'], $r['restaurees'], $r['standards_crees']));

        return self::SUCCESS;
    }
}
