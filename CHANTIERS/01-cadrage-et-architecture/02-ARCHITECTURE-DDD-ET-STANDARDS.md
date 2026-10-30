# 02 — Architecture DDD Pragmatique & Standards

**Date** : 30 Octobre 2026  
**Chantier** : `01-cadrage-et-architecture`  

---

## 🏛️ Découpage Bounded Contexts

Le code source applique une séparation stricte des couches dans `app/` :

```
app/
├── Domain/                         # Cœur métier (Entities, Enums)
│   ├── Auth/
│   │   └── Enums/UserRole.php      # ADMIN, CLIENT, LIVREUR, AGENCE, OPERATEUR
│   ├── Clients/
│   │   └── Enums/ClientType.php    # E_COMMERCE, ENTREPRISE, PARTICULIER
│   └── Colis/
│       └── Enums/ColisStatus.php   # Statuts logistiques (préparé pour Phase 2+)
├── Application/                    # Actions & Cas d'utilisation
│   └── Auth/
│       └── Actions/RegisterClientAction.php  # Forçage strict de UserRole::CLIENT
├── Http/                           # Présentation & Requêtes HTTP
│   ├── Controllers/
│   │   ├── Auth/AuthController.php
│   │   └── LandingController.php
│   └── Requests/Auth/
│       ├── LoginRequest.php
│       └── RegisterRequest.php
└── Models/
    └── User.php
```

### Règles d'or :
1. Les contrôleurs ne contiennent pas de requêtes complexes ni de logique métier brute.
2. Tout cas d'utilisation modifiant l'état passe par une Action dédiée.
3. Le rôle attribué lors de l'inscription est forcé côté serveur à `client`.
