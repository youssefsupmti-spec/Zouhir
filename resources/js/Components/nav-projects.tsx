import * as React from "react"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { ExternalLinkIcon } from "lucide-react"

export function NavProjects({
  projects,
}: {
  projects: {
    name: string
    url: string
    icon?: React.ReactNode
  }[]
}) {
  return (
    <SidebarGroup className="group-data-[collapsible=icon]:hidden mt-2">
      <SidebarGroupLabel className="text-[10px] font-bold uppercase tracking-wider text-sidebar-foreground/60 px-2.5 mb-1.5">
        Raccourcis Express
      </SidebarGroupLabel>
      <SidebarMenu className="gap-1">
        {projects.map((item) => (
          <SidebarMenuItem key={item.name}>
            <SidebarMenuButton 
              render={
                <a 
                  href={item.url} 
                  target={item.url.startsWith('http') ? '_blank' : undefined}
                  rel={item.url.startsWith('http') ? 'noopener noreferrer' : undefined}
                  className="w-full flex items-center justify-between rounded-xl px-3 py-2 text-xs font-medium text-sidebar-foreground/75 hover:bg-sidebar-accent/70 hover:text-sidebar-foreground transition-all"
                />
              }
            >
              <div className="flex items-center gap-2.5">
                <span className="text-sidebar-primary/90">{item.icon}</span>
                <span className="truncate">{item.name}</span>
              </div>
              <ExternalLinkIcon className="size-3 text-sidebar-foreground/40" />
            </SidebarMenuButton>
          </SidebarMenuItem>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  )
}
