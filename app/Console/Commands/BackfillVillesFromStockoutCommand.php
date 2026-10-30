<?php

namespace App\Console\Commands;

use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Console\Command;
use PDO;

class BackfillVillesFromStockoutCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-villes-from-stockout 
                            {--host=localhost : Host MySQL legacy} 
                            {--database=u405480831_stockout : Base legacy} 
                            {--user=u405480831_stockout : Utilisateur legacy} 
                            {--password=1987Hassan/00 : Mot de passe legacy} 
                            {--dry-run : Exécuter une simulation sans modifier la base}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill et normalisation des 1349 villes de u405480831_stockout vers le schéma WinningExpress';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $host = $this->option('host');
        $database = $this->option('database');
        $username = $this->option('user');
        $password = $this->option('password');

        $this->info("=== DÉMARRAGE DU BACKFILL VILLES (Stockout -> OUAISS EXPRESS) ===");
        if ($isDryRun) {
            $this->warn("[SIMULATION / DRY-RUN] Aucune modification ne sera écrite.");
        }

        // 1. Connexion PDO à la base legacy
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->info("Connexion réussie à la base legacy [{$database}].");
        } catch (\Throwable $e) {
            $this->error("Erreur de connexion à la base legacy : " . $e->getMessage());
            return self::FAILURE;
        }

        // 2. Création des zones logistiques standards si non existantes
        $zonesDef = [
            'RBT' => ['name' => 'Zone Rabat-Témara-Skhirate (Hub Principal)', 'desc' => 'Bassin historique Ouais Express (Temara, Rabat, Skhirate, Tamesna, Harhoura, Ain Aouda)'],
            'CAS' => ['name' => 'Zone Grand Casablanca', 'desc' => 'Grand Casablanca, Mohammedia, Bouskoura, Dar Bouazza, Nouaceur'],
            'NORD' => ['name' => 'Zone Nord & Détroit', 'desc' => 'Tanger, Tétouan, Larache, Asilah, Ksar El Kebir'],
            'CENTRE' => ['name' => 'Zone Centre & Atlas', 'desc' => 'Marrakech, Fès, Meknès, Kénitra, Salé, Béni Mellal, El Jadida, Safi'],
            'SUD' => ['name' => 'Zone Sud & Souss', 'desc' => 'Agadir, Inezgane, Ait Melloul, Dakhla, Laâyoune, Guelmim, Taroudant'],
            'ORIENTAL' => ['name' => 'Zone Oriental', 'desc' => 'Oujda, Nador, Berkane, Taourirt'],
        ];

        $zoneModels = [];
        foreach ($zonesDef as $code => $def) {
            if (! $isDryRun) {
                $zone = Zone::firstOrCreate(
                    ['code' => $code],
                    ['name' => $def['name'], 'description' => $def['desc']]
                );
                $zoneModels[$code] = $zone;
            }
        }

        // 3. Extraction des villes depuis stockout.cities
        $legacyCities = $pdo->query('SELECT id, city, trash FROM `cities` ORDER BY id ASC')->fetchAll();
        $this->info("Nombre de villes brutes trouvées dans stockout.cities : " . count($legacyCities));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacyCities as $row) {
            $id = (int) $row['id'];
            $cityName = trim($row['city'] ?? '');
            $trash = (int) ($row['trash'] ?? 1);

            if ($cityName === '') {
                $skipped++;
                continue;
            }

            // Détection de la zone
            $zoneCode = $this->detectZoneCode($cityName);
            $zoneId = isset($zoneModels[$zoneCode]) ? $zoneModels[$zoneCode]->id : null;

            if ($isDryRun) {
                $created++;
                continue;
            }

            // Mise à jour ou création idempotente
            $ville = Ville::withTrashed()->find($id);
            if ($ville) {
                $ville->name_fr = $cityName;
                $ville->zone_id = $zoneId;
                $ville->code_zone = $zoneCode;
                if ($trash === 0) {
                    $ville->deleted_at = $ville->deleted_at ?: now();
                } else {
                    $ville->deleted_at = null;
                }
                $ville->save();
                $updated++;
            } else {
                $ville = new Ville();
                $ville->id = $id;
                $ville->name_fr = $cityName;
                $ville->zone_id = $zoneId;
                $ville->code_zone = $zoneCode;
                if ($trash === 0) {
                    $ville->deleted_at = now();
                }
                $ville->save();
                $created++;
            }
        }

        $this->info("=== BILAN DU BACKFILL VILLES ===");
        $this->info("Villes créées : {$created}");
        $this->info("Villes mises à jour : {$updated}");
        $this->info("Villes ignorées : {$skipped}");
        $this->info("Total traité : " . ($created + $updated));

        return self::SUCCESS;
    }

    private function detectZoneCode(string $cityName): string
    {
        $upper = strtoupper(trim($cityName));

        // RBT : Fief historique Ouais Express
        $rbt = ['TEMARA', 'SKHIRATE', 'SKHIRAT', 'AIN EL AOUDA', 'AIN AOUDA', 'TAMESNA', 'TAMESSNA', 'AIN ATTIG', 'HARHOURA', 'MERS EL KHEIR', 'RABAT', 'SIDI YAHIA ZAERS', 'BOUZNIKA'];
        foreach ($rbt as $name) {
            if (str_contains($upper, $name)) return 'RBT';
        }

        // CAS : Casablanca
        $cas = ['CASABLANCA', 'CASA', 'MOHAMMEDIA', 'BOUSKOURA', 'DAR BOUAZZA', 'NOUACEUR', 'TIT MELLIL', 'MEDIUNA', 'MEDIOUNA', 'DEROUA'];
        foreach ($cas as $name) {
            if (str_contains($upper, $name)) return 'CAS';
        }

        // NORD : Tanger & Tétouan
        $nord = ['TANGER', 'TETOUAN', 'LARACHE', 'ASILAH', 'KSAR EL KEBIR', 'CHEFCHAOUEN', 'FNIDEQ', 'MDIQ', 'MARTIL'];
        foreach ($nord as $name) {
            if (str_contains($upper, $name)) return 'NORD';
        }

        // ORIENTAL
        $oriental = ['OUJDA', 'NADOR', 'BERKANE', 'TAOURIRT', 'SAIDIA', 'DRIOUCH', 'JERADA'];
        foreach ($oriental as $name) {
            if (str_contains($upper, $name)) return 'ORIENTAL';
        }

        // SUD
        $sud = ['AGADIR', 'INEZGANE', 'AIT MELLOUL', 'DAKHLA', 'LAAYOUNE', 'GUELMIM', 'TAROUDANT', 'TIZNIT', 'TAN TAN', 'SIDI IFNI'];
        foreach ($sud as $name) {
            if (str_contains($upper, $name)) return 'SUD';
        }

        // CENTRE (par défaut pour les autres villes majeures)
        return 'CENTRE';
    }
}
