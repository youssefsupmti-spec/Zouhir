import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Logo from '@/components/ui/Logo';
import Button from '@/components/ui/AppButton';
import { Menu, X, ArrowRight, User, PhoneCall, ShieldCheck, LogOut } from 'lucide-react';

export default function Navbar({ onOpenTracking }) {
    const { auth } = usePage().props;
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            setScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <header
            className={`fixed top-0 left-0 right-0 z-40 transition-all duration-300 ${
                scrolled
                    ? 'bg-white/95 backdrop-blur-md shadow-sm shadow-slate-900/5 border-b border-slate-100 py-3.5'
                    : 'bg-transparent py-5'
            }`}
        >
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between">
                    {/* Brand Logo */}
                    <Logo isWhite={false} showTagline={false} />

                    {/* Desktop Navigation Links */}
                    <nav className="hidden lg:flex items-center gap-8">
                        <a
                            href="#services"
                            className="text-sm font-medium text-slate-700 hover:text-[#004c68] transition-colors"
                        >
                            Solutions & Services
                        </a>
                        <a
                            href="#calculateur"
                            className="text-sm font-medium text-slate-700 hover:text-[#004c68] transition-colors"
                        >
                            Simulateur de Tarifs
                        </a>
                        <a
                            href="#pourquoi-nous"
                            className="text-sm font-medium text-slate-700 hover:text-[#004c68] transition-colors"
                        >
                            Pourquoi Ouais Express
                        </a>
                        <a
                            href="#temoignages"
                            className="text-sm font-medium text-slate-700 hover:text-[#004c68] transition-colors"
                        >
                            Témoignages Marchands
                        </a>
                        <button
                            type="button"
                            onClick={onOpenTracking}
                            className="text-sm font-semibold text-[#ff8806] hover:text-[#e07400] transition-colors flex items-center gap-1.5"
                        >
                            <span>Suivre un Colis</span>
                            <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-ping" />
                        </button>
                    </nav>

                    {/* Desktop Auth CTAs */}
                    <div className="hidden sm:flex items-center gap-3">
                        {auth?.user ? (
                            <div className="flex items-center gap-3">
                                <Link href="/dashboard">
                                    <Button variant="primary" size="sm" icon={User} className="font-bold shadow-md shadow-[#004c68]/20">
                                        Mon Tableau de Bord
                                    </Button>
                                </Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="text-xs font-semibold text-slate-500 hover:text-red-600 flex items-center gap-1 px-3 py-1.5 rounded-xl hover:bg-red-50 transition-colors"
                                >
                                    <LogOut className="w-3.5 h-3.5" />
                                    <span>Déconnexion</span>
                                </Link>
                            </div>
                        ) : (
                            <>
                                <Link href="/login">
                                    <Button variant="ghost" size="sm" icon={User}>
                                        Espace Client
                                    </Button>
                                </Link>
                                <Link href="/register">
                                    <Button variant="secondary" size="sm" icon={ArrowRight} iconPosition="right">
                                        Devenir Marchand
                                    </Button>
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Mobile Hamburger Toggle */}
                    <div className="flex items-center sm:hidden">
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="p-2 rounded-xl text-slate-700 hover:bg-slate-100 focus:outline-none"
                            aria-label="Menu"
                        >
                            {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile Dropdown Menu */}
            {mobileMenuOpen && (
                <div className="sm:hidden bg-white/98 backdrop-blur-xl border-b border-slate-200 px-4 pt-3 pb-6 shadow-xl animate-in fade-in slide-in-from-top-4 duration-200">
                    <div className="flex flex-col space-y-3.5 pt-2">
                        <a
                            href="#services"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-medium text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Solutions & Services
                        </a>
                        <a
                            href="#calculateur"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-medium text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Simulateur de Tarifs
                        </a>
                        <a
                            href="#pourquoi-nous"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-medium text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Pourquoi Ouais Express
                        </a>
                        <button
                            type="button"
                            onClick={() => {
                                setMobileMenuOpen(false);
                                onOpenTracking();
                            }}
                            className="text-left text-sm font-semibold text-[#ff8806] py-2 border-b border-slate-100"
                        >
                            🔍 Suivre un Colis en direct
                        </button>

                        <div className="pt-2 flex flex-col gap-2.5">
                            {auth?.user ? (
                                <div className="space-y-2">
                                    <Link href="/dashboard" className="w-full">
                                        <Button variant="primary" size="md" className="w-full font-bold">
                                            Mon Tableau de Bord
                                        </Button>
                                    </Link>
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="w-full text-center text-xs font-semibold text-red-600 py-2.5 rounded-xl border border-red-200"
                                    >
                                        Se déconnecter ({auth.user.name})
                                    </Link>
                                </div>
                            ) : (
                                <>
                                    <Link href="/login" className="w-full">
                                        <Button variant="outline" size="md" className="w-full">
                                            Se Connecter (Espace Client)
                                        </Button>
                                    </Link>
                                    <Link href="/register" className="w-full">
                                        <Button variant="secondary" size="md" className="w-full">
                                            Créer un Compte Marchand
                                        </Button>
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </header>
    );
}
