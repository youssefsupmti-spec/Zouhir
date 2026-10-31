"use client"

import * as React from "react"
import { usePage } from "@inertiajs/react"

import { NavMain } from "@/components/nav-main"
import { NavProjects } from "@/components/nav-projects"
import { NavUser } from "@/components/nav-user"
import { TeamSwitcher } from "@/components/team-switcher"
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
  Settings2Icon, 
  GlobeIcon, 
  CalculatorIcon, 
  PhoneCallIcon,
  Building2Icon,
  UsersIcon,
  MapPinIcon,
  LandmarkIcon,
  StoreIcon
} from "lucide-react"

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { auth = {} } = usePage().props as any
  const user = auth?.user || {
    name: "Client OUAISS",
    email: "contact@ouaissexpress.com",
    avatar: "/favicon.png",
  }

  const teams = [
    {
      name: "OUAISS EXPRESS",
      logo: <TruckIcon className="size-4 text-white" />,
      plan: "Hub National Casablanca",
    },
    {
      name: "Agence Rabat",
      logo: <Building2Icon className="size-4 text-white" />,
      plan: "Hub Régional Centre",
    },
    {
      name: "Agence Tanger",
      logo: <Building2Icon className="size-4 text-white" />,
      plan: "Hub Nord & Port",
    },
  ]

  const navMain = [
    {
      title: "Tableau de bord",
      url: "/dashboard",
      icon: <LayoutDashboardIcon className="size-4" />,
      isActive: true,
      items: [
        {
          title: "Vue d'ensemble",
          url: "/dashboard",
        },
        {
          title: "Performances & Taux",
          url: "/dashboard",
        },
      ],
    },
    {
      title: "Référentiels Métier",
      url: "/businesses",
      icon: <StoreIcon className="size-4" />,
      badge: "5",
      isActive: true,
      items: [
        {
          title: "Enseignes & Boutiques",
          url: "/businesses",
        },
        {
          title: "Clients Marchands",
          url: "/clients",
        },
        {
          title: "Livreurs (DLM)",
          url: "/livreurs",
        },
        {
          title: "Villes & Zones",
          url: "/villes",
        },
        {
          title: "Banques & RIBs",
          url: "/banks",
        },
      ],
    },
    {
      title: "Expéditions & Colis",
      url: "/colis",
      icon: <PackageIcon className="size-4" />,
      badge: "Colis",
      items: [
        {
          title: "Toutes les expéditions",
          url: "/colis",
        },
        {
          title: "Nouveau Colis Express",
          url: "/colis",
        },
      ],
    },
    {
      title: "Suivi National",
      url: "/dashboard",
      icon: <TruckIcon className="size-4" />,
      badge: "14",
      items: [
        {
          title: "Recherche par Code",
          url: "/dashboard",
        },
        {
          title: "Colis en cours de livraison",
          url: "/dashboard",
        },
        {
          title: "Retours & Réclamations",
          url: "/dashboard",
        },
      ],
    },
    {
      title: "Finances & CRBT",
      url: "/banks",
      icon: <WalletIcon className="size-4" />,
      badge: "MAD",
      items: [
        {
          title: "Coordonnées Bancaires",
          url: "/banks",
        },
        {
          title: "Relevés de paiement (CRBT)",
          url: "/dashboard",
        },
        {
          title: "Historique des virements",
          url: "/dashboard",
        },
      ],
    },
  ]

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
      <SidebarHeader className="border-b border-sidebar-border p-3">
        <TeamSwitcher teams={teams} />
      </SidebarHeader>
      <SidebarContent className="px-2 py-3">
        <NavMain items={navMain} />
        <NavProjects projects={quickLinks} />
      </SidebarContent>
      <SidebarFooter className="border-t border-sidebar-border p-3">
        <NavUser user={user} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
