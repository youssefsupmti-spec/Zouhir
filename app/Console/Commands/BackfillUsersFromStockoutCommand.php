<?php

namespace App\Console\Commands;

use App\Domain\Auth\Enums\UserRole;
use App\Models\Business;
use App\Models\CompteBancaire;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use Spatie\Permission\Models\Role;

class BackfillUsersFromStockoutCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-users-from-stockout 
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
    protected $description = 'Backfill et normalisation des 73 utilisateurs de u405480831_stockout vers le schéma WinningExpress';

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

        $this->info("=== DÉMARRAGE DU BACKFILL USERS (Stockout -> OUAISS EXPRESS WinningExpress) ===");
        if ($isDryRun) {
            $this->warn("[SIMULATION / DRY-RUN] Aucune modification ne sera écrite.");
        }

        // 1. Connexion PDO à la base legacy
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->info("Connexion réussie à la base legacy [{$database}] sur [{$host}].");
        } catch (\Throwable $e) {
            $this->error("Erreur de connexion à la base legacy: " . $e->getMessage());
            return self::FAILURE;
        }

        // 2. Récupérer les utilisateurs
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
        $legacyUsers = $stmt->fetchAll();
        $total = count($legacyUsers);
        $this->info("Nombre total d'utilisateurs trouvés dans stockout.users: {$total}");

        // Assurer l'existence des rôles Spatie cibles
        $roleMap = [
            'client' => UserRole::CLIENT->value,
            'dlm' => UserRole::LIVREUR->value,
            'moderator' => UserRole::ADMIN->value,
        ];

        if (! $isDryRun) {
            foreach ($roleMap as $r) {
                Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
            }
        }

        $migrated = 0;
        $updated = 0;
        $businessesCreated = 0;
        $bankAccountsCreated = 0;

        $statsByType = ['client' => 0, 'dlm' => 0, 'moderator' => 0, 'other' => 0];
        $statsByActive = ['on' => 0, 'off' => 0];
        $statsByTrash = ['active' => 0, 'trash' => 0];

        foreach ($legacyUsers as $row) {
            $id = (int) $row['id'];
            $type = strtolower(trim($row['type'] ?? 'client'));
            $statsByType[$type] = ($statsByType[$type] ?? 0) + 1;

            $activeRaw = strtolower(trim($row['active'] ?? 'on'));
            $isActive = ($activeRaw === 'on' || $activeRaw === '1');
            $statsByActive[$isActive ? 'on' : 'off']++;

            $trash = (int) ($row['trash'] ?? 1);
            // Dans stockout: trash = 1 -> ACTIF, trash = 0 -> ARCHIVÉ / SUPPRIMÉ
            $isSoftDeleted = ($trash !== 1);
            $statsByTrash[$isSoftDeleted ? 'trash' : 'active']++;

            // Conversion timestamp
            $signupTs = (int) ($row['datesignup'] ?? 0);
            $createdAt = $signupTs > 0 ? Carbon::createFromTimestamp($signupTs) : now();

            // Mot de passe
            $pwd = (string) ($row['password'] ?? '');
            $isBcrypt = str_starts_with($pwd, '$2y$') || str_starts_with($pwd, '$2a$');
            $finalPassword = $isBcrypt ? $pwd : Hash::make($pwd ?: 'ouais123456');

            // Rôle Spatie
            $targetRole = $roleMap[$type] ?? UserRole::CLIENT->value;

            $this->line(sprintf(
                "[%s] ID:%d | %s | %s | Type:%s -> Rôle:%s | Actif:%s | Corbeille:%s",
                $isDryRun ? 'SIMUL' : 'IMPORT',
                $id,
                $row['email'],
                $row['fullname'],
                $type,
                $targetRole,
                $isActive ? 'OUI' : 'NON',
                $isSoftDeleted ? 'OUI' : 'NON'
            ));

            if (! $isDryRun) {
                // Insérer ou mettre à jour dans users
                $userData = [
                    'id' => $id,
                    'nom_complet' => trim($row['fullname'] ?? ''),
                    'picture' => trim($row['picture'] ?? '') ?: null,
                    'email' => strtolower(trim($row['email'])),
                    'email_verified_at' => ($row['email_verified'] ?? 0) == 1 ? $createdAt : null,
                    'password' => $finalPassword,
                    'telephone' => trim($row['phone'] ?? '') ?: null,
                    'sav' => trim($row['sav'] ?? '') ?: null,
                    'ville_name' => trim($row['city'] ?? '') ?: null,
                    'cin' => trim($row['cin'] ?? '') ?: null,
                    'bank' => trim($row['bank'] ?? '') ?: null,
                    'rib' => trim($row['rib'] ?? '') ?: null,
                    'stockout' => trim($row['stockout'] ?? '') ?: null,
                    'emailstockout' => trim($row['emailstockout'] ?? '') ?: null,
                    'active' => $isActive ? 1 : 0,
                    'role' => $targetRole,
                    'client_type' => 'e_commerce',
                    'store_name' => $type === 'client' 
                        ? (trim($row['store'] ?? '') ?: trim($row['fullname'] ?? '') ?: ('Boutique #' . $id))
                        : (trim($row['store'] ?? '') ?: null),
                    'deleted_at' => $isSoftDeleted ? now() : null,
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ];

                $user = User::withTrashed()->find($id);
                if (! $user) {
                    // Forcer l'ID existant
                    DB::table('users')->insert($userData);
                    $user = User::withTrashed()->find($id);
                    $migrated++;
                } else {
                    $user->update($userData);
                    $updated++;
                }

                // Assigner le rôle Spatie
                if ($user && method_exists($user, 'syncRoles')) {
                    $user->syncRoles([$targetRole]);
                }

                // Créer l'enseigne marchande pour TOUS les clients (repli sur fullname si store vide)
                // Note : le nom est unique au niveau du même client (user_id), jamais unique globalement.
                if ($type === 'client') {
                    $businessName = trim($row['store'] ?? '');
                    if ($businessName === '') {
                        $businessName = trim($row['fullname'] ?? '') ?: ('Boutique #' . $id);
                    }

                    Business::updateOrCreate(
                        ['user_id' => $id, 'name' => $businessName],
                        [
                            'phone' => trim($row['phone'] ?? '') ?: null,
                            'city' => trim($row['city'] ?? '') ?: null,
                            'status' => 'active',
                        ]
                    );
                    $businessesCreated++;
                }

                // Créer le compte bancaire si `rib` est renseigné
                $ribRaw = trim($row['rib'] ?? '');
                if ($ribRaw !== '') {
                    $ribParts = explode('|', $ribRaw);
                    $cleanRib = trim($ribParts[0]);

                    CompteBancaire::updateOrCreate(
                        ['owner_id' => $id, 'rib' => $cleanRib],
                        [
                            'owner_type' => $type === 'dlm' ? CompteBancaire::OWNER_LIVREUR : CompteBancaire::OWNER_CLIENT,
                            'titulaire_nom' => trim($row['fullname'] ?? ''),
                            'bank_name' => trim($row['bank'] ?? '') ?: 'Banque Marocaine',
                            'is_default' => true,
                        ]
                    );
                    $bankAccountsCreated++;
                }
            }
        }

        $this->info("--------------------------------------------------");
        $this->info("RÉCAPITULATIF DE L'AUDIT & DU BACKFILL :");
        $this->info(" - Total enregistrements sources : {$total}");
        $this->info(" - Répartition par Type : " . json_encode($statsByType));
        $this->info(" - Répartition par Statut Actif : " . json_encode($statsByActive));
        $this->info(" - Répartition par Corbeille (Trash) : " . json_encode($statsByTrash));
        if (! $isDryRun) {
            $this->info(" - Utilisateurs insérés : {$migrated}");
            $this->info(" - Utilisateurs mis à jour : {$updated}");
            $this->info(" - Enseignes créées (businesses) : {$businessesCreated}");
            $this->info(" - Comptes bancaires créés (comptes_bancaires) : {$bankAccountsCreated}");
        }
        $this->info("=== BACKFILL USERS TERMINÉ AVEC SUCCÈS ===");

        return self::SUCCESS;
    }
}
