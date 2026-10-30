<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\CompteBancaire;
use Illuminate\Console\Command;

class BackfillBanksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-banks {--dry-run : Exécuter sans modifier la base}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialise le catalogue des banques marocaines et relie les comptes bancaires (bank_id)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("=== DÉMARRAGE DU RÉFÉRENTIEL BANCAIRE & BACKFILL COMPTES ===");
        if ($isDryRun) {
            $this->warn("[DRY-RUN] Aucune modification ne sera enregistrée.");
        }

        // 1. Catalogue des Banques Officielles du Maroc (Bank Al-Maghrib)
        $banksCatalogue = [
            [
                'name' => 'Attijariwafa Bank',
                'code_etablissement' => '007',
                'swift_code' => 'BCMAMAMC',
                'aliases' => ['ATTIJARI', 'AWB', 'WAFA', 'ATTIJARIWAFA'],
            ],
            [
                'name' => 'CIH Bank',
                'code_etablissement' => '230',
                'swift_code' => 'CIHBMAMC',
                'aliases' => ['CIH', 'CREDIT IMMOBILIER'],
            ],
            [
                'name' => 'Banque Populaire (BCP)',
                'code_etablissement' => '101',
                'swift_code' => 'BPOPMAMC',
                'aliases' => ['BCP', 'CHAABI', 'BANQUE POPULAIRE', 'POPULAIRE'],
            ],
            [
                'name' => 'Bank of Africa (BMCE)',
                'code_etablissement' => '011',
                'swift_code' => 'BMCEMAMC',
                'aliases' => ['BMCE', 'BOA', 'BANK OF AFRICA', 'BANQUE MAROCAINE'],
            ],
            [
                'name' => 'BMCI (Groupe BNP Paribas)',
                'code_etablissement' => '225',
                'swift_code' => 'BMCIMAMC',
                'aliases' => ['BMCI', 'BNP'],
            ],
            [
                'name' => 'Société Générale Maroc (SGMB)',
                'code_etablissement' => '022',
                'swift_code' => 'SGMAMAMC',
                'aliases' => ['SGMB', 'SOCIETE GENERALE', 'SG'],
            ],
            [
                'name' => 'Crédit Agricole du Maroc (CAM)',
                'code_etablissement' => '198',
                'swift_code' => 'CNCAMAMC',
                'aliases' => ['CAM', 'CREDIT AGRICOLE', 'FELLAH'],
            ],
            [
                'name' => 'Crédit du Maroc (CDM)',
                'code_etablissement' => '021',
                'swift_code' => 'CDMMMAMC',
                'aliases' => ['CDM', 'CREDIT DU MAROC'],
            ],
            [
                'name' => 'Al Barid Bank',
                'code_etablissement' => '350',
                'swift_code' => 'ABBKMAMC',
                'aliases' => ['BARID', 'POSTE', 'POSTE MAROC', 'AL BARID'],
            ],
            [
                'name' => 'CFG Bank',
                'code_etablissement' => '182',
                'swift_code' => 'CFGBMAMC',
                'aliases' => ['CFG'],
            ],
            [
                'name' => 'Umnia Bank',
                'code_etablissement' => '240',
                'swift_code' => 'UMBNMAMC',
                'aliases' => ['UMNIA'],
            ],
            [
                'name' => 'Bank Assafa',
                'code_etablissement' => '245',
                'swift_code' => 'BASFMAMC',
                'aliases' => ['ASSAFA'],
            ],
        ];

        $bankModels = [];
        $codeMap = [];
        $aliasMap = [];

        foreach ($banksCatalogue as $item) {
            if (! $isDryRun) {
                $bank = Bank::firstOrCreate(
                    ['name' => $item['name']],
                    [
                        'code_etablissement' => $item['code_etablissement'],
                        'swift_code' => $item['swift_code'],
                        'status' => 'active',
                    ]
                );
                $bankModels[$item['name']] = $bank;
            } else {
                $bank = new Bank($item);
                $bank->id = rand(1, 100);
            }

            $codeMap[$item['code_etablissement']] = $bank;
            foreach ($item['aliases'] as $alias) {
                $aliasMap[strtoupper($alias)] = $bank;
            }
        }

        $this->info("Catalogue banques : " . count($banksCatalogue) . " établissements enregistrés.");

        // 2. Rapprochement des comptes existants dans comptes_bancaires
        $comptes = CompteBancaire::all();
        $this->info("Comptes bancaires à vérifier et relier : " . $comptes->count());

        $linkedCount = 0;
        foreach ($comptes as $compte) {
            $matchedBank = null;

            // A. Détection par les 3 premiers chiffres du RIB (code établissement BAM)
            $cleanRib = preg_replace('/[^0-9]/', '', (string) $compte->rib);
            if (strlen($cleanRib) >= 3) {
                $prefix = substr($cleanRib, 0, 3);
                if (isset($codeMap[$prefix])) {
                    $matchedBank = $codeMap[$prefix];
                }
            }

            // B. Rapprochement par le libellé bank_name existant
            if (! $matchedBank && $compte->bank_name) {
                $bNameUpper = strtoupper(trim($compte->bank_name));
                foreach ($aliasMap as $alias => $bankObj) {
                    if (str_contains($bNameUpper, $alias)) {
                        $matchedBank = $bankObj;
                        break;
                    }
                }
            }

            // C. Repli par défaut si non trouvé
            if (! $matchedBank && isset($bankModels['Attijariwafa Bank'])) {
                $matchedBank = $bankModels['Attijariwafa Bank'];
            }

            if ($matchedBank) {
                $this->line(sprintf(
                    "Compte #%d (Titulaire: %s, RIB: %s) -> Banque liée: [%s] (ID: %d)",
                    $compte->id,
                    $compte->titulaire_nom,
                    substr($compte->rib, 0, 8) . '...',
                    $matchedBank->name,
                    $matchedBank->id
                ));

                if (! $isDryRun) {
                    $compte->bank_id = $matchedBank->id;
                    $compte->bank_name = $matchedBank->name;
                    $compte->save();
                }

                $linkedCount++;
            }
        }

        $this->info("=== BILAN DU BACKFILL BANCAIRE ===");
        $this->info("Banques au catalogue : " . count($banksCatalogue));
        $this->info("Comptes bancaires reliés par clé étrangère : {$linkedCount} / " . $comptes->count());

        return self::SUCCESS;
    }
}

