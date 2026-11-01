"use client"

import * as React from "react"
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
import { ChevronsUpDownIcon, CheckIcon } from "lucide-react"

export function TeamSwitcher({
  teams,
}: {
  teams: {
    name: string
    logo: React.ReactNode
    plan: string
  }[]
}) {
  const { isMobile } = useSidebar()
  const [activeTeam, setActiveTeam] = React.useState(teams[0])

  if (!activeTeam) {
    return null
  }

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <SidebarMenuButton
                size="lg"
                className="w-full data-open:bg-sidebar-accent data-open:text-sidebar-accent-foreground hover:bg-sidebar-accent/80 transition-colors rounded-lg"
              />
            }
          >
            <div className="flex items-center justify-center w-full group-data-[collapsible=icon]:hidden overflow-hidden">
              <img 
                src="/images/logo-full-dark.png" 
                alt="OUAISS EXPRESS" 
                className="h-15 w-auto max-w-[215px] object-contain mx-auto block [.sidebar-light_&]:hidden"
              />
              <img 
                src="/images/logo.png" 
                alt="OUAISS EXPRESS" 
                className="h-15 w-auto max-w-[215px] object-contain mx-auto hidden [.sidebar-light_&]:block"
              />
            </div>
            <div className="hidden group-data-[collapsible=icon]:flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#004c68]/30 border border-white/10 overflow-hidden shadow-sm mx-auto">
              <img 
                src="/images/logo-icon-rounded.png" 
                alt="OUAISS EXPRESS" 
                className="size-full rounded-md object-contain"
              />
            </div>
            <ChevronsUpDownIcon className="ml-auto size-4 text-sidebar-foreground/60" />
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-64 rounded-xl border border-sidebar-border bg-sidebar text-sidebar-foreground shadow-2xl p-1.5"
            align="start"
            side={isMobile ? "bottom" : "right"}
            sideOffset={6}
          >
            <DropdownMenuGroup>
              <DropdownMenuLabel className="text-[10px] font-bold uppercase tracking-wider text-sidebar-foreground/60 px-2 py-1.5">
                Hubs & Points Relais OUAISS
              </DropdownMenuLabel>
              {teams.map((team) => {
                const isSelected = team.name === activeTeam.name
                return (
                  <DropdownMenuItem
                    key={team.name}
                    onClick={() => setActiveTeam(team)}
                    className={`gap-3 p-2 rounded-lg cursor-pointer transition-colors ${
                      isSelected 
                        ? 'bg-sidebar-accent text-sidebar-accent-foreground font-semibold' 
                        : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground'
                    }`}
                  >
                    <div className="flex size-7 items-center justify-center rounded-lg bg-[#004c68]/30 text-sidebar-foreground border border-white/10 overflow-hidden">
                      {team.logo}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="text-xs font-semibold truncate">{team.name}</div>
                      <div className="text-[10px] text-sidebar-foreground/60 truncate">{team.plan}</div>
                    </div>
                    {isSelected && (
                      <CheckIcon className="size-4 text-sidebar-primary ml-auto" />
                    )}
                  </DropdownMenuItem>
                )
              })}
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
