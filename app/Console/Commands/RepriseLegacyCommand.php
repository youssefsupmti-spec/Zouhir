<?php

namespace App\Console\Commands;

use App\Domain\Reprise\Etapes\AuditReferentiels;
use App\Domain\Reprise\Etapes\EtapeReprise;
use App\Domain\Reprise\Etapes\RepriseBons;
use App\Domain\Reprise\Etapes\RepriseColis;
use App\Domain\Reprise\Etapes\RepriseEquipes;
use App\Domain\Reprise\Etapes\RepriseHistorique;
use App\Domain\Reprise\Etapes\RepriseNotes;
use App\Domain\Reprise\Etapes\RepriseStatuts;
use App\Domain\Reprise\JournalAnomalies;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reprise EXACTE de l'ancienne base (lecture seule) vers le nouveau projet, rejouable.
 *
 *   php artisan reprise:legacy --simulation        # compte tout, n'écrit rien
 *   php artisan reprise:legacy                     # toutes les étapes, dans l'ordre
 *   php artisan reprise:legacy --etape=bons        # une étape (répétable)
 *
 * Ordre : statuts → equipes → colis → historique → notes → bons → controles.
 * Ce qui ne se reprend pas exactement est listé dans `reprise_anomalies` (page « Reprise des
 * données ») et dans le rapport produit par `reprise:rapport`.
 */
class RepriseLegacyCommand extends Command
{
    protected $signature = 'reprise:legacy
                            {--etape=* : statuts, equipes, colis, historique, notes, bons, controles (défaut : toutes)}
                            {--simulation : lire et compter sans rien écrire}';

    protected $description = 'Reprise exacte de l’ancienne base OUAISS EXPRESS (lecture seule) avec journal des anomalies';

    /** @var list<class-string<EtapeReprise>> */
    public const ETAPES = [
        RepriseStatuts::class,
        RepriseEquipes::class,
        RepriseColis::class,
        RepriseHistorique::class,
        RepriseNotes::class,
        RepriseBons::class,
        AuditReferentiels::class,
    ];

    public function handle(): int
    {
        $simulation = (bool) $this->option('simulation');
        $demandees = array_map('strtolower', (array) $this->option('etape'));
        $connexion = (string) config('reprise.connexion');

        try {
            $legacy = DB::connection($connexion);
            $legacy->getPdo();
        } catch (\Throwable $e) {
            $this->error("Connexion legacy « {$connexion} » indisponible : ".$e->getMessage());
            $this->line('Renseigner LEGACY_DB_HOST / LEGACY_DB_DATABASE / LEGACY_DB_USERNAME / LEGACY_DB_PASSWORD dans le .env.');

            return self::FAILURE;
        }

        $inconnues = array_diff($demandees, array_map(fn ($e) => $e::nom(), self::ETAPES));
        if ($inconnues !== []) {
            $this->error('Étape(s) inconnue(s) : '.implode(', ', $inconnues));

            return self::FAILURE;
        }

        $this->info('Reprise legacy'.($simulation ? ' — SIMULATION (aucune écriture)' : '').' — source : '.$legacy->getDatabaseName().' (lecture seule)');
        $journal = new JournalAnomalies($simulation);

        foreach (self::ETAPES as $classe) {
            if ($demandees !== [] && ! in_array($classe::nom(), $demandees, true)) {
                continue;
            }
            $debut = microtime(true);
            $this->line("» {$classe::nom()}");
            $etape = new $classe($legacy, $journal, $simulation, fn (string $m) => $this->line($m));
            $chiffres = $etape->executer();
            $this->line('  '.json_encode($chiffres, JSON_UNESCAPED_UNICODE).' — '.round(microtime(true) - $debut, 1).' s');
        }

        $journal->vider();
        $this->newLine();
        $this->info('Anomalies constatées par ce passage :');
        foreach ($journal->compteurs() as $domaine => $codes) {
            foreach ($codes as $code => $n) {
                $this->line("  {$domaine} · {$code} : {$n}");
            }
        }

        return self::SUCCESS;
    }
}
