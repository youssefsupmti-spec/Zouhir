# Cadrage Technique de l'Hébergement Hostinger Shared

## 1. Contraintes Techniques
1. **Pas de démon Node.js permanent** : Les assets React / Vite / Tailwind sont compilés localement dans `public/build/` avant déploiement.
2. **Version PHP CLI vs Web** :
   - Web : Apache exécute PHP 8.4 pour le sous-domaine `dev.ouaissexpress.com`.
   - CLI : Alias et symlink `~/bin/php` configurés pour pointer sur `/opt/alt/php84/usr/bin/php`.
3. **Document Root** : `public_html/dev` avec réécriture vers `public/` via `.htaccess`.

## 2. Base de Données Dédiée
- Base : `u405480831_dev`
- Utilisateur : `u405480831_dev`
- Hôte local : `127.0.0.1` (socket Unix ou port 3306).
