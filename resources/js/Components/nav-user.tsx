"use client"

import * as React from "react"
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"
import { router } from "@inertiajs/react"
import { 
  ChevronsUpDownIcon, 
  UserIcon, 
  CreditCardIcon, 
  BellIcon, 
  LogOutIcon,
  ShieldCheckIcon
} from "lucide-react"

export function NavUser({
  user,
}: {
  user: {
    name: string
    email: string
    avatar?: string
    role?: string
    store_name?: string
  }
}) {
  const { isMobile } = useSidebar()
  const initials = user?.name ? user.name.substring(0, 2).toUpperCase() : 'OE'

  const handleLogout = () => {
    router.post('/logout')
  }

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <SidebarMenuButton 
                size="lg" 
                className="w-full data-open:bg-sidebar-accent data-open:text-sidebar-accent-foreground hover:bg-sidebar-accent/80 transition-colors rounded-xl p-2" 
              />
            }
          >
            <Avatar className="h-9 w-9 rounded-xl bg-sidebar-primary text-sidebar-primary-foreground font-bold text-xs flex items-center justify-center border border-sidebar-border shadow-sm">
              {user.avatar ? (
                <AvatarImage src={user.avatar} alt={user.name} />
              ) : null}
              <AvatarFallback className="bg-sidebar-primary text-white font-bold">{initials}</AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
              <span className="truncate font-bold text-sidebar-foreground">
                {user.name || 'Client OUAISS'}
              </span>
              <span className="truncate text-[11px] text-sidebar-foreground/70">
                {user.email || 'client@ouaissexpress.com'}
              </span>
            </div>
            <ChevronsUpDownIcon className="ml-auto size-4 text-sidebar-foreground/60" />
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-60 rounded-xl border border-sidebar-border bg-sidebar text-sidebar-foreground shadow-2xl p-1.5"
            side={isMobile ? "bottom" : "right"}
            align="end"
            sideOffset={6}
          >
            <DropdownMenuGroup>
              <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-3 px-2.5 py-2 text-left text-sm border-b border-sidebar-border/80">
                  <Avatar className="h-9 w-9 rounded-xl bg-sidebar-primary text-sidebar-primary-foreground font-bold text-xs flex items-center justify-center">
                    <AvatarFallback className="bg-sidebar-primary text-white font-bold">{initials}</AvatarFallback>
                  </Avatar>
                  <div className="grid flex-1 text-left text-sm leading-tight">
                    <span className="truncate font-bold text-sidebar-foreground">
                      {user.name || 'Client OUAISS'}
                    </span>
                    <span className="truncate text-[11px] text-sidebar-foreground/70">{user.email}</span>
                  </div>
                </div>
              </DropdownMenuLabel>
            </DropdownMenuGroup>
            <DropdownMenuSeparator className="bg-sidebar-border" />
            <DropdownMenuGroup>
              <DropdownMenuItem className="gap-2.5 p-2 rounded-lg cursor-pointer text-sidebar-foreground/80 hover:text-sidebar-foreground hover:bg-sidebar-accent/80 transition-colors">
                <UserIcon className="size-4 text-sidebar-primary" />
                <span className="text-xs font-semibold">Mon Profil Boutique</span>
              </DropdownMenuItem>
              <DropdownMenuItem className="gap-2.5 p-2 rounded-lg cursor-pointer text-sidebar-foreground/80 hover:text-sidebar-foreground hover:bg-sidebar-accent/80 transition-colors">
                <CreditCardIcon className="size-4 text-emerald-400" />
                <span className="text-xs font-semibold">Relevés de Paiement</span>
              </DropdownMenuItem>
              <DropdownMenuItem className="gap-2.5 p-2 rounded-lg cursor-pointer text-sidebar-foreground/80 hover:text-sidebar-foreground hover:bg-sidebar-accent/80 transition-colors">
                <BellIcon className="size-4 text-amber-400" />
                <span className="text-xs font-semibold">Notifications</span>
              </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator className="bg-sidebar-border" />
            <DropdownMenuItem 
              onClick={handleLogout}
              className="gap-2.5 p-2 rounded-lg cursor-pointer text-red-400 hover:text-red-300 hover:bg-red-500/15 transition-colors font-semibold text-xs"
            >
              <LogOutIcon className="size-4" />
              <span>Se déconnecter</span>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
