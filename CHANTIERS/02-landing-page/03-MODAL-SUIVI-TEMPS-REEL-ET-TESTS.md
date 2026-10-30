# Modal de Suivi Colis en Temps Réel & Tests d'Intégration

## 1. Objectif Fonctionnel
Permettre à tout visiteur de saisir un code de suivi (ex: `OUA-2026-CAS-001` ou tout format colis) et d'obtenir immédiatement l'état d'avancement du colis dans une fenêtre modale moderne avec timeline interactive.

## 2. Composant Frontend
- Composant : `resources/js/Components/TrackingSimulatorModal.jsx`
- Intégré dans la Navbar (`Navbar.jsx`) et le Hero (`HeroSection.jsx`).
- Animation fluide, étapes visuelles claires (Pris en charge, En transit, En cours de livraison, Livré), indicateur de statut badge dynamique.
- Gestion d'état React : chargement, succès, code introuvable avec message convivial.

## 3. Endpoint Backend
- Route : `POST /api/track-parcel`
- Contrôleur : `app/Http/Controllers/LandingController.php@trackParcel`
- Validation : code de suivi obligatoire, assaini.
- Données renvoyées : statut normalisé, expéditeur, destinataire, ville, historique d'étapes horodatées.

## 4. Tests Automatisés
- `tests/Feature/LandingTest.php` :
  - Test de validation du tracking sans code (retour 422).
  - Test de suivi avec code valide (retour 200, structure JSON validée).
  - Test du simulateur de tarifs avec différentes villes et poids.
