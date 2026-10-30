# Gestion Avancée des Rôles, Permissions & Policies (Spatie)

## 1. Principes d'Architecture (Inspirés de WinningExpress)
- **Zéro chaînes en dur** : Tous les rôles et permissions sont strictement définis via des Enums typés PHP 8.4 :
  - `App\Domain\Auth\Enums\UserRole` : `admin`, `client`, `livreur`, `agence`, `operateur`.
  - `App\Domain\Auth\Enums\Permission` : `view_dashboard`, `manage_colis`, `create_colis`, `track_colis`, `view_finances`, `export_excel`, `manage_users`, `access_admin`.
- **Package Officiel Spatie** : `spatie/laravel-permission` (v8.3) installé, avec migration des tables (`roles`, `permissions`, `model_has_roles`, `role_has_permissions`).
- **Super-Admin Bypass** : Configuré dans `AppServiceProvider` via `Gate::before(fn ($user, $ability) => $user->isAdmin() ? true : null)`.
- **Policies DDD Dédiées** :
  - `DashboardPolicy` : contrôle l'accès au tableau de bord (`view`).
  - `UserPolicy` : contrôle la gestion et l'affichage des comptes utilisateurs.

## 2. Redirections Intelligentes du Flux d'Authentification
- **Utilisateur NON connecté (Guest)** :
  - Tentative d'accès à `/dashboard` ou toute ressource protégée -> redirection HTTP 302 immédiate vers `/login`.
  - Configuré nativement dans `bootstrap/app.php` via `$middleware->redirectTo(guests: '/login', users: '/dashboard')`.
- **Utilisateur DÉJÀ connecté** :
  - Accès à `/login` ou `/register` -> redirection automatique vers `/dashboard`.
  - Connexion réussie -> redirection vers `/dashboard`.
  - Inscription réussie -> redirection vers `/dashboard`.
  - Dans la Navbar publique (`Navbar.jsx`), bouton proéminent "Mon Tableau de Bord" avec avatar et déconnexion en un clic.

## 3. Synchronisation & Seeder
- Seeder : `database/seeders/RolesAndPermissionsSeeder.php`.
- Tables migrées et exécutées sur MySQL `u405480831_dev`.
