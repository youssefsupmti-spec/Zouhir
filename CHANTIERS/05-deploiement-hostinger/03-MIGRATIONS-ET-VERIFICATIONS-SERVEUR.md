# Migrations & Vérifications Serveur

## 1. Procédure de Déploiement
1. Précompilation locale des assets : `npm run build`.
2. Synchronisation des fichiers sources et des assets compilés vers le serveur.
3. Installation des dépendances Composer de production :
   `/home/u405480831/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader`
4. Exécution des migrations MySQL :
   `/home/u405480831/bin/php artisan migrate --force`
5. Mise en cache de la configuration, des routes et des vues :
   `/home/u405480831/bin/php artisan config:cache`
   `/home/u405480831/bin/php artisan route:cache`
   `/home/u405480831/bin/php artisan view:cache`
   `/home/u405480831/bin/php artisan storage:link`

## 2. Protocole de Validation Live
- Test HTTP de `https://dev.ouaissexpress.com` (Statut 200).
- Test du calcul de tarif en direct.
- Test de la modale de suivi de colis en direct.
- Test de la page de connexion (`/login`).
- Test de la page d'inscription (`/register`).
- Vérification que l'ancien site `https://ouaissexpress.com` fonctionne sans interruption.
