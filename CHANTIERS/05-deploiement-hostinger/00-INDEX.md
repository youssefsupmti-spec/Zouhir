# Chantier 05 — Déploiement Hostinger & Environnement Production

## Fiche d'identité
- **Domaine** : DevOps, Déploiement CloudLinux/Hostinger, Subdomain dev
- **Statut** : DÉPLOYÉ & EN COURS DE VÉRIFICATION
- **Progression** : 95% (Phase 1)

## Architecture Cible
- **Hébergement** : Hostinger Premium / CloudLinux
- **Serveur SSH** : `147.79.103.78:65002` (compte `u405480831`)
- **Base MySQL** : `u405480831_dev` sur `127.0.0.1:3306`
- **Isolation** :
  - Ancien site : `ouaissexpress.com` dans `public_html/` (maintenu en PHP 7.4 via `AddHandler application/x-httpd-ea-php74 .php`).
  - Nouveau site Phase 1 : `dev.ouaissexpress.com` dans `public_html/dev/` (exécuté en PHP 8.4 avec assets précompilés).

## Documents du chantier
- [01-CADRAGE-HOSTINGER-SHARED.md](01-CADRAGE-HOSTINGER-SHARED.md)
- [02-INSTANCE-DEV-OUAISSEXPRESS.md](02-INSTANCE-DEV-OUAISSEXPRESS.md)
- [03-MIGRATIONS-ET-VERIFICATIONS-SERVEUR.md](03-MIGRATIONS-ET-VERIFICATIONS-SERVEUR.md)
