<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Zones\Backfill\DecoupageZones;
use Illuminate\Console\Command;

/**
 * Découpe les villes en zones logistiques (10 zones locales Témara + 9 hubs nationaux).
 * SIMULATION PAR DÉFAUT ; --apply refusé en production sans --force. Ne touche jamais `commands`.
 */
class BackfillZonesCommand extends Command
{
    protected $signature = 'zones:backfill
        {--apply : Écrire réellement (sinon simulation)}
        {--details : Lister chaque ville reclassée (avant → après, règle)}
        {--force : Autoriser --apply en environnement production}';

    protected $description = 'Découpe les villes en zones logistiques (locales BL / nationales BE) — simulation par défaut';

    public function handle(DecoupageZones $decoupage): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && app()->environment('production') && ! $this->option('force')) {
            $this->error('--apply refusé en production sans --force (autorisation explicite du propriétaire requise).');

            return self::FAILURE;
        }

        $this->info('=== Zones logistiques : découpage des villes ===');
        $this->line($apply ? '<fg=red>MODE ÉCRITURE</>' : '<fg=yellow>MODE SIMULATION — aucune écriture</>');

        $a = $decoupage->analyser();
        $liste = fn (array $c) => $c === [] ? '—' : implode(', ', $c);
        $this->table(['Contrôle', 'Valeur'], [
            ['Villes (archivées comprises)', $a['total']],
            ['Villes à classer (sans zone ou ancienne macro-zone)', count($a['villes'])],
            ['Villes déjà dans une zone du référentiel (inchangées)', $a['inchangees']],
            ['Zones à créer', $liste($a['zones_a_creer'])],
            ['Zones déjà présentes', $liste($a['zones_existantes'])],
            ['Zones en conflit (corbeille ou nom déjà pris) — non créées', $liste($a['zones_conflit'])],
            ['Anciennes macro-zones à archiver', $liste($a['anciennes_a_archiver'])],
            ['Anciennes renommées (nom repris)', $liste(array_map(fn ($c, $n) => "{$c} → {$n}", array_keys($a['noms_a_liberer']), $a['noms_a_liberer']))],
        ]);
        $this->line('Villes par zone cible (— = sans zone, à classer depuis la page Zones) :');
        $this->table(['Zone', 'Villes'], collect($a['par_zone'])->map(fn ($n, $c) => [$c, $n])->values()->all());
        $this->line('Règle appliquée :');
        $this->table(['Règle', 'Villes'], collect($a['par_regle'])->map(fn ($n, $r) => [$r, $n])->values()->all());

        if ($this->option('details')) {
            $this->table(['ID', 'Ville', 'Archivée', 'Avant', 'Après', 'Règle'], array_map(fn ($l) => [
                $l['id'], $l['nom'], $l['archivee'] ? 'oui' : '', $l['avant'] ?? '—', $l['apres'] ?? '—', $l['regle'],
            ], $a['villes']));
        }

        if (! $apply) {
            $this->comment('Simulation terminée. Relancer avec --apply pour écrire.');

            return self::SUCCESS;
        }

        $r = $decoupage->appliquer($a);
        $this->info("Écrit : {$r['zones_creees']} zone(s) créée(s), {$r['villes_rattachees']} ville(s) rattachée(s), "
            ."{$r['villes_sans_zone']} sans zone, {$r['anciennes_archivees']} ancienne(s) macro-zone(s) archivée(s).");

        return self::SUCCESS;
    }
}
