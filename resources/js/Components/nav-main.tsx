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
  SidebarMenuAction,
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  useSidebar,
} from "@/components/ui/sidebar"
import { ChevronRightIcon } from "lucide-react"

export interface NavSubItem {
  title: string
  url?: string
  badge?: string
  disabled?: boolean
}

export interface NavItem {
  title: string
  url?: string
  icon?: React.ReactNode
  badge?: string
  disabled?: boolean
  items?: NavSubItem[]
}

function getCleanPathname(rawUrl: string): string {
  if (!rawUrl) return ""
  return rawUrl.split("#")[0].split("?")[0].replace(/\/+$/, "") || "/"
}

function isPathActive(targetUrl: string | undefined, currentUrl: string): boolean {
  if (!targetUrl) return false
  const targetClean = getCleanPathname(targetUrl)
  const currentClean = getCleanPathname(currentUrl)

  if (targetClean === "/dashboard") {
    return currentClean === "/dashboard"
  }
  if (targetClean === "/") {
    return currentClean === "/"
  }

  return currentClean === targetClean || currentClean.startsWith(`${targetClean}/`)
}

function NavGroupCollapsible({
  item,
  currentUrl,
  onNavigate,
}: {
  item: NavItem
  currentUrl: string
  onNavigate?: () => void
}) {
  const isParentActive = isPathActive(item.url, currentUrl)
  const isAnyChildActive = Boolean(
    item.items?.some((sub) => sub.url && isPathActive(sub.url, currentUrl))
  )
  const shouldBeOpen = isParentActive || isAnyChildActive

  // Controlled open state for the collapsible
  const [open, setOpen] = React.useState<boolean>(shouldBeOpen)
  const prevUrlRef = React.useRef<string>(currentUrl)

  React.useEffect(() => {
    // If the route changes to one inside this section, expand automatically
    if (prevUrlRef.current !== currentUrl) {
      prevUrlRef.current = currentUrl
      if (shouldBeOpen) {
        setOpen(true)
      }
    }
  }, [currentUrl, shouldBeOpen])

  return (
    <Collapsible
      open={open}
      onOpenChange={setOpen}
      className="group/collapsible"
      render={<SidebarMenuItem />}
    >
      {item.url ? (
        // Case A: Group has its own page URL
        // Title & icon navigate directly via Inertia Link
        // Chevron on the right is an independent action that toggles the accordion
        <>
          <SidebarMenuButton
            isActive={isParentActive}
            tooltip={item.title}
            render={<Link href={item.url} onClick={onNavigate} />}
          >
            {item.icon}
            <span>{item.title}</span>
            {item.badge && <SidebarMenuBadge>{item.badge}</SidebarMenuBadge>}
          </SidebarMenuButton>
          <CollapsibleTrigger
            render={
              <SidebarMenuAction
                showOnHover={false}
                className="cursor-pointer transition-transform duration-200 group-data-open/collapsible:rotate-90"
              />
            }
          >
            <ChevronRightIcon className="size-3.5" />
            <span className="sr-only">Ouvrir/Fermer {item.title}</span>
          </CollapsibleTrigger>
        </>
      ) : (
        // Case B: Group does NOT have its own page URL (pure accordion category)
        // Clicking anywhere on the button toggles the accordion
        <CollapsibleTrigger
          render={
            <SidebarMenuButton
              isActive={isAnyChildActive}
              tooltip={item.title}
              className="cursor-pointer"
            />
          }
        >
          {item.icon}
          <span>{item.title}</span>
          {item.badge && <SidebarMenuBadge>{item.badge}</SidebarMenuBadge>}
          <ChevronRightIcon className="ml-auto size-3.5 transition-transform duration-200 group-data-open/collapsible:rotate-90" />
        </CollapsibleTrigger>
      )}

      <CollapsibleContent>
        <SidebarMenuSub>
          {item.items?.map((subItem) => {
            const isSubActive = isPathActive(subItem.url, currentUrl)

            if (subItem.disabled || !subItem.url) {
              return (
                <SidebarMenuSubItem key={subItem.title}>
                  <div className="flex h-7 min-w-0 items-center justify-between px-2 text-xs text-sidebar-foreground/40 cursor-not-allowed select-none">
                    <span className="truncate">{subItem.title}</span>
                    {subItem.badge && (
                      <span className="ml-auto text-[9px] font-semibold px-1.5 py-0.5 rounded bg-sidebar-accent/60 text-sidebar-foreground/50 border border-sidebar-border/40">
                        {subItem.badge}
                      </span>
                    )}
                  </div>
                </SidebarMenuSubItem>
              )
            }

            return (
              <SidebarMenuSubItem key={subItem.title}>
                <SidebarMenuSubButton
                  isActive={isSubActive}
                  render={<Link href={subItem.url} onClick={onNavigate} />}
                >
                  <span>{subItem.title}</span>
                  {subItem.badge && (
                    <span className="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-sidebar-primary/20 text-sidebar-primary">
                      {subItem.badge}
                    </span>
                  )}
                </SidebarMenuSubButton>
              </SidebarMenuSubItem>
            )
          })}
        </SidebarMenuSub>
      </CollapsibleContent>
    </Collapsible>
  )
}

function NavSingleItem({
  item,
  currentUrl,
  onNavigate,
}: {
  item: NavItem
  currentUrl: string
  onNavigate?: () => void
}) {
  const isItemActive = isPathActive(item.url, currentUrl)

  if (item.disabled || !item.url) {
    return (
      <SidebarMenuItem key={item.title}>
        <SidebarMenuButton
          disabled
          tooltip={item.title}
          className="cursor-not-allowed opacity-50"
        >
          {item.icon}
          <span>{item.title}</span>
          {item.badge && <SidebarMenuBadge>{item.badge}</SidebarMenuBadge>}
        </SidebarMenuButton>
      </SidebarMenuItem>
    )
  }

  return (
    <SidebarMenuItem key={item.title}>
      <SidebarMenuButton
        isActive={isItemActive}
        tooltip={item.title}
        render={<Link href={item.url} onClick={onNavigate} />}
      >
        {item.icon}
        <span>{item.title}</span>
        {item.badge && <SidebarMenuBadge>{item.badge}</SidebarMenuBadge>}
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}

export function NavMain({ items }: { items: NavItem[] }) {
  const { url = "" } = usePage()
  const { isMobile, setOpenMobile } = useSidebar()

  const handleNavigate = () => {
    // Automatically close the mobile sheet drawer after selecting an item
    if (isMobile) {
      setOpenMobile(false)
    }
  }

  return (
    <SidebarGroup>
      <SidebarGroupLabel className="text-[10px] font-bold uppercase tracking-wider text-sidebar-foreground/60 px-2.5 mb-1.5">
        Menu Principal
      </SidebarGroupLabel>
      <SidebarMenu className="gap-1">
        {items.map((item) => {
          const hasChildren = Boolean(item.items && item.items.length > 0)
          if (hasChildren) {
            return (
              <NavGroupCollapsible
                key={item.title}
                item={item}
                currentUrl={url}
                onNavigate={handleNavigate}
              />
            )
          }
          return (
            <NavSingleItem
              key={item.title}
              item={item}
              currentUrl={url}
              onNavigate={handleNavigate}
            />
          )
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}
