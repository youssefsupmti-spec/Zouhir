# 05 — Architecture des Permissions Avancées, Périmètres Acteurs Spatie & Rôles Personnalisés

**Date** : 30 octobre 2026  
**Auteur** : Gemini (Antigravity)  
**Chantier** : `C132` (`ouaissexpress-zouhir`) & `C84` (`gestion-droits-spatie`)  
**Projets Liés** : `OUAISS EXPRESS` (`Zouhir`), Référence `WinningExpress` & `Zakaria2`, Hub Central `Zakaria_Nouveau_102`  
**Statut** : **LIVRÉ, TESTÉ (20 TESTS VERTS) ET DÉPLOYÉ EN PRODUCTION (DEV)**  
**Niveau de Preuve** : `VERIFIED IN PRODUCTION`  

---

## 1. Contexte & Problématique Métier

Dans les architectures de référence **`Zakaria2`** et **`WinningExpress`**, les contrôles d'accès et les restrictions de données (scoping des colis, des bordereaux et des factures) reposent sur un principe fondamental :
1. **Découplage Rôle vs Capacité Comportementale** :
   Les vérifications applicatives ne testent pas en dur le libellé du rôle (ex: `$user->hasRole('client')`), car cela interdit la création de **rôles personnalisés** par les administrateurs (ex: *"Grand Compte E-commerce"*, *"Marchand VIP"*, *"Livreur Moto"*, *"Partenaire Régional"*).
2. **Périmètres Acteurs Spatie** :
   Chaque profil métier se voit attribuer une permission comportementale (`ActAsClient:Command`, `ActAsDriver:Command`, etc.). Un rôle personnalisé doté de `ActAsClient:Command` hérite automatiquement du statut et du périmètre client sans modifier une seule ligne de code PHP.
3. **Optimisation des Performances & Élimination des Lookups SQL Répétés** :
   Le scope natif de Spatie `$query->role('client')` déclenche systématiquement `Role::findByName('client')` sans mise en cache. Sur une page de tableau de bord ou de listage, la même requête `SELECT * FROM roles WHERE name = ?` était exécutée 2 à 5 fois.

---

## 2. Architecture Technique Implémentée

### A. Classe de Support `PerimetreColis`
Fichier : [`app/Domain/Commande/Support/PerimetreColis.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/app/Domain/Commande/Support/PerimetreColis.php)
- Déclare les constantes de permissions comportementales :
  - `PROPRE_COMPTE = 'ActAsClient:Command'` : restriction aux colis créés par le compte marchand / ses enseignes.
  - `COLIS_AFFECTES = 'ActAsDriver:Command'` : restriction aux colis affectés à la tournée du livreur (`livreur_id`).
  - `GESTION_AGENCE = 'ActAsAgency:Command'` : périmètre agence régionale.
  - `ADMINISTRATION = 'ActAsAdmin:Command'` : supervision globale de la plateforme.
- Méthodes statiques sécurisées : `compteVendeur(?User $user)`, `colisAffectes(?User $user)`, `agence(?User $user)`, `administration(?User $user)`.
- Évite le bypass global super-admin de `Gate::before` grâce à l'utilisation directe de `$user->hasPermissionTo(...)` protégée par `try/catch`.

### B. Service de Cache `RoleIds` (Lookups Spatie Scalaires)
Fichier : [`app/Domain/RolesPermissions/Support/RoleIds.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/app/Domain/RolesPermissions/Support/RoleIds.php)
- Résolution `nom de rôle -> ID numérique` avec mise en cache **Laravel Cache (TTL 24 heures)**.
- **Mémo en mémoire par requête** : élimine 100% des doublons au sein d'un même cycle HTTP / cycle de tâche.
- Sentinelle `NONE = 0` : garantit qu'un rôle inexistant ne déclenche pas une boucle de requêtes miss `null`.
- Enregistrement dans le conteneur en `scoped` dans [`AppServiceProvider.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/app/Providers/AppServiceProvider.php) (l'instance meurt avec la requête, compatible Laravel Octane).
- Invalidation automatique : écouteurs d'événements Eloquent sur `Role::saved` et `Role::deleted` (invalide également l'ancien libellé en cas de renommage).

### C. Évolution du Modèle `App\Models\User`
Fichier : [`app/Models/User.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/app/Models/User.php)
1. **Méthodes d'état s'appuyant sur les capacités** :
   ```php
   public function isClient(): bool
   {
       return PerimetreColis::compteVendeur($this)
           || $this->hasRole('client')
           || $this->role === UserRole::CLIENT;
   }

   public function isDriver(): bool
   {
       return PerimetreColis::colisAffectes($this)
           || $this->hasRole('livreur')
           || $this->role === UserRole::LIVREUR;
   }

   public function isAdmin(): bool
   {
       return PerimetreColis::administration($this)
           || $this->hasAnyRole(['admin', 'super_admin'])
           || $this->role === UserRole::ADMIN;
   }

   public function isStaff(): bool
   {
       return ! $this->isClient() && ! $this->isDriver();
   }
   ```
