# Schéma & Gestion de la Base de Données — OUAISS EXPRESS

Ce document détaille la structure de données et les règles de gestion de base de données pour le projet **OUAISS EXPRESS**.

---

## 🗄️ 1. Environnements de Base de Données

| Environnement | Moteur | Configuration | Objectif |
|---|---|---|---|
| **Local (Développement)** | SQLite / MySQL 8 | `database/database.sqlite` | Tests unitaires & feature rapides, migration locale |
| **Testing (CI/PHPUnit)** | SQLite In-Memory | `:memory:` | Tests automatisés isolés avec `RefreshDatabase` |
| **Hostinger (Shared Prod)** | MySQL 8.x | Hostinger Shared Database | Base réelle de production avec préfixe `u405480831_` |

> ⚠️ **RÈGLE CRITIQUE DE SÉCURITÉ** :
> Les identifiants secrets de production ne doivent jamais figurer dans le code source versionné. Ils sont configurés exclusivement dans le fichier `.env` du serveur.

---

## 📋 2. Tables & Migrations Actives (Phase 1)

### Table `users` (Utilisateurs & Marchands)
Table centrale d'authentification et d'identité du système.

| Colonne | Type | Index | Rôle & Contrainte |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PRIMARY KEY | Identifiant unique auto-incrémenté |
| `name` | VARCHAR(255) | Non | Nom complet ou responsable |
| `email` | VARCHAR(255) | UNIQUE | Email unique de connexion |
| `phone` | VARCHAR(30) | INDEX | Numéro de téléphone WhatsApp professionnel |
| `role` | VARCHAR(30) | INDEX | Enum `UserRole` (`client`, `admin`, etc.) |
| `client_type` | VARCHAR(30) | Non | Enum `ClientType` (`e_commerce`, `entreprise`, `particulier`) |
| `store_name` | VARCHAR(255) | Non | Nom de la boutique ou enseigne commerciale |
| `city` | VARCHAR(100) | INDEX | Ville de rattachement ou ramassage |
| `address` | TEXT | Non | Adresse complète du stock ou dépôt |
| `is_active` | BOOLEAN | INDEX | Statut actif du compte (défaut `true`) |
| `password` | VARCHAR(255) | Non | Mot de passe haché (Bcrypt) |
| `remember_token`| VARCHAR(100) | Non | Jeton "Se souvenir de moi" |
| `created_at` | TIMESTAMP | Non | Date de création |
| `updated_at` | TIMESTAMP | Non | Date de dernière mise à jour |

### Table `sessions`
Stockage des sessions utilisateurs sécurisées (gérées via driver `database`).

### Table `password_reset_tokens`
Tokens de réinitialisation sécurisée des mots de passe.

### Table `cache` & `jobs`
Gestion du cache applicatif et des files d'attente asynchrones.

---

## 🛡️ 3. Directives de Sécurité des Migrations

1. **Aucune migration destructive** :
   - `migrate:fresh` et `db:wipe` sont **strictement interdits** en production.
   - Toute modification future doit être additive via de nouvelles migrations `Schema::table()`.
2. **Méthode `down()` obligatoire** :
   - Chaque fichier de migration doit contenir la méthode symétrique inverse permettant un rollback propre sans laisser d'orphelins.
3. **Indexation stratégique** :
   - Tous les champs filtrés fréquemment (`email`, `phone`, `role`, `city`, `is_active`) possèdent un index dédié pour garantir des temps de réponse sous 50ms sur MySQL 8.
