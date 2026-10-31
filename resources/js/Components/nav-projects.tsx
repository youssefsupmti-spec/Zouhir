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

const BUTTON_CLASS =
  "rounded-lg text-sidebar-foreground/85 hover:text-sidebar-foreground"

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
      <SidebarGroupLabel className="mb-1 px-2 text-[10px] font-bold tracking-wider text-sidebar-foreground/60 uppercase">
        Raccourcis Express
      </SidebarGroupLabel>
      <SidebarMenu className="gap-0.5">
        {projects.map((item) => {
          const isExternal = item.url.startsWith("http")
          return (
            <SidebarMenuItem key={item.name}>
              {isExternal ? (
                <SidebarMenuButton
                  tooltip={item.name}
                  className={BUTTON_CLASS}
                  render={
                    <a
                      href={item.url}
                      target="_blank"
                      rel="noopener noreferrer"
                    />
                  }
                >
                  {item.icon}
                  <span className="min-w-0 flex-1 truncate">{item.name}</span>
                  <ExternalLinkIcon className="ml-auto size-3! text-sidebar-foreground/50" />
                  <span className="sr-only">(ouvre un nouvel onglet)</span>
                </SidebarMenuButton>
              ) : (
                <SidebarMenuButton
                  tooltip={item.name}
                  className={BUTTON_CLASS}
                  render={
                    <Link
                      href={item.url}
                      onClick={handleNavigate}
                    />
                  }
                >
                  {item.icon}
                  <span className="min-w-0 flex-1 truncate">{item.name}</span>
                </SidebarMenuButton>
              )}
            </SidebarMenuItem>
          )
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}
