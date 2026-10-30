# 01 — Audit Initial & Cahier des Charges

**Date** : 30 Octobre 2026  
**Chantier** : `01-cadrage-et-architecture`  
**Client** : Zouhir (Ouais Express)  

---

## 🎯 1. Contexte du Client

Le client **ZOUHIR** crée sa société de livraison de colis au Maroc sous le nom commercial **OUAISS EXPRESS**.
Les projets existants **WinningExpress** et **Zakaria2** sont des références techniques (calculs de tarification, flux logistiques, reversement CRBT) mais **ne doivent en aucun cas être copiés visuellement**.

## 🚀 2. Exigences Techniques
- **Stack** : React 19 + Inertia.js + Tailwind CSS v4 + shadcn/ui + Laravel 13 + PHP 8.4/8.5 + MySQL 8.
- **Hébergement** : Hostinger Shared (environnement mutualisé, document root `public/`, pas de Node permanent en production).
- **Couleurs officielles** :
  - Primaire : `#004c68` (Bleu Océan)
  - Secondaire : `#ff8806` (Orange Livraison)
- **Périmètre Phase 1** :
  1. Landing Page premium et interactive.
  2. Authentification (Login).
  3. Inscription client (Register).
  4. Migrations BDD et tests automatisés.
