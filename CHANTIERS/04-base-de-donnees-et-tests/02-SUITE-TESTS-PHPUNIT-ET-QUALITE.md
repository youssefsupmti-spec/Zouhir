# Suite de Tests PHPUnit & Contrôles de Qualité

## 1. Résultats des Tests Automatisés
Exécution locale : `php vendor/phpunit/phpunit/phpunit --testdox`
- **Résultat** : 12 tests exécutés, 37 assertions, 100% au vert.
- Couverture :
  - `LandingTest` : affichage page d'accueil, calcul devis livraison Casablanca -> Marrakech, calcul devis sans ville, suivi colis valide, suivi colis vide.
  - `AuthTest` : affichage écran login, connexion réussie d'un client actif, échec de connexion mot de passe erroné, blocage compte inactif, affichage écran inscription, inscription client réussie avec assignation rôle client immuable, validation champs requis à l'inscription, déconnexion sécurisée.

## 2. Contrôle Statique TypeScript
- Commande : `npx tsc --noEmit`
- Résultat : 0 erreur détectée. Configuration adaptée à TypeScript 7 / Vite Bundler.
