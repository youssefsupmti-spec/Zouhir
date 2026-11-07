<?php

namespace App\Console\Commands;

use App\Models\RepriseAnomalie;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapport des anomalies de reprise : Markdown (synthèse + détail) et CSV (toutes les lignes).
 *
 *   php artisan reprise:rapport --sortie=storage/app/reprise
 */
class RepriseRapportCommand extends Command
{
    protected $signature = 'reprise:rapport {--sortie=storage/app/reprise : dossier de sortie}';

    protected $description = 'Écrit le rapport (Markdown + CSV) des données non reprises exactement';

    public function handle(): int
    {
        $dossier = rtrim((string) $this->option('sortie'), '/\\');
        if (! is_dir($dossier) && ! mkdir($dossier, 0775, true) && ! is_dir($dossier)) {
            $this->error("Dossier {$dossier} impossible à créer.");

            return self::FAILURE;
        }

        $md = self::markdown();
        file_put_contents("{$dossier}/RAPPORT-REPRISE-ANOMALIES.md", $md);

        $csv = fopen("{$dossier}/reprise-anomalies.csv", 'w');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['id', 'domaine', 'code', 'gravite', 'statut', 'libelle', 'legacy_table', 'legacy_id', 'valeur_legacy', 'cible_table', 'cible_id', 'decision'], ';');
        foreach (DB::table('reprise_anomalies')->orderBy('domaine')->orderBy('code')->orderBy('id')->cursor() as $a) {
            fputcsv($csv, [$a->id, $a->domaine, $a->code, $a->gravite, $a->statut, $a->libelle, $a->legacy_table, $a->legacy_id, $a->valeur_legacy, $a->cible_table, $a->cible_id, $a->decision], ';');
        }
        fclose($csv);

        $this->info("Rapport écrit dans {$dossier}");

        return self::SUCCESS;
    }

    public static function markdown(): string
    {
        $domaines = RepriseAnomalie::DOMAINES;
        $gravites = RepriseAnomalie::GRAVITES;
        $l = [];
        $l[] = '# Reprise de l’ancienne base — données non reprises exactement';
        $l[] = '';
        $l[] = 'Généré le '.now()->format('d/m/Y H:i').'. Source : ancienne base (lecture seule). Chaque ligne est aussi visible, avec sa décision, dans la page *Reprise des données*.';
        $l[] = '';
        $l[] = '## Volumes repris';
        $l[] = '';
        $l[] = '| Table | Lignes |';
        $l[] = '|---|---:|';
        foreach (['commands' => 'Colis', 'statuts' => 'Statuts', 'command_status_histories' => 'Historique', 'note_messages' => 'Messages de notes',
            'bons_livraison' => 'Bons de livraison', 'bons_envoi' => 'Bons d’envoi', 'bon_ramassage' => 'Bons de ramassage', 'bons_retour' => 'Bons de retour',
            'agence_membres' => 'Livreurs rattachés', 'delivery_partners' => 'Partenaires'] as $table => $nom) {
            $l[] = "| {$nom} | ".number_format(DB::table($table)->count(), 0, ',', ' ').' |';
        }
        $l[] = '';
        $l[] = '## Synthèse des anomalies';
        $l[] = '';
        $l[] = '| Domaine | Code | Gravité | Ouvertes | Traitées |';
        $l[] = '|---|---|---|---:|---:|';
        $synthese = DB::table('reprise_anomalies')
            ->selectRaw("domaine, code, gravite, SUM(CASE WHEN statut = 'ouverte' THEN 1 ELSE 0 END) AS ouvertes, SUM(CASE WHEN statut <> 'ouverte' THEN 1 ELSE 0 END) AS traitees")
            ->groupBy('domaine', 'code', 'gravite')->orderBy('domaine')->orderBy('code')->get();
        foreach ($synthese as $s) {
            $l[] = '| '.($domaines[$s->domaine] ?? $s->domaine)." | `{$s->code}` | ".($gravites[$s->gravite] ?? $s->gravite)." | {$s->ouvertes} | {$s->traitees} |";
        }
        if ($synthese->isEmpty()) {
            $l[] = '| — | — | — | 0 | 0 |';
        }

        foreach ($domaines as $cle => $nom) {
            $lignes = DB::table('reprise_anomalies')->where('domaine', $cle)->orderByRaw("CASE gravite WHEN 'bloquant' THEN 0 WHEN 'decision' THEN 1 WHEN 'attention' THEN 2 ELSE 3 END")->orderBy('code')->orderBy('id')->limit(300)->get();
            if ($lignes->isEmpty()) {
                continue;
            }
            $total = DB::table('reprise_anomalies')->where('domaine', $cle)->count();
            $l[] = '';
            $l[] = "## {$nom} ({$total})";
            $l[] = '';
            foreach ($lignes as $a) {
                $l[] = '- **'.($gravites[$a->gravite] ?? $a->gravite)."** · `{$a->code}` — ".str_replace(["\r", "\n", '|'], [' ', ' ', '/'], $a->libelle)
                    .($a->statut !== 'ouverte' ? " — *{$a->statut}* : ".str_replace("\n", ' ', (string) $a->decision) : '');
            }
            if ($total > $lignes->count()) {
                $l[] = '- … '.($total - $lignes->count()).' autres lignes dans `reprise-anomalies.csv`.';
            }
        }

        return implode("\n", $l)."\n";
    }
}
