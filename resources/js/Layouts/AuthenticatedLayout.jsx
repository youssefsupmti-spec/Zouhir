import React, { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import Logo from '@/Components/ui/Logo';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import { 
    LayoutDashboard, 
    Package, 
    Truck, 
    Wallet, 
    Settings, 
    LogOut, 
    Menu, 
    X, 
    Bell, 
    Search,
    ChevronDown,
    ExternalLink
} from 'lucide-react';

export default function AuthenticatedLayout({ children, title = "Tableau de bord" }) {
    const { auth = {}, flash = {} } = usePage().props;
    const user = auth.user || {};
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const handleLogout = () => {
        router.post('/logout');
    };

    const navigation = [
        { name: 'Tableau de bord', href: '/dashboard', icon: LayoutDashboard, current: true },
        { name: 'Mes Expéditions', href: '#', icon: Package, badge: '14' },
        { name: 'Suivi National', href: '#', icon: Truck },
        { name: 'Relevés & CRBT', href: '#', icon: Wallet, badge: 'MAD' },
        { name: 'Paramètres Compte', href: '#', icon: Settings },
    ];

    return (
        <div className="min-h-screen bg-slate-50 flex">
            {/* Mobile Sidebar Backdrop */}
            {sidebarOpen && (
                <div 
                    className="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar Desktop & Mobile */}
            <aside className={`
                fixed inset-y-0 left-0 z-50 w-72 bg-[#05161f] text-white flex flex-col border-r border-slate-800 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:flex
                ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
            `}>
                {/* Brand Header */}
                <div className="h-20 flex items-center justify-between px-6 border-b border-slate-800/80 bg-[#004c68]/20">
                    <Logo isWhite={true} showTagline={false} />
                    <button 
                        onClick={() => setSidebarOpen(false)}
                        className="lg:hidden p-1.5 text-slate-400 hover:text-white rounded-lg"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Account / Store Badge */}
                <div className="p-4 mx-4 my-4 rounded-2xl bg-gradient-to-br from-[#004c68]/40 to-[#ff8806]/10 border border-slate-800/80">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-[#ff8806]/20 text-[#ff8806] flex items-center justify-center font-bold text-sm">
                            {user.name ? user.name.substring(0, 2).toUpperCase() : 'OE'}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="text-sm font-bold text-white truncate">
                                {user.store_name || user.name || 'Espace Client'}
                            </div>
                            <div className="text-[11px] text-slate-400 truncate flex items-center gap-1.5">
                                <span className="w-2 h-2 rounded-full bg-emerald-400" />
                                {user.city ? `Hub ${user.city}` : 'Client Actif'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Nav Links */}
                <nav className="flex-1 px-4 space-y-1.5 overflow-y-auto">
                    {navigation.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={`
                                flex items-center justify-between px-4 py-3 rounded-xl text-xs font-semibold transition-all
                                ${item.current 
                                    ? 'bg-[#004c68] text-white shadow-md shadow-[#004c68]/30 font-bold' 
                                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60'}
                            `}
                        >
                            <div className="flex items-center gap-3">
                                <item.icon className={`w-4 h-4 ${item.current ? 'text-[#ff8806]' : 'text-slate-400'}`} />
                                <span>{item.name}</span>
                            </div>
                            {item.badge && (
                                <span className="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-300 font-bold">
                                    {item.badge}
                                </span>
                            )}
                        </Link>
                    ))}
                </nav>

                {/* Quick Link to Landing Page */}
                <div className="px-4 py-3">
                    <Link
                        href="/"
                        className="flex items-center gap-2 text-xs text-slate-400 hover:text-white px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-800 transition-colors"
                    >
                        <ExternalLink className="w-3.5 h-3.5 text-[#ff8806]" />
                        <span>Voir la Vitrine Publique</span>
                    </Link>
                </div>

                {/* User Footer & Logout */}
                <div className="p-4 border-t border-slate-800/80 bg-slate-950/40">
                    <div className="flex items-center justify-between">
                        <div className="min-w-0 pr-2">
                            <p className="text-xs font-bold text-white truncate">{user.name}</p>
                            <p className="text-[11px] text-slate-400 truncate">{user.email}</p>
                        </div>
                        <button
                            onClick={handleLogout}
                            title="Se déconnecter"
                            className="p-2 text-slate-400 hover:text-red-400 rounded-lg hover:bg-red-500/10 transition-colors"
                        >
                            <LogOut className="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
                {/* Topbar */}
                <header className="h-20 bg-white border-b border-slate-200/80 px-6 flex items-center justify-between sticky top-0 z-30 shadow-sm">
                    <div className="flex items-center gap-4">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden p-2 text-slate-600 hover:text-slate-900 rounded-lg"
                        >
                            <Menu className="w-6 h-6" />
                        </button>
                        <h1 className="text-xl font-extrabold text-[#004c68] tracking-tight font-['Inter']">
                            {title}
                        </h1>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-700">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span>Réseau Opérationnel</span>
                        </div>

                        <Badge variant="secondary" size="md">
                            Espace Marchand
                        </Badge>

                        <button
                            onClick={handleLogout}
                            className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-red-600 rounded-lg transition-colors border border-slate-200"
                        >
                            <LogOut className="w-3.5 h-3.5" />
                            <span>Déconnexion</span>
                        </button>
                    </div>
                </header>

                {/* Page Body */}
                <main className="flex-1 p-6 md:p-8 overflow-y-auto">
                    {flash.success && (
                        <div className="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-xs font-semibold shadow-sm flex items-center justify-between">
                            <span>{flash.success}</span>
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
