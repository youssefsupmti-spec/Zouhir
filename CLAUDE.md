# CLAUDE.md — Référence Centrale du Projet OUAISS EXPRESS (Client : ZOUHIR)

Plateforme indépendante de livraison express et logistique e-commerce au Maroc pour le client **ZOUHIR** (Marque commerciale : **OUAISS EXPRESS** · Domaines : `ouaissexpress.com` / `dev.ouaissexpress.com`).

---

## 🎯 MISSION & PÉRIMÈTRE STRICT (PHASE 1)

Ce projet utilise **WinningExpress** et **Zakaria2** uniquement comme références fonctionnelles et techniques, et **ne doit jamais être une copie conforme de leurs interfaces ni de leur code**.

### Périmètre Phase 1 (Livré et Validé) :
1. **Fondation technique & Architecture DDD pragmatique** (Domain, Application, Infrastructure, Presentation).
2. **Landing Page Premium Internationale** : Vitrine corporate, simulateur interactif de tarifs, widget de suivi de colis par satellite en temps réel, garanties 24/48h & CRBT à J+2.
3. **Authentification & Inscription Fonctionnelles** : Login et Register complets avec React Hook Form, Zod, validation Laravel stricte, CSRF, rate limiting, protection contre l'escalade de privilèges (rôle forcé côté serveur).
4. **Design System Original OUAISS EXPRESS** : Couleurs officielles `#004c68` (Deep Ocean Blue) et `#ff8806` (Vibrant Delivery Orange), typographie *Inter* (français) et *Droid Arabic Kufi* (arabe), auto-hébergées.
5. **Préparation Déploiement Hostinger Shared** : Document root `public/`, assets précompilés (aucun daemon Node en production), `.env` sécurisé, migrations Phase 1 non destructives.

### ⛔ INTERDICTIONS FORMELLES (Règles permanentes) :
- **NE PAS développer** le Dashboard interne, la gestion des colis/expéditions, les livreurs, le stock, la facturation, les paiements, le scanner, les bons de livraison/retour, l'import Excel, le reporting ni aucun autre module métier sans demande explicite du propriétaire.
- **NE PAS copier** textuellement ou visuellement les interfaces de WinningExpress ou Zakaria2.
- **NE JAMAIS exécuter** `migrate:fresh`, `db:wipe` ou toute migration destructive.
- **NE PAS déployer**, commit ou push en production sans validation explicite du propriétaire.

---

## 🛠️ STACK TECHNIQUE OFFICIELLE

| Composant | Technologie | Version / Remarques |
|---|---|---|
| **Backend** | Laravel | Framework 13.x |
| **Langage Backend** | PHP | 8.4+ (Compatible PHP 8.5 Hostinger) |
| **Architecture** | DDD Pragmatique | Domain / Application / Infrastructure / Presentation |
| **Frontend Framework** | React | React 19 (19.3+) |
| **Liaison Monolithe** | Inertia.js | Inertia React Adapter v3.x |
| **Styles & CSS** | Tailwind CSS v4 | `@tailwindcss/vite` + Design Tokens |
| **UI Components** | Custom shadcn/ui | Primitives modulaires, Radix & Lucide React |
| **Validation Formulaires** | React Hook Form + Zod | Couplé aux FormRequests Laravel |
| **Bundler** | Vite 8 | Configuration ESM moderne avec `@vitejs/plugin-react` |
| **Base de Données** | MySQL 8 | Hostinger Shared DB `u405480831_dev` |
| **Hébergement Cible** | Hostinger Premium Shared | Document root `public/`, CLI SSH, pas de Docker |

---

## 🏛️ STRUCTURE DU CODE (DDD PRAGMATIQUE)

```
app/
├── Domain/                         # Cœur métier (Entities, Enums, Value Objects)
│   ├── Auth/
│   │   └── Enums/UserRole.php      # ADMIN, CLIENT, LIVREUR, AGENCE, OPERATEUR
│   ├── Clients/
│   │   └── Enums/ClientType.php    # E_COMMERCE, ENTREPRISE, PARTICULIER
│   └── Colis/                      # Bounded Context préparé pour les futures phases
│       └── Enums/ColisStatus.php   # NOUVEAU, RAMASSE, EXPEDIE, EN_TRANSIT, LIVRE...
├── Application/                    # Cas d'utilisation & Actions
│   └── Auth/
│       └── Actions/RegisterClientAction.php  # Rôle forcé CLIENT côté serveur
├── Http/                           # Présentation Web / Inertia
│   ├── Controllers/
│   │   ├── Auth/AuthController.php
│   │   └── LandingController.php
│   ├── Requests/Auth/
│   │   ├── LoginRequest.php        # Rate limiting + verrouillage de compte
│   │   └── RegisterRequest.php     # Validation forte (MDP, téléphone, unicité email)
│   └── Middleware/
│       └── HandleInertiaRequests.php
└── Models/
    └── User.php                    # Casts d'Enums, sécurité mass-assignment
```

---

## 🎨 IDENTITÉ VISUELLE & COULEURS

- **Couleur Primaire** : `#004c68` (Bleu Océan Profond — Stabilité, confiance institutionnelle)
- **Couleur Secondaire** : `#ff8806` (Orange Livraison Vif — Vitesse, dynamisme logistique)
- **Surfaces Sombres** : `#05161f` (Fond footer & cartes premium), `#0a2533` (Surfaces de contraste)
- **Typographie (règle du propriétaire, 01/11/2026, commune aux trois projets)** : *Inter* pour tout le texte français
  (titres compris, classe `font-sans`), *Droid Arabic Kufi* pour tout texte arabe — automatique, même sans `lang="ar"`
  (`resources/css/app.css` prête ses glyphes arabes à « Inter Variable »). Auto-hébergées : Inter par
  `@fontsource-variable/inter`, Droid Arabic Kufi dans `resources/fonts/`. Aucun Google Fonts, aucune autre famille
  (Outfit, Plus Jakarta Sans…) sans décision du propriétaire.

---

## 🧪 COMMANDES ESSENTIELLES DU PROJET

```bash
# Compilation des assets (développement)
npm run dev

# Compilation des assets (production Hostinger)
npm run build

# Vérification stricte TypeScript (0 erreur tolérée)
npx tsc --noEmit

# Exécution de la suite de tests automatisés (100% verts)
php vendor/phpunit/phpunit/phpunit --testdox

# Application des migrations locales
php artisan migrate
```

---

## 🔒 SÉCURITÉ & BONNES PRATIQUES

1. **Rôles sécurisés** : L'inscription publique force impérativement `UserRole::CLIENT`. Aucun paramètre de rôle envoyé par le navigateur n'est pris en compte.
2. **Rate Limiting** : `LoginRequest` protège contre les attaques brute-force (5 tentatives max par IP/email avant verrouillage temporaire).
3. **Protection CSRF** : Active sur toutes les routes POST (`/login`, `/register`, `/logout`, `/api/*`).
4. **Secrets protégés** : Aucun secret, identifiant SSH ou mot de passe de base de données n'est commité en clair dans le code.
