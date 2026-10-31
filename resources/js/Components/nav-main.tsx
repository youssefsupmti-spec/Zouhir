import * as React from "react"
import { Link, usePage } from "@inertiajs/react"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubItem,
} from "@/components/ui/sidebar"
import { ChevronRightIcon } from "lucide-react"

export function NavMain({
  items,
}: {
  items: {
    title: string
    url: string
    icon?: React.ReactNode
    badge?: string
    isActive?: boolean
    items?: {
      title: string
      url: string
    }[]
  }[]
}) {
  const { url = "" } = usePage()

  const checkIsActive = (targetUrl: string) => {
    if (!targetUrl) return false
    if (targetUrl === "/dashboard") {
      return url === "/dashboard"
    }
    return url.startsWith(targetUrl)
  }

  return (
    <SidebarGroup>
      <SidebarGroupLabel className="text-[10px] font-bold uppercase tracking-wider text-sidebar-foreground/60 px-2.5 mb-1.5">
        Menu Principal
      </SidebarGroupLabel>
      <SidebarMenu className="gap-1">
        {items.map((item) => {
          const hasChildren = item.items && item.items.length > 0
          const isItemActive = checkIsActive(item.url)
          const isAnyChildActive = hasChildren && item.items?.some((sub) => checkIsActive(sub.url))
          const isSectionOpen = item.isActive || isItemActive || isAnyChildActive

          if (!hasChildren) {
            return (
              <SidebarMenuItem key={item.title}>
                <Link
                  href={item.url}
                  className={`
                    w-full flex items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition-all
                    ${isItemActive 
                      ? 'bg-sidebar-accent text-sidebar-primary font-bold shadow-xs' 
                      : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/70 hover:text-sidebar-foreground'}
                  `}
                >
                  <div className="flex items-center gap-2.5 flex-1 min-w-0">
                    <span className="text-sidebar-primary">{item.icon}</span>
                    <span className="truncate">{item.title}</span>
                  </div>
                  {item.badge && (
                    <span className="ml-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-sidebar-primary text-sidebar-primary-foreground">
                      {item.badge}
                    </span>
                  )}
                </Link>
              </SidebarMenuItem>
            )
          }

          return (
            <Collapsible
              key={item.title}
              defaultOpen={isSectionOpen}
              className="group/collapsible"
            >
              <SidebarMenuItem>
                <div className="flex items-center w-full gap-1">
                  {/* Lien de navigation direct vers la page principale de la rubrique */}
                  <Link
                    href={item.url}
                    className={`
                      flex-1 flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-semibold transition-all min-w-0
                      ${(isItemActive || isAnyChildActive)
                        ? 'bg-sidebar-accent/60 text-sidebar-foreground font-bold' 
                        : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/70 hover:text-sidebar-foreground'}
                    `}
                  >
                    <span className="text-sidebar-primary shrink-0">{item.icon}</span>
                    <span className="truncate">{item.title}</span>
                    {item.badge && (
                      <span className="ml-auto px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-sidebar-primary text-sidebar-primary-foreground">
                        {item.badge}
                      </span>
                    )}
                  </Link>

                  {/* Bouton flèche toggle accordéon sans interférence avec le lien de navigation */}
                  <CollapsibleTrigger
                    className="p-2 rounded-lg text-sidebar-foreground/50 hover:text-sidebar-foreground hover:bg-sidebar-accent/50 transition-colors cursor-pointer"
                    aria-label={`Ouvrir/Fermer ${item.title}`}
                  >
                    <ChevronRightIcon className="size-3.5 transition-transform duration-200 group-data-open/collapsible:rotate-90" />
                  </CollapsibleTrigger>
                </div>

                <CollapsibleContent>
                  <SidebarMenuSub className="border-l border-sidebar-border ml-5 pl-2 my-1 space-y-1">
                    {item.items?.map((subItem) => {
                      const isSubActive = checkIsActive(subItem.url)
                      return (
                        <SidebarMenuSubItem key={subItem.title}>
                          <Link
                            href={subItem.url}
                            className={`
                              rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors block
                              ${isSubActive
                                ? 'bg-sidebar-primary/10 text-sidebar-primary font-bold'
                                : 'text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent/60'}
                            `}
                          >
                            <span className="truncate">{subItem.title}</span>
                          </Link>
                        </SidebarMenuSubItem>
                      )
                    })}
                  </SidebarMenuSub>
                </CollapsibleContent>
              </SidebarMenuItem>
            </Collapsible>
          )
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}
