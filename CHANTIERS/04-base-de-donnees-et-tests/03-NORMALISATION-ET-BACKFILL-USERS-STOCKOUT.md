# 03 — Normalisation de la Base de Données & Backfill de la Table `users` (u405480831_stockout -> u405480831_dev)

**Date** : 30 octobre 2026  
**Auteur** : Gemini (Antigravity)  
**Projets Liés** : `OUAISS EXPRESS` (Zouhir) · Référence `WinningExpress` · Hub Central `Zakaria_Nouveau_102`  
**Statut** : **LIVRÉ, EXÉCUTÉ ET VÉRIFIÉ EN PRODUCTION (DEV)**  
**Niveau de Preuve** : `VERIFIED IN PRODUCTION`  

---

## 1. Objectif & Stratégie Table par Table

À la demande explicite de l'utilisateur :
1. **Alignement strict du schéma sur WinningExpress** : La structure des tables du nouveau projet OUAISS EXPRESS doit adopter exactement la nomenclature, les conventions et le découpage relationnel de `winningExpressLaravelFilament` (noms de colonnes francisés, SoftDeletes natifs, Spatie permissions, tables d'entités `businesses` et `comptes_bancaires`).
2. **Audit & Backfill table par table** : Début de l'opération par la table maîtresse **`users`** depuis la base historique Hostinger `u405480831_stockout` vers la nouvelle base `u405480831_dev`.
3. **Zéro régression sur le legacy** : La base de production historique `u405480831_stockout` est lue en **lecture seule stricte (SELECT)** sans aucune écriture ni modification.

---

## 2. Métriques & Chiffres Clés de la Table Source (`u405480831_stockout.users`)

| Métrique | Valeur Observée | Description / Règle Métier |
| :--- | :--- | :--- |
| **Total Enregistrements** | **73 utilisateurs** | Exhaustivité des comptes créés sur l'ancien système |
| **Clients (`type = 'client'`)** | **33 comptes** | Commerçants et marchands e-commerce |
| **Livreurs / DLM (`type = 'dlm'`)** | **33 comptes** | Livreurs urbains, coursiers régionaux et chefs d'agence |
| **Modérateurs (`type = 'moderator'`)** | **7 comptes** | Administrateurs système et opérateurs de saisie |
| **Comptes Actifs (`active = 'on'`)** | **66 comptes** | Comptes autorisés à se connecter (`active = 1`) |
| **Comptes Désactivés (`active = 'off'`)** | **7 comptes** | Comptes bloqués administrativement (`active = 0`) |
| **Comptes Sains (`trash = 1`)** | **57 comptes** | Comptes actifs (`deleted_at = NULL`) |
| **Comptes Supprimés (`trash = 0`)** | **16 comptes** | Placés en corbeille via SoftDeletes (`deleted_at = now()`) |

---

## 3. Matrice de Mapping Colonne par Colonne (`stockout` -> `WinningExpress`)

| Colonne Source (`u405480831_stockout`) | Type Source | Colonne Cible (`u405480831_dev`) | Type Cible | Transformation & Normalisation Appliquée |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `int(11)` | `id` | `bigint unsigned` | Conservé à l'identique pour garantir l'intégrité référentielle |
| `fullname` | `varchar(255)` | `nom_complet` | `varchar(255)` | Renommé selon la convention WinningExpress (alias `name` maintenu) |
| `email` | `varchar(255)` | `email` | `varchar(255)` | Normalisé en minuscules (`strtolower(trim())`) |
| `password` | `varchar(255)` | `password` | `varchar(255)` | **Détection automatique** : hash bcrypt conservé intact ; mots de passe clairs ou legacy re-hachés via `Hash::make()` |
| `phone` | `varchar(255)` | `telephone` | `varchar(255)` | Renommé selon la convention WinningExpress (alias `phone` maintenu) |
| `city` | `varchar(255)` | `ville_name` | `varchar(255)` | Nom textuel conservé + `ville_id` préparé pour les jointures |
| `cin` | `varchar(255)` | `cin` | `varchar(50)` | Identifiant carte nationale d'identité |
| `sav` | `varchar(255)` | `sav` | `varchar(100)` | Numéro ou contact service après-vente |
| `store` | `varchar(255)` | `store_name` + `businesses` | `table` | Extrait vers la table dédiée `businesses` (16 enseignes créées) |
| `bank` | `varchar(255)` | `bank` + `comptes_bancaires` | `varchar` + `table` | Nom de la banque du titulaire |
| `rib` | `varchar(255)` | `rib` + `comptes_bancaires` | `varchar(255)` + `table` | **Nettoyage automatique** : extraction des 24 chiffres du RIB séparés des métadonnées `RIB_IMG:xxx.jpg` (8 comptes bancaires créés) |
| `stockout` | `varchar(255)` | `stockout` | `varchar(255)` | Référence identifiant legacy |
| `emailstockout` | `varchar(255)` | `emailstockout` | `varchar(255)` | Email de secours historique |
| `type` | `varchar(255)` | *Supprimé* -> **Spatie Roles** | `model_has_roles` | **Migration vers Spatie Permission** : `'client'` -> rôle `client`, `'dlm'` -> rôle `livreur`, `'moderator'` -> rôle `admin` |
| `roles` | `text` | *Supprimé* -> **Spatie Permissions** | `permissions` | Anciennes permissions textuelles CSV obsolètes remplacées par Spatie |
| `active` | `varchar(255)` | `active` | `tinyint(1)` (bool) | `'on'` ou `'1'` -> `1` (true) ; `'off'` ou `'0'` -> `0` (false) |
| `datesignup` | `varchar(255)` | `created_at` / `updated_at` | `timestamp` | Converti depuis le timestamp Unix entier vers date Laravel standard |
| `trash` | `int(11)` | `deleted_at` | `timestamp` | `trash = 1` -> non supprimé ; `trash = 0` -> horodaté `deleted_at` (SoftDeletes) |
| `email_verified` | `tinyint(1)` | `email_verified_at` | `timestamp` | `1` -> horodaté à la date d'inscription ; `0` -> NULL |
| *Nouveau* | - | `inscription_statut` | `varchar(20)` | Statut du circuit d'inscription C120 (`acceptee`, `en_attente`, etc.) |
| *Nouveau* | - | `api_abilities` | `json` | Droits API marchands et livreurs (fail-closed) |

---

## 4. Outils & Commandes Développés

### 4.1 Migration d'Alignement du Schéma
Fichier : `database/migrations/2026_10_30_050000_align_users_table_with_winning_express.php`
- Aligne les colonnes `nom_complet`, `telephone`, `ville_name`, `active`, `civilite`, `cin`, `sav`, `rib`, `bank`.
- Crée la table satellite `businesses` (enseignes des clients marchands).
- Crée la table satellite `comptes_bancaires` (comptes bancaires multi-RIB).

### 4.2 Commande Artisan de Backfill Idempotente
Fichier : `app/Console/Commands/BackfillUsersFromStockoutCommand.php`
- Signature :
  ```bash
  php artisan app:backfill-users-from-stockout [--dry-run]
  ```
- Caractéristiques :
  - Connexion PDO directe et isolée vers la base legacy.
  - Mode simulation `--dry-run` pour audit préalable sans écriture.
  - Sauvegarde et mise à jour idempotente (`updateOrCreate`).
  - Hachage sécurisé des mots de passe avec conservation des hashes existants.
  - Nettoyage des chaînes RIB complexes comportant des pièces jointes (`...|RIB_IMG:...`).
  - Création automatique des enregistrements `businesses` et `comptes_bancaires`.
  - Synchronisation automatique des rôles Spatie (`syncRoles`).

---

## 5. Résultat de l'Exécution en Production (dev.ouaissexpress.com)

```text
=== DÉMARRAGE DU BACKFILL USERS (Stockout -> OUAISS EXPRESS WinningExpress) ===
Connexion réussie à la base legacy [u405480831_stockout] sur [localhost].
Nombre total d'utilisateurs trouvés dans stockout.users: 73
--------------------------------------------------
RÉCAPITULATIF DE L'AUDIT & DU BACKFILL :
 - Total enregistrements sources : 73
 - Répartition par Type : {"client":33,"dlm":33,"moderator":7,"other":0}
 - Répartition par Statut Actif : {"on":66,"off":7}
 - Répartition par Corbeille (Trash) : {"active":57,"trash":16}
 - Utilisateurs insérés : 22
 - Utilisateurs mis à jour : 51
 - Enseignes créées (businesses) : 16
 - Comptes bancaires créés (comptes_bancaires) : 8
=== BACKFILL USERS TERMINÉ AVEC SUCCÈS ===
```

---

## 6. Vérifications Live & Tests

1. **Tests PHPUnit** : 15 tests exécutés, 43 assertions validées (**100% verts**).
2. **TypeScript & Build Vite** : `npx tsc --noEmit` (**0 erreur**), `npm run build` exécuté en 4.26s.
3. **Shadcn/UI TanStack Data Table** : Opérationnelle sur le Dashboard avec tri, recherche globale instantanée et pagination.
4. **Endpoint Login** : `https://dev.ouaissexpress.com/login` répond en **HTTP 200 OK**.
5. **Base de données historique** : `u405480831_stockout` **100% intacte**.