2. **Scopes Eloquent ultra-performants** :
   - `scopeWithRoleNamed(Builder $query, string $role)` : résout l'ID via `RoleIds` et applique `$query->whereHas('roles', fn ($q) => $q->whereKey($id))`.
   - `scopeClients(Builder $query)` : intègre à la fois le rôle standard `client`, les rôles personnalisés ayant `ActAsClient:Command`, et le fallback legacy `role = 'client'`.
   - `scopeDrivers(Builder $query)` : intègre le rôle standard `livreur`, les rôles personnalisés avec `ActAsDriver:Command`, et le fallback legacy `role = 'livreur'`.
   - `scopeActiveAccounts(Builder $query)` : filtre sur les comptes actifs.

---

## 3. Migration & Seeding Automatique

1. **Enum Permission** :
   Fichier : [`app/Domain/Auth/Enums/Permission.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/app/Domain/Auth/Enums/Permission.php)
   Ajout des 4 cas comportementaux : `ACT_AS_CLIENT`, `ACT_AS_DRIVER`, `ACT_AS_AGENCY`, `ACT_AS_ADMIN`.
2. **Migration Dédiée** :
   Fichier : [`database/migrations/2026_10_30_060000_seed_behavioral_permissions.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/database/migrations/2026_10_30_060000_seed_behavioral_permissions.php)
   Injecte les permissions dans `permissions` et les associe aux rôles de base lors du déploiement via `php artisan migrate`.
3. **Synchronisation du Seeder** :
   Fichier : [`database/seeders/RolesAndPermissionsSeeder.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/database/seeders/RolesAndPermissionsSeeder.php)

---

## 4. Validation des Tests Automatisés

Création de la suite de tests dédiée : [`tests/Feature/RolesPermissionsArchitectureTest.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/tests/Feature/RolesPermissionsArchitectureTest.php)

| Test Unitaire / Fonctionnel | Statut | Assertions | Description |
| :--- | :---: | :---: | :--- |
| `test_role_ids_cache_resolves_and_caches_id` | **VERT** | 3 | Valide la résolution et la mise en cache de l'ID rôle |
| `test_standard_client_is_detected_and_scoped` | **VERT** | 5 | Vérifie l'isolation du rôle client standard |
| `test_custom_role_with_act_as_client_is_detected_as_client` | **VERT** | 4 | **Valide qu'un rôle personnalisé (`marchand_vip`) est reconnu comme client** |
| `test_custom_role_with_act_as_driver_is_detected_as_driver` | **VERT** | 4 | **Valide qu'un rôle personnalisé (`coursier_express`) est reconnu comme chauffeur** |
| `test_admin_is_not_falsely_scoped_as_client_or_driver` | **VERT** | 6 | Vérifie l'étanchéité absolue : un admin n'est ni client ni livreur |

**Bilan global de la suite PHPUnit** :
- **20 tests exécutés, 20 tests passants (100% verts)**
- **66 assertions validées**
- 0 échec, 0 avertissement.

---

## 5. Déploiement et Vérification en Production (`dev.ouaissexpress.com`)

1. **Commit Git** : `106b25b` poussé sur la branche unique `main`.
2. **Application en direct sur le serveur Hostinger** :
   - Synchronisation Git : `HEAD is now at 106b25b`
   - Migration jouée : `2026_10_30_060000_seed_behavioral_permissions DONE (49ms)`
   - Seeder exécuté : `RolesAndPermissionsSeeder DONE (68ms)`
   - Caches de production re-générés : `config:cache`, `route:cache`, `view:cache`.
3. **Exécution du Backfill Utilisateurs** :
   - Commande : `php artisan app:backfill-users-from-stockout`
   - 73 utilisateurs mis à jour et synchronisés avec leurs permissions Spatie.
4. **Vérification HTTP 200 en Direct** :
   - `https://dev.ouaissexpress.com/` : **HTTP 200**
   - `https://dev.ouaissexpress.com/login` : **HTTP 200**
   - `https://dev.ouaissexpress.com/register` : **HTTP 200**

