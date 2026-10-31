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
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  useSidebar,
} from "@/components/ui/sidebar"
import { cn } from "cn"
import { ChevronRightIcon } from "lucide-react"

/**
 * Badge de navigation.
 * - "count" : compteur numérique RÉEL fourni par le Backend (jamais fictif).
 * - "soon"  : fonctionnalité pas encore disponible (élément désactivé).
 */
export type NavBadge = { kind: "count"; value: number } | { kind: "soon" }

export interface NavSubItem {
  title: string
  url?: string
  badge?: NavBadge
  disabled?: boolean
}

export interface NavItem {
  title: string
  url?: string
  icon?: React.ReactNode
  badge?: NavBadge
  disabled?: boolean
  items?: NavSubItem[]
}

/* -------------------------------------------------------------------------- */
/* Styles partagés (Base Lyra + Design System OUAISS EXPRESS, radius 0.375rem) */
/* -------------------------------------------------------------------------- */

// Ligne principale : hauteur 32px, icône 16px, état actif marqué par une barre orange
// en box-shadow inset (aucun décalage de layout, fonctionne aussi en mode icône 48px).
const MENU_BUTTON_CLASS =
  "rounded-lg text-sidebar-foreground/85 hover:text-sidebar-foreground data-active:bg-sidebar-accent data-active:font-semibold data-active:text-white data-active:shadow-[inset_2px_0_0_var(--sidebar-primary)]"

const SUB_BUTTON_CLASS =
  "rounded-lg text-sidebar-foreground/70 hover:text-sidebar-foreground data-active:font-semibold data-active:text-sidebar-primary"

const DISABLED_ROW_CLASS =
  "flex h-7 min-w-0 items-center gap-2 rounded-lg px-2 text-xs text-sidebar-foreground/45 cursor-not-allowed select-none"

function getCleanPathname(rawUrl: string): string {
  if (!rawUrl) return ""
  return rawUrl.split("#")[0].split("?")[0].replace(/\/+$/, "") || "/"
}

function isPathActive(targetUrl: string | undefined, currentUrl: string): boolean {
  if (!targetUrl) return false
  const targetClean = getCleanPathname(targetUrl)
  const currentClean = getCleanPathname(currentUrl)

  if (targetClean === "/dashboard" || targetClean === "/") {
    return currentClean === targetClean
  }

  return currentClean === targetClean || currentClean.startsWith(`${targetClean}/`)
}

/** Un élément est navigable s'il possède une URL et n'est pas désactivé. */
function isNavigable(entry: { url?: string; disabled?: boolean }): boolean {
  return Boolean(entry.url) && !entry.disabled
}

/**
 * Badge inline léger, placé dans le flux flexbox (jamais en position absolue)
 * afin de ne jamais entrer en collision avec le chevron SidebarMenuAction.
 * Masqué automatiquement en mode réduit (48px).
 */
function NavInlineBadge({ badge, className }: { badge?: NavBadge; className?: string }) {
  if (!badge) return null

  if (badge.kind === "count") {
    if (!Number.isFinite(badge.value) || badge.value <= 0) return null
    return (
      <span
        data-slot="nav-inline-badge"
        className={cn(
          "ml-auto inline-flex h-4.5 min-w-4.5 shrink-0 items-center justify-center rounded-md bg-sidebar-primary px-1 text-[10px] leading-none font-bold text-sidebar-primary-foreground tabular-nums group-data-[collapsible=icon]:hidden",
          className
        )}
      >
        {badge.value > 99 ? "99+" : badge.value}
      </span>
    )
  }

  return (
    <span
      data-slot="nav-inline-badge"
      className={cn(
        "ml-auto inline-flex h-4 shrink-0 items-center rounded-md border border-sidebar-border px-1.5 text-[9px] leading-none font-semibold tracking-wide text-sidebar-foreground/60 uppercase group-data-[collapsible=icon]:hidden",
        className
      )}
    >
      Bientôt
    </span>
  )
}

function NavTitle({ children }: { children: React.ReactNode }) {
  return <span className="min-w-0 flex-1 truncate">{children}</span>
}

