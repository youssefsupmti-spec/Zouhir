<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Villes\Support\VilleRef;
use App\Models\Command as ColisModel;
use Illuminate\Console\Command;

/**
 * SIMULATION (lecture seule) des divergences de ville sur les colis DÉJÀ importés
 * (rapport #35, décisions 1 et 7).
 *
 * Compare, pour chaque colis, `destinataire_ville_id` actuel à la ville que donnerait la
 * politique unique (VilleRef, mode historique) appliquée à son libellé `destinataire_ville`.
 * N'écrit RIEN : aucune option d'écriture n'existe. Une correction éventuelle des colis
 * fera l'objet d'une décision et d'une commande distinctes.
 */
class AuditVillesColisCommand extends Command
{
    protected $signature = 'tarifs:audit-villes-colis {--limite=30 : Nombre de groupes divergents affichés}';

    protected $description = 'Simule (lecture seule) les écarts de ville des colis importés par rapport à la politique VilleRef';

    public function handle(VilleRef $villeRef): int
    {
        $this->info('=== Audit des villes des colis importés — LECTURE SEULE ===');

        $compte = ['identique' => 0, 'divergent' => 0, 'vide_resoluble' => 0, 'introuvable' => 0, 'sans_libelle' => 0];
        $groupes = [];

        ColisModel::withTrashed()
            ->select(['id', 'destinataire_ville', 'destinataire_ville_id'])
            ->orderBy('id')
            ->chunk(2000, function ($colis) use ($villeRef, &$compte, &$groupes) {
                foreach ($colis as $c) {
                    $libelle = trim((string) $c->destinataire_ville);
                    if ($libelle === '') {
                        $compte['sans_libelle']++;

                        continue;
                    }

                    $attendu = BackfillCommandsFromStockoutCommand::villePourLibelle($villeRef, $libelle);
                    $actuel = $c->destinataire_ville_id === null ? null : (int) $c->destinataire_ville_id;

                    $etat = match (true) {
                        $attendu === null => 'introuvable',
                        $actuel === null => 'vide_resoluble',
                        $actuel === $attendu => 'identique',
                        default => 'divergent',
                    };
                    $compte[$etat]++;

                    if ($etat !== 'identique') {
                        $cle = $etat.'|'.mb_strtolower($libelle).'|'.($actuel ?? '—').'|'.($attendu ?? '—');
                        $groupes[$cle] ??= ['etat' => $etat, 'libelle' => $libelle, 'actuel' => $actuel ?? '—', 'attendu' => $attendu ?? '—', 'colis' => 0];
                        $groupes[$cle]['colis']++;
                    }
                }
            });

        $this->table(['État', 'Colis'], collect($compte)->map(fn ($n, $e) => [$e, $n])->values()->all());

        usort($groupes, fn ($a, $b) => $b['colis'] <=> $a['colis']);
        if ($groupes !== []) {
            $this->line('Groupes non identiques (les plus volumineux) :');
            $this->table(
                ['État', 'Libellé colis', 'Ville actuelle', 'Ville attendue', 'Colis'],
                array_map(fn ($g) => [$g['etat'], $g['libelle'], $g['actuel'], $g['attendu'], $g['colis']], array_slice($groupes, 0, (int) $this->option('limite'))),
            );
        }

        $this->warn('Aucune écriture effectuée. Corriger des colis exige une décision et une commande distinctes.');

        return self::SUCCESS;
    }
}
