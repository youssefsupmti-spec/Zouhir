# Écrans Login & Inscription

## 1. Écran de Connexion (`Login.jsx`)
- Disposition split-screen responsive :
  - Colonne gauche : formulaire de connexion épuré avec saisie email, mot de passe avec bascule afficher/masquer, case "Se souvenir de moi" et lien mot de passe oublié.
  - Colonne droite : visuel de marque OUAISS EXPRESS avec mise en avant des atouts (réseau national, traçabilité temps réel, support dédié).
- Feedback instantané : affichage des alertes d'erreur en cas d'identifiants incorrects ou compte inactif.

## 2. Écran d'Inscription (`Register.jsx`)
- Sélection intuitive du profil d'activité : E-commerce, Entreprise ou Particulier.
- Formulaire structuré en 2 colonnes sur grand écran :
  - Nom complet & Nom de l'entreprise.
  - Email professionnel & Téléphone au format national (+212 / 06...).
  - Ville de rattachement (sélecteur complet des principales villes du Maroc).
  - Mot de passe sécurisé et confirmation.
  - Case à cocher d'acceptation des Conditions Générales d'Utilisation.
- Validation côté client via Zod et réconciliation automatique avec les erreurs renvoyées par Laravel Inertia.
