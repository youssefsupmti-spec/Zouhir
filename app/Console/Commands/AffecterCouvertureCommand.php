<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Reseau\AffectationCouverture;
use Illuminate\Console\Command;

/**
 * Remplit le Coverage Board à partir de l'historique des livraisons (titulaire, remplaçant, capacité, partenaire).
 * SIMULATION PAR DÉFAUT ; --apply refusé en production sans --force. N'écrase jamais une couverture déjà renseignée.
 */
class AffecterCouvertureCommand extends Command
{
    protected $signature = 'reseau:affecter-couverture
        {--apply : Écrire réellement (sinon simulation)}
        {--force : Autoriser --apply en environnement production}';

    protected $description = 'Affecte la couverture des zones d\'après l\'historique réel — simulation par défaut';

    public function handle(AffectationCouverture $affectation): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && app()->environment('production') && ! $this->option('force')) {
            $this->error('--apply refusé en production sans --force (autorisation explicite du propriétaire requise).');

            return self::FAILURE;
        }

        $this->info('=== Coverage Board : affectation d\'après l\'historique ===');
        $this->line($apply ? '<fg=red>MODE ÉCRITURE</>' : '<fg=yellow>MODE SIMULATION — aucune écriture</>');

        $a = $affectation->analyser();
        if (! $a['fin']) {
            $this->warn('Aucune livraison dans la base : rien à proposer.');

            return self::SUCCESS;
        }
        $this->line("Fenêtre : {$a['debut']} → {$a['fin']} (".AffectationCouverture::FENETRE_JOURS.' j), seuil '.AffectationCouverture::SEUIL.' colis.');

        $this->table(['Zone', 'Type', 'Action', 'Titulaire', 'Remplaçant', 'Partenaire', 'Capacité/j', 'Raison'], array_map(fn ($l) => [
            $l['code'], $l['type'], $l['action'],
            $l['titulaire'] ? "#{$l['titulaire']['id']} {$l['titulaire']['nom']} ({$l['titulaire']['livres']})" : '—',
            $l['backup'] ? "#{$l['backup']['id']} {$l['backup']['nom']} ({$l['backup']['livres']})" : '—',
            $l['partenaire'] ? "#{$l['partenaire']['id']} {$l['partenaire']['nom']} ({$l['partenaire']['colis']})" : '—',
            $l['capacite'] ?? '—', $l['raison'],
        ], $a['lignes']));

        if (! $apply) {
            $this->comment('Simulation terminée. Relancer avec --apply pour écrire.');

            return self::SUCCESS;
        }

        $r = $affectation->appliquer($a);
        $this->info("Écrit : {$r['affectees']} zone(s) affectée(s), {$r['rattachements_livreurs']} rattachement(s) livreur/zone, "
            ."{$r['rattachements_partenaires']} rattachement(s) partenaire/zone.");

        return self::SUCCESS;
    }
}
