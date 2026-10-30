# Chantier 03 — Authentification, Rôles & Design System

## Fiche d'identité
- **Domaine** : Sécurité, Authentification, Rôles Spatie, Shadcn/UI, Sidebar
- **Stack** : Laravel 13 + Spatie Permission + Inertia v3 + React 19 + Shadcn/UI (base-lyra) + Tailwind v4
- **Statut** : LIVRÉ & TESTÉ
- **Progression** : 100%

## Ce qui est FAIT
- [x] Spécification stricte des rôles : `admin`, `client`, `livreur`, `agence`, `operateur` dans `app/Domain/Auth/Enums/UserRole.php`.
- [x] Action applicative `RegisterClientAction` forçant impérativement le rôle `client` côté serveur.
- [x] Écran Login premium avec séparation visuelle, mémorisation de session et messages d'erreur contextualisés.
- [x] Écran Register complet avec sélection de profil (E-commerce, Entreprise, Particulier), ville du Maroc, téléphone marocain et accord CGU.
- [x] Protection CSRF, sessions sécurisées et Rate Limiting (5 tentatives par minute par IP).
- [x] Intégration avancée de Spatie Laravel Permission (Enums typés `Permission` & `UserRole`, `DashboardPolicy`, `UserPolicy`, `Gate::before` super-admin).
- [x] Redirections unifiées (guest vers `/login`, utilisateur connecté vers `/dashboard`).
- [x] Installation et initialisation de Shadcn/UI (`--preset b5dLKX1LE --template next`, style `base-lyra`).
- [x] Intégration du bloc `sidebar-07` dans `AuthenticatedLayout.jsx` avec navigation marocaine multi-hubs et profil dynamique.
- [x] Suite de tests unitaires et fonctionnels (15 tests, 43 assertions, 100% verts).

## Documents du chantier
- [01-SPECIFICATIONS-AUTH-ET-ROLES.md](01-SPECIFICATIONS-AUTH-ET-ROLES.md)
- [02-ECRANS-LOGIN-ET-REGISTER.md](02-ECRANS-LOGIN-ET-REGISTER.md)
- [03-SECURITE-RATE-LIMITING-ET-VALIDATION.md](03-SECURITE-RATE-LIMITING-ET-VALIDATION.md)
- [04-SPATIE-ROLES-PERMISSIONS-ET-POLICIES.md](04-SPATIE-ROLES-PERMISSIONS-ET-POLICIES.md)
- [05-SHADCN-UI-BASE-LYRA-ET-SIDEBAR-07.md](05-SHADCN-UI-BASE-LYRA-ET-SIDEBAR-07.md)
