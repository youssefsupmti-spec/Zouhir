# Architecture Logicielle — OUAISS EXPRESS (DDD Pragmatique)

Ce document décrit l'architecture logicielle retenue pour la plateforme **OUAISS EXPRESS**, conçue pour évoluer d'une Phase 1 (Landing, Auth, Inscription) vers un écosystème complet de transport et messagerie express sans dette technique.

---

## 🏛️ 1. Principes Directeurs

1. **Domain-Driven Design (DDD) Pragmatique** :
   - Le code est structuré par domaines métier (Bounded Contexts) plutôt que par type de fichier purement technique.
   - Les entités, Enums et règles métier résident dans `app/Domain/`.
   - Les cas d'utilisation (use cases) résident dans `app/Application/`.
   - L'infrastructure et les adapteurs externes résident dans `app/Infrastructure/`.
   - La couche de présentation (Controllers, Requests, Inertia Responses) réside dans `app/Http/`.

2. **Séparation Stricte des Responsabilités** :
   - Les contrôleurs sont légers (Thin Controllers) : ils délèguent l'orchestration métier aux Actions et Services.
   - La validation est isolée dans des `FormRequest` dédiées.
   - Les autorisations et sécurité des rôles sont vérifiées côté serveur.

3. **Monolithe Moderne Réactif (Inertia.js + React 19)** :
   - Évite la complexité d'une API REST / GraphQL découplée tout en offrant l'expérience utilisateur d'une Single Page Application (SPA).
   - Pas de rechargement complet de page, transitions fluides, validation instantanée.

---

## 📦 2. Bounded Contexts Préparés

### A. Domaine `Auth` & Identité (`app/Domain/Auth/`)
- **Rôle** : Gestion des utilisateurs, des sessions et des rôles d'accès.
- **Enums** :
  - `UserRole` : `admin`, `client`, `livreur`, `agence`, `operateur`.
- **Règles d'or** :
  - Aucun utilisateur ne peut s'auto-attribuer un rôle autre que `client` lors de l'inscription publique.
  - Tout compte inactif (`is_active = false`) est rejeté dès l'authentification.

### B. Domaine `Clients` (`app/Domain/Clients/`)
- **Rôle** : Profils marchands et entreprises expéditrices.
- **Enums** :
  - `ClientType` : `e_commerce`, `entreprise`, `particulier`.
- **Champs métier** :
  - `store_name`, `phone` (WhatsApp), `city`, `address`.

### C. Domaine `Colis` (`app/Domain/Colis/`) — *Préparé pour les phases futures*
- **Rôle** : Cycle de vie du colis, statuts d'acheminement, simulateur public.
- **Enums** :
  - `ColisStatus` : `nouveau`, `ramasse`, `expedie`, `en_transit`, `en_cours_livraison`, `livre`, `reporte`, `retour_en_cours`, `retourne`, `annule`.
- **Services** :
  - Calculateur de frais d'envoi et gestion des surtaxes de poids et frais CRBT.
  - Moteur de timeline et traçabilité pour les clients.

---

## 🔄 3. Flux de Données Typique (Exemple : Inscription Marchand)

```
[Navigateur / Client React 19]
             │
             │  POST /register (Données formulaire Zod)
             ▼
[Routing Laravel + CSRF + RateLimiter]
             │
             ▼
[RegisterRequest (Validation des types & unicité)]
             │
             ▼
[AuthController@register]
             │
             ▼
[RegisterClientAction (Couche Application)]
             │  - Nettoyage des chaînes
             │  - Hachage sécurisé du mot de passe (Bcrypt / Argon2)
             │  - Assignation FORCÉE de UserRole::CLIENT
             ▼
[User::create() (Persistance Eloquent)]
             │
             ▼
[Auth::login($user) + Session::regenerate()]
             │
             ▼
[Inertia Redirect('/') avec Flash Toast Success]
```

---

## 🔒 4. Sécurité & Bonnes Pratiques

- **Protection CSRF** native sur toutes les requêtes d'écriture.
- **Protection contre le Brute Force** : Rate limiting à 5 tentatives échouées par minute par combinaison IP/Email.
- **Protection XSS** : Rendu React sans injection brute de HTML.
- **Prévention des escalades de privilèges** : Le rôle administratif n'est jamais paramétrable depuis une requête publique.
