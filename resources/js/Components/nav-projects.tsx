import * as React from "react"
import { Link } from "@inertiajs/react"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
  useSidebar,
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
  const { isMobile, setOpenMobile } = useSidebar()

  const handleNavigate = () => {
    if (isMobile) {
      setOpenMobile(false)
    }
  }

  return (
    <SidebarGroup className="group-data-[collapsible=icon]:hidden mt-2">
      <SidebarGroupLabel className="text-[10px] font-bold uppercase tracking-wider text-sidebar-foreground/60 px-2.5 mb-1.5">
        Raccourcis Express
      </SidebarGroupLabel>
      <SidebarMenu className="gap-1">
        {projects.map((item) => {
          const isExternal = item.url.startsWith("http")
          return (
            <SidebarMenuItem key={item.name}>
              {isExternal ? (
                <SidebarMenuButton
                  tooltip={item.name}
                  render={
                    <a
                      href={item.url}
                      target="_blank"
                      rel="noopener noreferrer"
                    />
                  }
                >
                  {item.icon}
                  <span>{item.name}</span>
                  <ExternalLinkIcon className="ml-auto size-3 text-sidebar-foreground/40" />
                </SidebarMenuButton>
              ) : (
                <SidebarMenuButton
                  tooltip={item.name}
                  render={
                    <Link
                      href={item.url}
                      onClick={handleNavigate}
                    />
                  }
                >
                  {item.icon}
                  <span>{item.name}</span>
                </SidebarMenuButton>
              )}
            </SidebarMenuItem>
          )
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}
