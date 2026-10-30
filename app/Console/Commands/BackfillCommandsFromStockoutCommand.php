<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Command as ColisModel;
use App\Models\User;
use App\Models\Ville;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class BackfillCommandsFromStockoutCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-commands-from-stockout 
                            {--host=localhost : Host MySQL legacy} 
                            {--database=u405480831_stockout : Base legacy} 
                            {--user=u405480831_stockout : Utilisateur legacy} 
                            {--password=1987Hassan/00 : Mot de passe legacy} 
                            {--limit=0 : Nombre maximal de colis à traiter (0 = tous)} 
                            {--chunk=1000 : Taille des blocs d\'insertion} 
                            {--dry-run : Exécuter une simulation sans modifier la base}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill et normalisation des colis (commands) de u405480831_stockout vers le schéma WinningExpress';

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
        $limit = (int) $this->option('limit');
        $chunkSize = max(100, (int) $this->option('chunk'));

        $this->info("=== DÉMARRAGE DU BACKFILL COLIS / COMMANDS (Stockout -> OUAISS EXPRESS) ===");
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

        // 2. Préchargement en mémoire des tables de référence
        $this->info("Préchargement des Marchands, Enseignes et Villes...");
        
        $usersMap = User::withTrashed()->pluck('nom_complet', 'id')->toArray();
        $businesses = Business::all();
        $clientBusinesses = [];
        $businessNames = [];
        foreach ($businesses as $b) {
            $clientBusinesses[$b->user_id] = $b->id;
            $businessNames[$b->user_id] = $b->name;
        }

        $villesRaw = Ville::withTrashed()->get(['id', 'name_fr', 'code_zone']);
        $villesMap = [];
        $villesZoneMap = [];
        foreach ($villesRaw as $v) {
            $cleanName = strtoupper(trim($v->name_fr));
            $villesMap[$cleanName] = $v->id;
            $villesZoneMap[$cleanName] = $v->code_zone;
        }

        // 3. Extraction paginée des colis depuis stockout.commands
        $sqlCount = "SELECT COUNT(*) FROM `commands`";
        if ($limit > 0) {
            $sqlCount .= " LIMIT {$limit}";
        }
        $totalLegacy = (int) $pdo->query($sqlCount)->fetchColumn();
        $totalToProcess = $limit > 0 ? min($limit, $totalLegacy) : $totalLegacy;
        $this->info("Total de colis à traiter : {$totalToProcess}");

        $offset = 0;
        $insertedTotal = 0;

        while ($offset < $totalToProcess) {
            $currentLimit = min($chunkSize, $totalToProcess - $offset);
            $stmt = $pdo->prepare("SELECT * FROM `commands` ORDER BY id ASC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $currentLimit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                break;
            }

            $batch = [];
            $now = now()->toDateTimeString();

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $clientId = (int) $row['client'];
                $dlmId = (int) $row['dlm'];

                // Vérifier si le client existe dans users
                if (! isset($usersMap[$clientId])) {
                    // Si le client n'est pas dans users (orphelin legacy), l'associer au compte par défaut ou premier admin
                    $clientId = 1;
                }

                $businessId = $clientBusinesses[$clientId] ?? null;
                $businessName = $businessNames[$clientId] ?? ($usersMap[$clientId] ?? 'Boutique Client');
                $livreurId = ($dlmId > 0 && isset($usersMap[$dlmId])) ? $dlmId : null;
                $livreurNom = $livreurId ? ($usersMap[$livreurId] ?? null) : null;

                $cityStr = trim($row['city'] ?? '');
                $cityUpper = strtoupper($cityStr);
                $villeId = $villesMap[$cityUpper] ?? null;
                $zoneCode = $villesZoneMap[$cityUpper] ?? null;

                $state = trim($row['state'] ?? 'Nouveau');
                $stateGroup = $this->classifyStateGroup($state);

                $dateAdd = (int) ($row['dateadd'] ?? 0);
                $dateUpdate = (int) ($row['dateupdate'] ?? 0);
                $createdAt = ($dateAdd > 0) ? Carbon::createFromTimestamp($dateAdd)->toDateTimeString() : $now;
                $updatedAt = ($dateUpdate > 0) ? Carbon::createFromTimestamp($dateUpdate)->toDateTimeString() : $now;
                $deletedAt = ((int) ($row['trash'] ?? 1) === 0) ? $now : null;

                $batch[] = [
                    'id' => $id,
                    'code' => trim($row['code'] ?? '') ?: ('OIS-' . $id),
                    'produit' => trim($row['product'] ?? '') ?: null,
                    'quantite' => max(1, (int) ($row['qty'] ?? 1)),
                    'client_id' => $clientId,
                    'business_id' => $businessId,
                    'business_name' => $businessName,
                    'nom_cliente' => $usersMap[$clientId] ?? null,
                    'livreur_id' => $livreurId,
                    'livreur_nom' => $livreurNom,
                    'livreur_telephone' => null,
                    'destinataire_nom' => trim($row['fullname'] ?? '') ?: 'Client Final',
                    'destinataire_telephone' => trim($row['phone'] ?? ''),
                    'destinataire_adresse' => trim($row['address'] ?? '') ?: null,
                    'destinataire_ville' => $cityStr ?: 'Non spécifiée',
                    'destinataire_ville_id' => $villeId,
                    'destinataire_zone_code' => $zoneCode,
                    'prix_brut' => (float) ($row['price'] ?? 0),
                    'frais_livraison_client' => 0.00,
                    'frais_livraison_livreur' => 0.00,
                    'state' => $state,
                    'state_group' => $stateGroup,
                    'is_ramassee' => ($row['collected'] ?? '') === 'on',
                    'ouverture_autorisee' => (bool) ($row['openpackage'] ?? 0),
                    'echange' => (bool) ($row['echange'] ?? 0),
                    'note' => trim($row['note'] ?? '') ?: null,
                    'livre_at' => ($stateGroup === 'livre') ? $updatedAt : null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                    'deleted_at' => $deletedAt,
                ];
            }

            if (! $isDryRun && ! empty($batch)) {
                ColisModel::upsert(
                    $batch,
                    ['id'],
                    [
                        'code', 'produit', 'quantite', 'client_id', 'business_id', 'business_name',
                        'nom_cliente', 'livreur_id', 'livreur_nom', 'destinataire_nom', 'destinataire_telephone',
                        'destinataire_adresse', 'destinataire_ville', 'destinataire_ville_id', 'destinataire_zone_code',
                        'prix_brut', 'state', 'state_group', 'is_ramassee', 'ouverture_autorisee', 'echange',
                        'note', 'livre_at', 'created_at', 'updated_at', 'deleted_at'
                    ]
                );
            }

            $insertedTotal += count($batch);
            $offset += $currentLimit;
            $this->line("Progression : {$insertedTotal} / {$totalToProcess} colis traités...");
        }

        $this->info("=== BACKFILL TERMINÉ AVEC SUCCÈS ===");
        $this->info("Total colis insérés / mis à jour : {$insertedTotal}");

        return self::SUCCESS;
    }

    private function classifyStateGroup(string $state): string
    {
        $s = mb_strtolower(trim($state));

        if (str_contains($s, 'livré') || str_contains($s, 'livre')) {
            return 'livre';
        }

        if (str_contains($s, 'annul') || str_contains($s, 'refus') || str_contains($s, 'pas de réponse') || str_contains($s, 'hors zone') || str_contains($s, 'injoignable')) {
            return 'annule';
        }

        if (str_contains($s, 'retour')) {
            return 'retour';
        }

        if (str_contains($s, 'ajout') || str_contains($s, 'prêt')) {
            return 'nouveau';
        }

        return 'en_cours';
    }
}
