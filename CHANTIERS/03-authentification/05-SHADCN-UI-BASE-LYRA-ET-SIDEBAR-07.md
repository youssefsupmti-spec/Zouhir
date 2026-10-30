# 05 — Intégration Shadcn/UI (Preset b5dLKX1LE · base-lyra) et Bloc sidebar-07

**Date** : 30 octobre 2026  
**Auteur** : Gemini (Antigravity)  
**Projet** : Zouhir (`OUAISS EXPRESS`)  
**Statut** : **LIVRÉ, VÉRIFIÉ ET DÉPLOYÉ EN PRODUCTION (DEV)**  
**Niveau de preuve** : `VERIFIED IN PRODUCTION`  

---

## 1. Contexte et Demande

Le projet nécessitait l'intégration officielle du design system **Shadcn/UI** avec le preset personnalisé ainsi que l'installation du bloc de navigation **sidebar-07** :
1. **Initialisation Shadcn/UI** :
   ```bash
   npx shadcn@latest init --preset b5dLKX1LE --template next
   ```
2. **Ajout du composant Sidebar 07** :
   ```bash
   npx shadcn@latest add sidebar-07
   ```
3. **Résolution du mode debug** sur `dev.ouaissexpress.com` (`APP_DEBUG=true`, `APP_ENV=local`) pour permettre aux développeurs d'accéder au détail complet des erreurs et de copier les traces en cas d'anomalie.
4. **Intégration du composant dans l'application** :
   - Mise à disposition de la sidebar pliable (`collapsible="icon"`) avec tooltips.
   - Switcher d'agences/hubs (Casablanca, Rabat, Tanger).
   - Arborescence logistique marocaine (Expéditions, Suivi National, Relevés & CRBT, Paramètres).
   - Menu profil utilisateur (`NavUser`) synchronisé avec Inertia (`auth.user`) et action de déconnexion sécurisée (`router.post('/logout')`).
   - Header avec Breadcrumbs dynamiques, `SidebarTrigger`, statut réseau opérationnel en temps réel.

---

## 2. Réalisations Techniques

### A. Initialisation Shadcn/UI (Preset b5dLKX1LE)
- Résolution du preset `b5dLKX1LE` par le registry Shadcn :
  - Style : `base-lyra`
  - Base Color : `neutral`
  - CSS Variables : `true`
  - Primitives : `@base-ui/react` v1.9.0
  - Utilitaire : `cn` v0.5.0
  - Tailwind CSS : v4
- Fichier généré : `components.json`

### B. Installation du bloc sidebar-07
- Composants de structure installés :
  - `resources/js/components/app-sidebar.tsx`
  - `resources/js/components/nav-main.tsx`
  - `resources/js/components/nav-projects.tsx`
  - `resources/js/components/nav-user.tsx`
  - `resources/js/components/team-switcher.tsx`
- Composants UI atomiques :
  - `resources/js/components/ui/sidebar.tsx`
  - `resources/js/components/ui/breadcrumb.tsx`
  - `resources/js/components/ui/collapsible.tsx`
  - `resources/js/components/ui/dropdown-menu.tsx`
  - `resources/js/components/ui/avatar.tsx`
  - `resources/js/components/ui/sheet.tsx`
  - `resources/js/components/ui/tooltip.tsx`
  - `resources/js/components/ui/skeleton.tsx`
  - `resources/js/components/ui/separator.tsx`
  - `resources/js/components/ui/button.tsx`
  - `resources/js/components/ui/input.tsx`
  - `resources/js/hooks/use-mobile.ts`

### C. Résolution des Conflits de Casse (TypeScript TS1261 / TS1149)
- Les imports ont été unifiés de `@/Components` vers `@/components` (minuscules).
- Les boutons et inputs de formulaires applicatifs préexistants ont été renommés en `AppButton.jsx` et `AppInput.jsx` pour cohabiter sans ambiguïté avec les composants atomiques Shadcn `button.tsx` et `input.tsx`.
- Vérification TypeScript `npx tsc --noEmit` : **0 erreur**.

### D. Layout Authentifié Inertia (`AuthenticatedLayout.jsx`)
- Le layout intègre désormais `<SidebarProvider>`, `<AppSidebar>`, `<SidebarInset>`, `<SidebarTrigger>` et `<Breadcrumb>`.
- Mode responsive complet : drawer mobile automatique via `use-mobile` et `Sheet`.

---

## 3. Matrice de Vérification

| Vérification | Commande / URL | Résultat | Statut |
| :--- | :--- | :--- | :--- |
| **Compilation TypeScript** | `npx tsc --noEmit` | `0 errors` | ✅ CONFORME |
| **Build Frontend Vite** | `npm run build` | `app-BRxYNY_E.css` (142 kB), `app-BSlZY_m-.js` (690 kB) en 5.97s | ✅ CONFORME |
| **Tests PHPUnit** | `php artisan test` | 15 tests, 43 assertions | ✅ 100% VERTS |
| **Vitrine Live** | `https://dev.ouaissexpress.com/` | HTTP 200 OK | ✅ CONFORME |
| **Page Login** | `https://dev.ouaissexpress.com/login` | HTTP 200 OK | ✅ CONFORME |
| **Assets Shadcn/UI** | `https://dev.ouaissexpress.com/build/assets/app-BSlZY_m-.js` | HTTP 200 OK | ✅ CONFORME |
| **Redirection Guest** | `https://dev.ouaissexpress.com/dashboard` | HTTP 302 vers `/login` | ✅ CONFORME |
| **Mode Debug Serveur** | `.env` sur Hostinger | `APP_ENV=local`, `APP_DEBUG=true` | ✅ ACTIF |
| **Site Historique** | `https://ouaissexpress.com/` | HTTP 200 OK (PHP 7.4.33 isolé) | ✅ STRICTEMENT PRÉSERVÉ |

---

## 4. Conclusion & Prochaines Étapes
La fondation Shadcn/UI (base-lyra) et le composant `sidebar-07` sont pleinement opérationnels et déployés en production sur l'instance de développement. Le tableau de bord et les vues connectées bénéficient d'une interface responsive et moderne.
