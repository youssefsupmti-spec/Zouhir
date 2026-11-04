<?php

namespace App\Console\Commands;

use App\Domain\Villes\Support\VilleRef;
use Illuminate\Console\Command;

/**
 * ANCIENNE reprise des colis — remplacée par `reprise:legacy` (C140).
 *
 * Elle rattachait au compte #1 les colis sans expéditeur, datait « livré » à la dernière
 * modification et remplaçait les villes inconnues par « Uknow-ville » sans garder le libellé :
 * ce n'était pas une reprise exacte. Elle refuse désormais de s'exécuter ; seule la règle de
 * rattachement des villes (utilisée par l'audit des villes et les tests) reste exposée.
 */
class BackfillCommandsFromStockoutCommand extends Command
{
    protected $signature = 'app:backfill-commands-from-stockout {--dry-run : sans effet}';

    protected $description = 'Remplacée par reprise:legacy (reprise exacte des colis, de l’historique, des notes et des bons)';

    public function handle(): int
    {
        $this->error('Commande remplacée : utiliser « php artisan reprise:legacy » (voir --help).');

        return self::FAILURE;
    }

    /**
     * Ville d'un libellé legacy de colis — même politique que les tarifs clients (VilleRef, historique).
     */
    public static function villePourLibelle(VilleRef $villeRef, string $libelle): ?int
    {
        return $villeRef->resoudre(trim($libelle), historique: true)->villeId;
    }
}
