# Sécurité, Rate Limiting & Validation

## 1. Protection Anti-Brute Force (Rate Limiting)
- Implémenté dans `app/Http/Requests/Auth/LoginRequest.php` via `RateLimiter::hit()` et `RateLimiter::tooManyAttempts()`.
- Seuil : 5 tentatives consécutives infructueuses par combinaison email + adresse IP.
- Blocage temporaire de 60 secondes avec retour HTTP 429 et message explicite en français.

## 2. Protection de Session & CSRF
- Token CSRF renouvelé automatiquement à chaque requête via le middleware Inertia.
- Régénération systématique de l'ID de session après chaque connexion réussie (`$request->session()->regenerate()`) pour empêcher toute fixation de session.
- Déconnexion avec invalidation complète de session (`$request->session()->invalidate()`) et régénération du token CSRF (`regenerateToken()`).
