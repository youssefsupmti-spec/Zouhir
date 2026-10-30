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
import { ChevronsUpDownIcon, SparklesIcon, BadgeCheckIcon, CreditCardIcon, BellIcon, LogOutIcon, UserIcon } from "lucide-react"

export function NavUser({
  user,
}: {
  user: {
    name: string
    email: string
    avatar?: string
    role?: string
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
              <SidebarMenuButton size="lg" className="aria-expanded:bg-muted hover:bg-slate-800/50 transition-colors" />
            }
          >
            <Avatar className="h-8 w-8 rounded-lg bg-[#004c68] text-white font-bold text-xs flex items-center justify-center border border-slate-700">
              {user.avatar ? (
                <AvatarImage src={user.avatar} alt={user.name} />
              ) : null}
              <AvatarFallback className="bg-[#004c68] text-white font-bold">{initials}</AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
              <span className="truncate font-semibold text-white">{user.name || 'Client OUAISS'}</span>
              <span className="truncate text-xs text-slate-400">{user.email || 'client@ouaissexpress.com'}</span>
            </div>
            <ChevronsUpDownIcon className="ml-auto size-4 text-slate-400" />
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-56 rounded-xl border border-slate-800 bg-[#0a1a24] text-white shadow-xl"
            side={isMobile ? "bottom" : "right"}
            align="end"
            sideOffset={6}
          >
            <DropdownMenuGroup>
              <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-3 px-3 py-2 text-left text-sm border-b border-slate-800/80">
                  <Avatar className="h-8 w-8 rounded-lg bg-[#004c68] text-white font-bold text-xs flex items-center justify-center">
                    <AvatarFallback className="bg-[#004c68] text-white font-bold">{initials}</AvatarFallback>
                  </Avatar>
                  <div className="grid flex-1 text-left text-sm leading-tight">
                    <span className="truncate font-bold text-white">{user.name || 'Client OUAISS'}</span>
                    <span className="truncate text-[11px] text-slate-400">{user.email}</span>
                  </div>
                </div>
              </DropdownMenuLabel>
            </DropdownMenuGroup>
            <DropdownMenuSeparator className="bg-slate-800" />
            <DropdownMenuGroup>
              <DropdownMenuItem className="gap-2 cursor-pointer text-slate-300 hover:text-white hover:bg-slate-800/80 focus:bg-slate-800">
                <UserIcon className="size-4 text-[#ff8806]" />
                Mon Profil Marchand
              </DropdownMenuItem>
              <DropdownMenuItem className="gap-2 cursor-pointer text-slate-300 hover:text-white hover:bg-slate-800/80 focus:bg-slate-800">
                <CreditCardIcon className="size-4 text-emerald-400" />
                Mes Relevés & Factures
              </DropdownMenuItem>
              <DropdownMenuItem className="gap-2 cursor-pointer text-slate-300 hover:text-white hover:bg-slate-800/80 focus:bg-slate-800">
                <BellIcon className="size-4 text-amber-400" />
                Notifications
              </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator className="bg-slate-800" />
            <DropdownMenuItem 
              onClick={handleLogout}
              className="gap-2 cursor-pointer text-red-400 hover:text-red-300 hover:bg-red-500/10 focus:bg-red-500/10 font-medium"
            >
              <LogOutIcon className="size-4" />
              Se déconnecter
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
