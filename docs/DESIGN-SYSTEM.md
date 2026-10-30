# Design System — OUAISS EXPRESS

Ce document définit l'identité visuelle, la palette de couleurs, la typographie et la charte graphique de la marque **OUAISS EXPRESS**.

---

## 🎨 1. Palette Chromatique Officielle

L'identité chromatique d'Ouais Express repose sur un contraste fort entre la rigueur institutionnelle du bleu océan et l'énergie du secteur de la livraison express portée par l'orange.

| Rôle | Hex | Nom Commercial | Utilisation |
|---|---|---|---|
| **Primary** | `#004c68` | *Ouais Ocean Deep* | Boutons d'action principaux, titres H1/H2, navbar contrastée, logos |
| **Primary Hover** | `#00384d` | *Ouais Ocean Dark* | États survol et focus des éléments primaires |
| **Primary Light** | `#e6f1f5` | *Ouais Ocean Mist* | Arrière-plans de section, badges légers, bordures douces |
| **Secondary** | `#ff8806` | *Ouais Express Orange* | Accents visuels, boutons de conversion "Devenir Marchand", statut actif |
| **Secondary Hover** | `#e07400` | *Ouais Amber Force* | États survol des boutons secondaires et CTA |
| **Secondary Light** | `#fff4e6` | *Ouais Orange Cream*| Badges d'urgence, surlignages de texte, indicateurs de livraison |
| **Brand Dark** | `#05161f` | *Ouais Night Slate* | Footer immersif, cartes corporate haute précision |
| **Brand Surface**| `#0a2533` | *Ouais Hub Surface* | Arrière-plans des compteurs en direct et cartes technologiques |
| **Brand Border** | `#1a4961` | *Ouais Electric Edge* | Bordures subtiles sur fond sombre |

---

## 🔤 2. Typographie

Deux polices complémentaires sont chargées via Google Fonts :

1. **Titres & Identité de Marque : *Outfit***
   - Poids : `600` (SemiBold), `700` (Bold), `800` (ExtraBold), `900` (Black).
   - Usage : Titres de sections, chiffres des statistiques, logos, badges d'accentuation.
   - Caractéristiques : Géométrique, moderne, impactante.

2. **Corps de Texte & Interfaces : *Plus Jakarta Sans***
   - Poids : `400` (Regular), `500` (Medium), `600` (SemiBold), `700` (Bold).
   - Usage : Paragraphes, labels de formulaires, tableaux, descriptions.
   - Caractéristiques : Excellente lisibilité sur tous les écrans, optimisée pour l'ergonomie applicative.

---

## 🧩 3. Composants UI (Inspiration shadcn/ui)

- **`Button`** : Variantes `primary`, `secondary`, `outline`, `ghost`, `dark`. Supporte l'état `isLoading` avec spinner animé et gestion des icônes gauche/droite.
- **`Input`** : Support des icônes intégrées, gestion des erreurs de validation réactive, labels et textes d'aide.
- **`Badge`** : Variantes de statuts colorés avec point lumineux clignotant (`dot={true}`).
- **`Card`** : Structure modulaire `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter` avec effet de survol `hoverEffect`.
- **`Modal`** : Fenêtre modale accessible avec gestion de la touche Échap, verrouillage du défilement et flou d'arrière-plan.
- **`Logo`** : Composant vectoriel SVG réactif avec bascule fond sombre / fond clair.

---

## 📱 4. Accessibilité & Responsive

- Ratios de contraste conformes aux normes **WCAG AA** pour le texte.
- Layout 100% responsive testé sur Mobile (375px+), Tablette (768px+) et Desktop (1280px+).
- Formulaires dotés d'attributs `aria-label`, d'états de focus visibles et de retours visuels clairs.