function NavGroupCollapsible({
  item,
  currentUrl,
  onNavigate,
}: {
  item: NavItem
  currentUrl: string
  onNavigate: () => void
}) {
  const isParentActive = isPathActive(item.url, currentUrl)
  const isAnyChildActive = Boolean(
    item.items?.some((sub) => isNavigable(sub) && isPathActive(sub.url, currentUrl))
  )
  const shouldBeOpen = isParentActive || isAnyChildActive

  // État contrôlé : initialisé selon la route, puis ré-ouvert automatiquement
  // lorsque la navigation entre dans cette rubrique (sans forcer la fermeture).
  const [open, setOpen] = React.useState<boolean>(shouldBeOpen)
  const [syncedUrl, setSyncedUrl] = React.useState<string>(currentUrl)
  if (syncedUrl !== currentUrl) {
    setSyncedUrl(currentUrl)
    if (shouldBeOpen && !open) setOpen(true)
  }

  return (
    <Collapsible
      open={open}
      onOpenChange={setOpen}
      className="group/collapsible"
      render={<SidebarMenuItem />}
    >
      {item.url ? (
        // Cas A : la rubrique possède sa propre page.
        // Titre + icône = Link Inertia ; chevron = action indépendante (accordéon).
        <>
          <SidebarMenuButton
            isActive={isParentActive}
            tooltip={item.title}
            className={MENU_BUTTON_CLASS}
            render={<Link href={item.url} onClick={onNavigate} />}
          >
            {item.icon}
            <NavTitle>{item.title}</NavTitle>
            <NavInlineBadge badge={item.badge} />
          </SidebarMenuButton>
          <CollapsibleTrigger
            render={
              <SidebarMenuAction
                showOnHover={false}
                aria-label={`Afficher ou masquer le sous-menu ${item.title}`}
                className="cursor-pointer rounded-md text-sidebar-foreground/60 transition-transform duration-200 group-data-open/collapsible:rotate-90"
              />
            }
          >
            <ChevronRightIcon />
          </CollapsibleTrigger>
        </>
      ) : (
        // Cas B : rubrique « dossier » sans page : tout le bouton bascule l'accordéon.
        <CollapsibleTrigger
          render={
            <SidebarMenuButton
              isActive={isAnyChildActive}
              tooltip={item.title}
              className={cn(MENU_BUTTON_CLASS, "cursor-pointer")}
            />
          }
        >
          {item.icon}
          <NavTitle>{item.title}</NavTitle>
          <NavInlineBadge badge={item.badge} className="ml-0" />
          <ChevronRightIcon className="ml-auto text-sidebar-foreground/60 transition-transform duration-200 group-data-open/collapsible:rotate-90" />
        </CollapsibleTrigger>
      )}

      <CollapsibleContent>
        <SidebarMenuSub className="mr-0 gap-0.5 pr-0">
          {item.items?.map((subItem) => (
            <SidebarMenuSubItem key={subItem.title}>
              {isNavigable(subItem) ? (
                <SidebarMenuSubButton
                  isActive={isPathActive(subItem.url, currentUrl)}
                  className={SUB_BUTTON_CLASS}
                  render={<Link href={subItem.url!} onClick={onNavigate} />}
                >
                  <NavTitle>{subItem.title}</NavTitle>
                  <NavInlineBadge badge={subItem.badge} />
                </SidebarMenuSubButton>
              ) : (
                <span aria-disabled="true" className={DISABLED_ROW_CLASS}>
                  <NavTitle>{subItem.title}</NavTitle>
                  <NavInlineBadge badge={subItem.badge ?? { kind: "soon" }} />
                  <span className="sr-only">(bientôt disponible)</span>
                </span>
              )}
            </SidebarMenuSubItem>
          ))}
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
  onNavigate: () => void
}) {
  if (!isNavigable(item)) {
    // Élément désactivé : aucun lien, aucun accordéon, état visuel et ARIA explicites.
    return (
      <SidebarMenuItem>
        <SidebarMenuButton
          disabled
          aria-disabled="true"
          tooltip={`${item.title} (bientôt)`}
          className={cn(MENU_BUTTON_CLASS, "cursor-not-allowed text-sidebar-foreground/45 disabled:opacity-100")}
        >
          {item.icon}
          <NavTitle>{item.title}</NavTitle>
          <NavInlineBadge badge={item.badge ?? { kind: "soon" }} />
          <span className="sr-only">(bientôt disponible)</span>
        </SidebarMenuButton>
      </SidebarMenuItem>
    )
  }

  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        isActive={isPathActive(item.url, currentUrl)}
        tooltip={item.title}
        className={MENU_BUTTON_CLASS}
        render={<Link href={item.url!} onClick={onNavigate} />}
      >
        {item.icon}
        <NavTitle>{item.title}</NavTitle>
        <NavInlineBadge badge={item.badge} />
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}

export function NavMain({ items }: { items: NavItem[] }) {
  const { url = "" } = usePage()
  const { isMobile, setOpenMobile } = useSidebar()

  const handleNavigate = React.useCallback(() => {
    // Ferme automatiquement le drawer mobile après sélection d'un lien.
    if (isMobile) setOpenMobile(false)
  }, [isMobile, setOpenMobile])

  return (
    <SidebarGroup>
      <SidebarGroupLabel className="mb-1 px-2 text-[10px] font-bold tracking-wider text-sidebar-foreground/60 uppercase">
        Menu Principal
      </SidebarGroupLabel>
      <SidebarMenu className="gap-0.5">
        {items.map((item) => {
          // Une rubrique dossier dont aucun enfant n'est navigable n'ouvre pas
          // d'accordéon « vide » : elle est rendue comme un élément désactivé.
          const hasNavigableChildren = Boolean(item.items?.some(isNavigable))
          if (item.items?.length && (hasNavigableChildren || isNavigable(item))) {
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
