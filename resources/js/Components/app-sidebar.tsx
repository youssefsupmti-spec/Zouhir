"use client"

import * as React from "react"
import { usePage } from "@inertiajs/react"

import { NavMain, type NavItem } from "@/components/nav-main"
import { NavProjects } from "@/components/nav-projects"
import { NavUser } from "@/components/nav-user"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from "@/components/ui/sidebar"
import { 
  TruckIcon, 
  PackageIcon, 
  LayoutDashboardIcon, 
  WalletIcon, 
  GlobeIcon, 
  CalculatorIcon, 
  PhoneCallIcon,
  StoreIcon,
  ShieldCheckIcon,
  KeyRoundIcon,
  TagsIcon,
} from "lucide-react"

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const pageProps = usePage().props as any
  const auth = pageProps?.auth || {}
  const user = auth?.user || {
    name: "Client OUAISS",
    email: "contact@ouaissexpress.com",
    avatar: "/images/logo-icon-rounded.png",
  }

  // Badges : uniquement des compteurs réels fournis par le Backend ({ kind: "count" })
  // ou l'état « Bientôt » ({ kind: "soon" }). Aucun compteur fictif.
  const SOON = { kind: "soon" } as const

  const navAll: NavItem[] = [
    {
      title: "Tableau de bord",
      url: "/dashboard",
      icon: <LayoutDashboardIcon />,
    },
    {
      title: "Expéditions & Colis",
      icon: <PackageIcon />,
      items: [
        { title: "Toutes les expéditions", url: "/colis" },
        { title: "Historique & Suivi AWB", badge: SOON, disabled: true },
      ],
    },
    {
      title: "Référentiels Métier",
      icon: <StoreIcon />,
      items: [
        { title: "Enseignes & Boutiques", url: "/businesses" },
        { title: "Clients Marchands", url: "/clients" },
        { title: "Livreurs (DLM)", url: "/livreurs" },
        { title: "Villes & Zones", url: "/villes" },
      ],
    },
    {
      // Aucun sous-élément navigable : nav-main la rend comme rubrique désactivée
      // « Bientôt » (pas d'accordéon). Les sous-éléments restent documentés ici.
      title: "Suivi National",
      icon: <TruckIcon />,
      badge: SOON,
      items: [
        { title: "Recherche par Code", badge: SOON, disabled: true },
        { title: "Colis en cours de livraison", badge: SOON, disabled: true },
        { title: "Retours & Réclamations", badge: SOON, disabled: true },
      ],
    },
    {
      title: "Finances & CRBT",
      icon: <WalletIcon />,
      items: [
        // Entrée unique pour /banks (doublon supprimé de « Référentiels Métier »).
        { title: "Banques & RIBs", url: "/banks" },
        { title: "Relevés de paiement (CRBT)", badge: SOON, disabled: true },
        { title: "Historique des virements", badge: SOON, disabled: true },
      ],
    },
    {
      // Domaine Tarifs (Sprint 29) : le parent est un déclencheur d'accordéon, les enfants sont les liens.
      title: "Tarifs",
      icon: <TagsIcon />,
      items: [
        { title: "Tarifs de livraison", url: "/tarifs-standard" },
        { title: "Tarifs clients", url: "/tarifs-clients" },
        { title: "Tarifs livreurs", url: "/tarifs-livreurs" },
      ],
    },
  ]

  // Filtrage par capacités serveur (auth.can, calculées par les Policies Laravel).
  // Aucun nom de rôle côté React. Ce masquage est un confort : la sécurité reste serveur.
  const can: Record<string, boolean> = auth?.can || {}
  const ABILITY_BY_URL: Record<string, string> = {
    "/colis": "colis",
    "/businesses": "businesses",
    "/clients": "users",
    "/livreurs": "users",
    "/villes": "villes",
    "/banks": "banks",
    "/roles": "roles",
    "/permissions": "permissions",
    "/tarifs-clients": "tarifsClients",
    "/tarifs-standard": "tarifsStandard",
    "/tarifs-livreurs": "tarifsLivreurs",
  }
  const isAllowed = (url?: string) => !url || !ABILITY_BY_URL[url] || can[ABILITY_BY_URL[url]] === true
  const hasLink = (i: { url?: string; disabled?: boolean }) => Boolean(i.url) && !i.disabled

  const navMain: NavItem[] = navAll.flatMap((item) => {
    if (!item.items) return isAllowed(item.url) ? [item] : []
    const items = item.items.filter((sub) => isAllowed(sub.url))
    // Une rubrique qui avait des liens mais n'en conserve aucun est masquée.
    if (item.items.some(hasLink) && !items.some(hasLink)) return []
    return [{ ...item, items }]
  })

  // Groupe indépendant « Administration & Sécurité » (accès vérifié côté Laravel par RolePolicy / PermissionPolicy).
  const adminNav: NavItem[] = [
    { title: "Rôles", url: "/roles", icon: <ShieldCheckIcon /> },
    { title: "Permissions", url: "/permissions", icon: <KeyRoundIcon /> },
  ].filter((item) => isAllowed(item.url))

  const quickLinks = [
    {
      name: "Vitrine Publique",
      url: "/",
      icon: <GlobeIcon className="size-4" />,
    },
    {
      name: "Calculateur Tarifs",
      url: "/#tarifs",
      icon: <CalculatorIcon className="size-4" />,
    },
    {
      name: "Support WhatsApp",
      url: "https://wa.me/212600000000",
      icon: <PhoneCallIcon className="size-4" />,
    },
  ]

  return (
    <Sidebar collapsible="icon" className="border-r border-sidebar-border" {...props}>
            <SidebarHeader className="h-20 justify-center items-center border-b border-sidebar-border px-3 py-2">
        {/* Mode déplié : Logo officiel centré en h-15 (image seule, sans bouton ni lien) */}
        <div className="flex items-center justify-center w-full group-data-[collapsible=icon]:hidden">
          {/* Version pour fond sombre (par défaut sur sidebar OUAISS EXPRESS) */}
          <img 
            src="/images/logo-full-dark.png" 
            alt="OUAISS EXPRESS" 
            className="h-15 w-auto max-w-[215px] object-contain mx-auto block [.sidebar-light_&]:hidden"
          />
          {/* Version pour fond clair (si la sidebar passe en mode clair) */}
          <img 
            src="/images/logo.png" 
            alt="OUAISS EXPRESS" 
            className="h-15 w-auto max-w-[215px] object-contain mx-auto hidden [.sidebar-light_&]:block"
          />
        </div>

        {/* Mode replié (collapsible="icon") : Emblème squircle officiel (image seule) */}
        <div className="hidden group-data-[collapsible=icon]:flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#004c68]/30 border border-white/10 overflow-hidden shadow-sm mx-auto">
          <img 
            src="/images/logo-icon-rounded.png" 
            alt="OUAISS EXPRESS" 
            className="size-full rounded-md object-contain"
          />
        </div>
      </SidebarHeader>
      <SidebarContent className="py-2">
        <NavMain items={navMain} />
        {adminNav.length > 0 && <NavMain label="Administration & Sécurité" items={adminNav} />}
        <NavProjects projects={quickLinks} />
      </SidebarContent>
      <SidebarFooter className="border-t border-sidebar-border">
        <NavUser user={user} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
