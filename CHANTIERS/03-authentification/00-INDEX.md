# Chantier 03 — Authentification & Gestion des Accès

## Fiche d'identité
- **Domaine** : Sécurité, Authentification, Session Client
- **Stack** : Laravel 13 + Inertia v3 + React 19 + TypeScript + Zod + React Hook Form
- **Statut** : LIVRÉ & TESTÉ
- **Progression** : 100% (Phase 1)

## Ce qui est FAIT
- [x] Spécification stricte des rôles : `admin`, `client`, `livreur`, `agence`, `operateur` dans `app/Domain/Auth/Enums/UserRole.php`.
- [x] Action applicative `RegisterClientAction` forçant impérativement le rôle `client` côté serveur.
- [x] Écran Login premium avec séparation visuelle, mémorisation de session et messages d'erreur contextualisés.
- [x] Écran Register complet avec sélection de profil (E-commerce, Entreprise, Particulier), ville du Maroc, téléphone marocain et accord CGU.
- [x] Protection CSRF, sessions sécurisées et Rate Limiting (5 tentatives par minute par IP).
- [x] Suite de tests unitaires et fonctionnels (Login, Register, Logout, Validation).

## Documents du chantier
- [01-SPECIFICATIONS-AUTH-ET-ROLES.md](01-SPECIFICATIONS-AUTH-ET-ROLES.md)
- [02-ECRANS-LOGIN-ET-REGISTER.md](02-ECRANS-LOGIN-ET-REGISTER.md)
- [03-SECURITE-RATE-LIMITING-ET-VALIDATION.md](03-SECURITE-RATE-LIMITING-ET-VALIDATION.md)
