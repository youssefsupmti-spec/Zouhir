# 07 — Unicité Scopée par Client des Enseignes & Backfill des 33 Businesses

**Date** : 30 octobre 2026  
**Auteur** : Gemini (Antigravity)  
**Chantier** : `C132` (`ouaissexpress-zouhir`) & `C105` (`normalisation-base-de-donnees`)  
**Projets Liés** : `OUAISS EXPRESS` (`Zouhir`), Référence `WinningExpress` & `Zakaria2`, Hub Central `Zakaria_Nouveau_102`  
**Statut** : **LIVRÉ, TESTÉ (22 TESTS VERTS) ET VÉRIFIÉ EN PRODUCTION (DEV)**  
**Niveau de Preuve** : `VERIFIED IN PRODUCTION`  

---

## 1. Vérification de l'Architecture de Référence (`Zakaria2` & `WinningExpress`)

À la demande explicite de l'utilisateur :
> *"verifier que dans zakria2 le Buissnes unique selmenet au niveau meme client pas unique dans table global verifier"*

### A. Constat dans `Zakaria2`
- Migration [`2026_06_28_120000_create_businesses_table.php`](file:///c:/Users/youssef/Documents/Zakaria2/database/migrations/2026_06_28_120000_create_businesses_table.php) :
  ```php
  // name NON unique : les store legacy ne sont pas uniques (doublons / vides).
  $table->string('name');
  $table->index('name');
  ```
  Le champ `name` **n'a jamais été unique globalement**. Deux clients différents peuvent posséder une enseigne portant le même libellé.
- Migration [`2026_06_28_120100_seed_businesses_from_users.php`](file:///c:/Users/youssef/Documents/Zakaria2/database/migrations/2026_06_28_120100_seed_businesses_from_users.php) :
  ```sql
  INSERT INTO businesses (user_id, name, ...)
  SELECT u.id, COALESCE(NULLIF(TRIM(u.store), ''), u.fullname), ...
  ```
  Repli automatique sur `fullname` dès que `store` est vide.

### B. Constat dans `WinningExpress`
- Migration [`2026_07_12_300000_create_businesses_table_and_backfill.php`](file:///c:/Users/youssef/Desktop/Project/winningExpressLaravelFilament/database/migrations/2026_07_12_300000_create_businesses_table_and_backfill.php) :
  `name` est une simple colonne `varchar(255)` indexée, sans unicité globale, et le backfill applique également :
  `COALESCE(NULLIF(TRIM(u.store), ''), u.fullname)`.

---

## 2. Règle Métier & Implémentation Appliquée

1. **Unicité stricte par client (`user_id`, `name`)** :
   - Migration [`database/migrations/2026_10_30_070000_ensure_businesses_scoped_uniqueness.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/database/migrations/2026_10_30_070000_ensure_businesses_scoped_uniqueness.php) :
     ```php
     $table->unique(['user_id', 'name'], 'businesses_user_id_name_unique');
     ```
   - Un même client ne peut pas créer deux enseignes avec le même nom.
   - En revanche, deux clients différents **peuvent avoir le même nom d'enseigne sans aucun conflit** (ex: Client #46 « Ahmed » et Client #58 « Ahmed »).
2. **Génération automatique inspirée du nom du client** :
   - Mise à jour de [`app/Console/Commands/BackfillUsersFromStockoutCommand.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/app/Console/Commands/BackfillUsersFromStockoutCommand.php) :
     ```php
     $businessName = trim($row['store'] ?? '');
     if ($businessName === '') {
         $businessName = trim($row['fullname'] ?? '') ?: ('Boutique #' . $id);
     }
     ```
   - Synchronisation de `users.store_name` et de `businesses.name` : **100% des clients possèdent désormais une enseigne active**.

---

## 3. Validation par Tests Automatisés

Création du test dédié [`tests/Feature/BusinessScopedUniquenessTest.php`](file:///c:/Users/youssef/Desktop/Project/Zouhir/tests/Feature/BusinessScopedUniquenessTest.php) :
- `test_different_clients_can_have_businesses_with_the_exact_same_name` : vérifie avec succès que deux clients distincts créent simultanément une enseigne « Boutique Express ».
- `test_same_client_cannot_have_duplicate_business_names` : vérifie qu'une tentative de doublon pour le même client lève une exception `QueryException` (violation de la contrainte composite).

**Résultats PHPUnit globaux** :
- **22 tests passants sur 22 (100% verts)**
- **73 assertions validées**
- 0 erreur, 0 échec.

---

## 4. Bilan Vérifié en Production (`u405480831_dev`)

Exécution en direct sur le serveur Hostinger :
```
TOTAL BUSINESSES IN DEV : 33
CLIENTS SANS BUSINESS : 0 (0%)
```

### Liste Complète des 33 Enseignes Marchandes Actives en BDD :
| ID Business | ID Client | Nom Client | Nom Enseigne (`businesses.name`) | Ville | Origine du Nom |
| :---: | :---: | :--- | :--- | :--- | :--- |
| **01** | User #45 | Speedo | **Anouar** | *(non renseignée)* | Legacy `store` |
| **02** | User #49 | Oukniline | **Oukniline** | Aghbalou nserdane | Legacy `store` |
| **03** | User #59 | Ayoub | **Store** | Sidi taibi | Legacy `store` |
| **04** | User #62 | Alta | **Alta** | Sale | Legacy `store` |
| **05** | User #63 | Loubna Erouidi | **Irysma** | *(non renseignée)* | Legacy `store` |
| **06** | User #66 | Oubella zakrix | **Zakrix** | Sale | Legacy `store` |
| **07** | User #70 | Winning | **Express** | Sale | Legacy `store` |
| **08** | User #71 | Group for delivery | **G4d** | *(non renseignée)* | Legacy `store` |
| **09** | User #96 | AMINE AMARI | **Clenxpert.ma (Instagram)** | TEMARA | Legacy `store` |
| **10** | User #100 | Sarazakys | **Sarazakys** | Sale | Legacy `store` |
| **11** | User #101 | Optigo.ouaisexpres | **Optigo** | Casablanca | Legacy `store` |
| **12** | User #103 | Mohamed boussague | **Boutique Facebook** | Salé | Legacy `store` |
| **13** | User #108 | Abdelouahab tanji | **Vivana.ma (Site Web)** | Temara | Legacy `store` |
| **14** | User #109 | Marouane hajjaj | **Bio Elixir (Instagram)** | Tamesna | Legacy `store` |
| **15** | User #111 | libourkiloubna | **Loubna Hmizat (Instagram)** | temara | Legacy `store` |
| **16** | User #112 | CAVSTORE | **Cavstore (Facebook)** | Salé | Legacy `store` |
| **17** | User #46 | Ahmed | **Ahmed** | Sale | **Généré depuis nom client** |
| **18** | User #58 | Ahmed | **Ahmed** | Sale | **Généré depuis nom client** |
| **19** | User #64 | Ouais express | **Ouais express** | *(non renseignée)* | **Généré depuis nom client** |
| **20** | User #65 | Hicham | **Hicham** | *(non renseignée)* | **Généré depuis nom client** |
| **21** | User #88 | test aymane | **test aymane** | *(non renseignée)* | **Généré depuis nom client** |
| **22** | User #90 | zsrezqs sqfdrsq | **zsrezqs sqfdrsq** | rabat | **Généré depuis nom client** |
| **23** | User #91 | Oumayma Zaid | **Oumayma Zaid** | Harhoura | **Généré depuis nom client** |
| **24** | User #92 | Sakkal samiha | **Sakkal samiha** | Tanger | **Généré depuis nom client** |
| **25** | User #93 | Zakrix | **Zakrix** | Temara | **Généré depuis nom client** |
| **26** | User #94 | Fatiha ahnini | **Fatiha ahnini** | Ain el aouda | **Généré depuis nom client** |
| **27** | User #95 | aideriar | **aideriar** | New York | **Généré depuis nom client** |
| **28** | User #98 | PixaMaroc | **PixaMaroc** | Temara | **Généré depuis nom client** |
| **29** | User #105 | ayoub lamghari | **ayoub lamghari** | temara | **Généré depuis nom client** |
| **30** | User #106 | RABAT DECO | **RABAT DECO** | Rabat | **Généré depuis nom client** |
| **31** | User #114 | Amalita | **Amalita** | Temara | **Généré depuis nom client** |
| **32** | User #115 | هشام ايت بوضروب | **هشام ايت بوضروب** | عين العودة | **Généré depuis nom client** |
| **33** | User #116 | أناقة البلدي | **أناقة البلدي** | Temara | **Généré depuis nom client** |

- **HTTP 200** confirmé en continu sur `https://dev.ouaissexpress.com/`, `/login` et `/register`.

