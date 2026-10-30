import * as React from "react"
import { Link } from "@inertiajs/react"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
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
  return (
    <SidebarGroup>
      <SidebarGroupLabel className="text-[10px] font-bold uppercase tracking-wider text-sidebar-foreground/60 px-2.5 mb-1.5">
        Menu Principal
      </SidebarGroupLabel>
      <SidebarMenu className="gap-1">
        {items.map((item) => {
          const hasChildren = item.items && item.items.length > 0

          if (!hasChildren) {
            return (
              <SidebarMenuItem key={item.title}>
                <SidebarMenuButton 
                  tooltip={item.title}
                  className={`
                    w-full flex items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition-all
                    ${item.isActive 
                      ? 'bg-sidebar-accent text-sidebar-primary font-bold shadow-sm' 
                      : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/70 hover:text-sidebar-foreground'}
                  `}
                >
                  <Link href={item.url} className="flex items-center gap-2.5 flex-1 min-w-0">
                    <span className="text-sidebar-primary">{item.icon}</span>
                    <span className="truncate">{item.title}</span>
                  </Link>
                  {item.badge && (
                    <span className="ml-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-sidebar-primary text-sidebar-primary-foreground">
                      {item.badge}
                    </span>
                  )}
                </SidebarMenuButton>
              </SidebarMenuItem>
            )
          }

          return (
            <Collapsible
              key={item.title}
              defaultOpen={item.isActive}
              className="group/collapsible"
              render={<SidebarMenuItem />}
            >
              <CollapsibleTrigger
                render={
                  <SidebarMenuButton 
                    tooltip={item.title}
                    className={`
                      w-full rounded-xl px-3 py-2 text-xs font-semibold transition-all
                      ${item.isActive 
                        ? 'bg-sidebar-accent/60 text-sidebar-foreground font-bold' 
                        : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/70 hover:text-sidebar-foreground'}
                    `}
                  />
                }
              >
                <div className="flex items-center gap-2.5 flex-1 min-w-0">
                  <span className="text-sidebar-primary">{item.icon}</span>
                  <span className="truncate">{item.title}</span>
                </div>
                {item.badge && (
                  <span className="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-sidebar-primary text-sidebar-primary-foreground mr-1">
                    {item.badge}
                  </span>
                )}
                <ChevronRightIcon className="ml-auto size-3.5 text-sidebar-foreground/50 transition-transform duration-200 group-data-open/collapsible:rotate-90" />
              </CollapsibleTrigger>
              <CollapsibleContent>
                <SidebarMenuSub className="border-l border-sidebar-border ml-5 pl-2 my-1 space-y-1">
                  {item.items?.map((subItem) => (
                    <SidebarMenuSubItem key={subItem.title}>
                      <SidebarMenuSubButton 
                        render={
                          <Link 
                            href={subItem.url} 
                            className="rounded-lg px-2.5 py-1.5 text-xs text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent/60 transition-colors block"
                          />
                        }
                      >
                        <span className="truncate">{subItem.title}</span>
                      </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                  ))}
                </SidebarMenuSub>
              </CollapsibleContent>
            </Collapsible>
          )
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}
