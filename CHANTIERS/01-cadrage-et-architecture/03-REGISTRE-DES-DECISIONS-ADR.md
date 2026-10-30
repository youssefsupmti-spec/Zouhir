# 03 — Registre des Décisions d'Architecture (ADR)

**Date** : 30 Octobre 2026  
**Chantier** : `01-cadrage-et-architecture`  

---

## ADR-001 : Monolithe Laravel 13 + Inertia.js v3 + React 19
- **Décision** : Monolithe couplé via Inertia.js au lieu d'une API découplée.
- **Raison** : Expérience utilisateur SPA immédiate sans la complexité de maintenance d'une API indépendante. Idéal pour l'hébergement mutualisé Hostinger grâce aux assets précompilés.

## ADR-002 : Tailwind CSS v4 via `@tailwindcss/vite`
- **Décision** : Utilisation native de Tailwind v4 sans `tailwind.config.js`.
- **Raison** : Performance de build 10x supérieure (10s de build complet) et déclaration des thèmes dans `@theme` dans le CSS.

## ADR-003 : Forçage du Rôle Côté Serveur
- **Décision** : Ne jamais accepter le rôle utilisateur dans le payload HTTP d'inscription.
- **Raison** : Élimine le risque d'escalade de privilèges vers `admin` ou `livreur`.

## ADR-004 : Précompilation Locale & Absence de Node en Production
- **Décision** : Aucune exécution permanente de Node.js sur le serveur Hostinger.
- **Raison** : Contraintes de l'hébergement mutualisé. Les bundles CSS/JS sont construits en local avant déploiement.
