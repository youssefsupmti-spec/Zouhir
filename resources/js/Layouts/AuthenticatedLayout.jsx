import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { AppSidebar } from '@/components/app-sidebar';
import ThemeToggle from '@/components/ThemeToggle';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from '@/components/ui/sidebar';
import Badge from '@/components/ui/Badge';
import { CheckCircle2, X } from 'lucide-react';

export default function AuthenticatedLayout({ children, title = "Tableau de bord" }) {
    const { flash = {} } = usePage().props;
    const [dismissFlash, setDismissFlash] = useState(false);

    return (
        <SidebarProvider>
            <AppSidebar />
            <SidebarInset className="bg-background min-h-screen flex flex-col font-['Inter'] transition-colors duration-200">
                {/* Header with trigger, separator, breadcrumb and live network status */}
                <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-border bg-card/90 backdrop-blur-md px-4 sm:px-6 sticky top-0 z-30 transition-all ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14">
                    <div className="flex items-center gap-2 sm:gap-3">
                        <SidebarTrigger className="-ml-1 text-foreground hover:bg-muted rounded-lg p-2 transition-colors" />
                        <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-auto bg-border" />
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem className="hidden sm:block">
                                    <BreadcrumbLink href="/dashboard" className="text-muted-foreground hover:text-foreground transition-colors font-medium text-xs">
                                        Espace Client
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator className="hidden sm:block text-muted-foreground/40" />
                                <BreadcrumbItem>
                                    <BreadcrumbPage className="font-bold text-foreground text-xs sm:text-sm">
                                        {title}
                                    </BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <div className="hidden md:flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span>Réseau Opérationnel</span>
                        </div>

                        <Badge variant="secondary" size="sm" className="hidden sm:inline-flex">
                            OUAISS EXPRESS
                        </Badge>

                        {/* Theme Toggle Sun / Moon */}
                        <ThemeToggle />
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto bg-background">
                    {flash.success && !dismissFlash && (
                        <div className="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl text-emerald-700 dark:text-emerald-300 text-xs font-semibold shadow-sm flex items-center justify-between animate-in fade-in slide-in-from-top-2 duration-300">
                            <div className="flex items-center gap-2.5">
                                <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{flash.success}</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setDismissFlash(true)}
                                className="p-1 rounded-lg hover:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 transition-colors"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                    )}
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}
