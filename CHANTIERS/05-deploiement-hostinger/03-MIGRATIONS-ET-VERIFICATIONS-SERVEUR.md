# Migrations & Vérifications Serveur (dev.ouaissexpress.com)

## 1. Procédure de Déploiement Exécutée
1. **Précompilation locale des assets** :
   - `npm run build` exécuté localement avec succès en 2.86s.
   - Fichiers générés : `manifest.json`, `app-BrKrEBQm.css` (87.16 kB), `app-Bex2JsYL.js` (457.40 kB).
   - Versionnés dans Git sous `public/build/` pour garantir un déploiement autonome sans Node sur Hostinger.
2. **Synchronisation Git & Fichier .htaccess** :
   - Dépôt synchronisé sur `main` (`5be9f98`).
   - `.htaccess` racine configuré avec réécriture vers `public/` pour éviter l'erreur 403 Forbidden.
3. **Variables d'Environnement (.env)** :
   - Fichier `.env` de production sécurisé déployé dans `public_html/dev/`.
   - `APP_KEY` générée via `artisan key:generate --force`.
   - Connexion MySQL locale sur `127.0.0.1:3306` avec la base `u405480831_dev`.
4. **Exécution des Migrations MySQL** :
   - Commande : `/home/u405480831/bin/php artisan migrate --force`
   - Migrations jouées :
     - `0001_01_01_000000_create_users_table` (DONE)
     - `0001_01_01_000001_create_cache_table` (DONE)
     - `0001_01_01_000002_create_jobs_table` (DONE)
     - `2026_10_30_030000_add_client_fields_to_users_table` (DONE)
5. **Caches de Production** :
   - `config:cache` (DONE)
   - `route:cache` (DONE)
   - `view:cache` (DONE)
   - `storage:link` (DONE)

## 2. Protocole de Validation Live (Vérifications HTTP)
- `GET https://dev.ouaissexpress.com/` : **HTTP 200 OK** (Serveur LiteSpeed / PHP 8.4.27, Inertia React 19).
- `GET https://dev.ouaissexpress.com/build/assets/app-BrKrEBQm.css` : **HTTP 200 OK** (87.16 kB).
- `GET https://dev.ouaissexpress.com/build/assets/app-Bex2JsYL.js` : **HTTP 200 OK** (457.40 kB).
- `GET https://dev.ouaissexpress.com/login` : **HTTP 200 OK** (Cookies XSRF & session sécurisée).
- `GET https://dev.ouaissexpress.com/register` : **HTTP 200 OK** (Rôle client forcé, validation Zod).
- `POST https://dev.ouaissexpress.com/api/tarification/calculate` : **HTTP 200 OK** (JSON calculé en direct).
- `POST https://dev.ouaissexpress.com/api/tracking/search` : **HTTP 200 OK** (JSON historique d'étapes en direct).
- `GET https://ouaissexpress.com/` : **HTTP 200 OK** (Ancien site 100% stable sur PHP 7.4.33).
