# Migrations & Schéma MySQL

## 1. Migration Phase 1
- Fichier : `database/migrations/2026_10_30_030000_add_client_fields_to_users_table.php`
- Champs ajoutés sur la table `users` :
  - `phone` (string, nullable)
  - `city` (string, nullable)
  - `address` (text, nullable)
  - `company_name` (string, nullable)
  - `client_type` (string, default: 'e_commerce')
  - `role` (string, default: 'client')
  - `is_active` (boolean, default: true)
- Rollback garanti : la méthode `down()` supprime proprement les colonnes ajoutées sans altérer les colonnes natives de Laravel.

## 2. Règle d'or en Production
- **INTERDICTION STRICTE** de lancer `migrate:fresh` ou `db:wipe`.
- Les migrations s'exécutent avec `php artisan migrate --force`.
