import React from 'react';
import { usePage } from '@inertiajs/react';
import { AppSidebar } from '@/components/app-sidebar';
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

export default function AuthenticatedLayout({ children, title = "Tableau de bord" }) {
    const { flash = {} } = usePage().props ;

    return (
        <SidebarProvider>
            <AppSidebar />
            <SidebarInset className="bg-slate-50 min-h-screen flex flex-col font-['Inter']">
                {/* Header with trigger, separator, breadcrumb and live network status */}
                <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-slate-200/80 bg-white/95 backdrop-blur px-4 sm:px-6 sticky top-0 z-30 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14">
                    <div className="flex items-center gap-2 sm:gap-3">
                        <SidebarTrigger className="-ml-1 text-slate-700 hover:text-[#004c68]" />
                        <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-auto bg-slate-200" />
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem className="hidden sm:block">
                                    <BreadcrumbLink href="/dashboard" className="text-slate-500 hover:text-[#004c68]">
                                        Espace Client
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator className="hidden sm:block text-slate-300" />
                                <BreadcrumbItem>
                                    <BreadcrumbPage className="font-bold text-[#004c68]">
                                        {title}
                                    </BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <div className="hidden md:flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/60 text-xs font-semibold text-emerald-700">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span>Réseau Opérationnel</span>
                        </div>
                        <Badge variant="secondary" size="sm">
                            OUAISS EXPRESS
                        </Badge>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto">
                    {flash.success && (
                        <div className="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-xs font-semibold shadow-sm flex items-center justify-between">
                            <span>{flash.success}</span>
                        </div>
                    )}
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}
