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
                className="w-full data-open:bg-sidebar-accent data-open:text-sidebar-accent-foreground hover:bg-sidebar-accent/80 transition-colors rounded-xl p-2"
              />
            }
          >
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-sidebar-primary text-sidebar-primary-foreground font-bold shadow-md shadow-sidebar-primary/20">
              {activeTeam.logo}
            </div>
            <div className="grid flex-1 text-left text-sm leading-tight">
              <span className="truncate font-bold text-sidebar-foreground tracking-tight">
                {activeTeam.name}
              </span>
              <span className="truncate text-[11px] font-medium text-sidebar-foreground/70">
                {activeTeam.plan}
              </span>
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
                    <div className="flex size-7 items-center justify-center rounded-lg bg-sidebar-primary/15 text-sidebar-primary border border-sidebar-border/40">
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
