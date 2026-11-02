import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Logo from '@/components/ui/Logo';
import Button from '@/components/ui/AppButton';
import { Menu, X, ArrowRight, User, PhoneCall, ShieldCheck, LogOut, PackageSearch } from 'lucide-react';

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

    const navLinkClass = `text-sm font-semibold transition-colors ${
        scrolled 
            ? 'text-slate-700 hover:text-[#004c68]' 
            : 'text-slate-200 hover:text-white'
    }`;

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
                    {/* Brand Logo - dark logo on transparent hero, light logo on white scrolled navbar */}
                    <div className="flex items-center">
                        <Logo isWhite={!scrolled} showTagline={false} />
                    </div>

                    {/* Desktop Navigation Links */}
                    <nav className="hidden lg:flex items-center gap-7">
                        <a href="#services" className={navLinkClass}>
                            Solutions & Services
                        </a>
                        <a href="#comment-ca-marche" className={navLinkClass}>
                            Comment ça marche
                        </a>
                        <a href="#calculateur" className={navLinkClass}>
                            Simulateur de Tarifs
                        </a>
                        <a href="#pourquoi-nous" className={navLinkClass}>
                            Pourquoi Nous
                        </a>
                        <a href="#temoignages" className={navLinkClass}>
                            Témoignages
                        </a>
                        
                        <button
                            type="button"
                            onClick={onOpenTracking}
                            className="text-sm font-bold text-[#ff8806] hover:text-[#e07400] transition-colors flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#ff8806]/10"
                        >
                            <PackageSearch className="w-4 h-4" />
                            <span>Suivre un Colis</span>
                            <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-ping ml-0.5" />
                        </button>
                    </nav>

                    {/* Desktop Auth CTAs */}
                    <div className="hidden sm:flex items-center gap-3">
                        {auth?.user ? (
                            <div className="flex items-center gap-3">
                                <Link href="/dashboard">
                                    <Button 
                                        variant="primary" 
                                        size="sm" 
                                        icon={User} 
                                        className="font-bold shadow-md bg-[#004c68] hover:bg-[#003b52] text-white border-0"
                                    >
                                        Mon Espace
                                    </Button>
                                </Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className={`text-xs font-semibold flex items-center gap-1 px-3 py-1.5 rounded-xl transition-colors ${
                                        scrolled 
                                            ? 'text-slate-500 hover:text-red-600 hover:bg-red-50' 
                                            : 'text-slate-300 hover:text-red-400 hover:bg-white/10'
                                    }`}
                                >
                                    <LogOut className="w-3.5 h-3.5" />
                                    <span>Déconnexion</span>
                                </Link>
                            </div>
                        ) : (
                            <>
                                <Link href="/login">
                                    <button 
                                        type="button"
                                        className={`text-xs font-bold px-3.5 py-2 rounded-xl transition-colors ${
                                            scrolled 
                                                ? 'text-[#004c68] hover:bg-slate-100' 
                                                : 'text-white hover:bg-white/10'
                                        }`}
                                    >
                                        Espace Client
                                    </button>
                                </Link>
                                <Link href="/register">
                                    <button 
                                        type="button"
                                        className="text-xs font-extrabold px-4 py-2 rounded-xl bg-[#ff8806] hover:bg-[#e07400] text-white shadow-md shadow-[#ff8806]/20 transition-all flex items-center gap-1.5"
                                    >
                                        <span>Devenir Marchand</span>
                                        <ArrowRight className="w-3.5 h-3.5" />
                                    </button>
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Mobile Hamburger Toggle */}
                    <div className="flex items-center lg:hidden">
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className={`p-2 rounded-xl focus:outline-none ${
                                scrolled ? 'text-slate-700 hover:bg-slate-100' : 'text-white hover:bg-white/10'
                            }`}
                            aria-label="Menu"
                        >
                            {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile Dropdown Menu */}
            {mobileMenuOpen && (
                <div className="lg:hidden bg-white/98 backdrop-blur-xl border-b border-slate-200 px-4 pt-3 pb-6 shadow-xl animate-in fade-in slide-in-from-top-4 duration-200">
                    <div className="flex flex-col space-y-3 pt-2">
                        <a
                            href="#services"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-semibold text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Solutions & Services
                        </a>
                        <a
                            href="#comment-ca-marche"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-semibold text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Comment ça marche
                        </a>
                        <a
                            href="#calculateur"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-semibold text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Simulateur de Tarifs
                        </a>
                        <a
                            href="#pourquoi-nous"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-semibold text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Pourquoi Nous
                        </a>
                        <a
                            href="#temoignages"
                            onClick={() => setMobileMenuOpen(false)}
                            className="text-sm font-semibold text-slate-800 hover:text-[#004c68] py-2 border-b border-slate-100"
                        >
                            Témoignages Marchands
                        </a>
                        
                        <button
                            type="button"
                            onClick={() => {
                                setMobileMenuOpen(false);
                                onOpenTracking();
                            }}
                            className="text-sm font-bold text-[#ff8806] py-2.5 flex items-center justify-between"
                        >
                            <span className="flex items-center gap-2">
                                <PackageSearch className="w-4 h-4" />
                                <span>Suivre un Colis en direct</span>
                            </span>
                            <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-ping" />
                        </button>

                        <div className="pt-4 flex flex-col gap-2.5 border-t border-slate-100">
                            {auth?.user ? (
                                <Link
                                    href="/dashboard"
                                    className="w-full text-center py-2.5 px-4 rounded-xl bg-[#004c68] text-white text-xs font-bold"
                                >
                                    Mon Tableau de Bord
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="w-full text-center py-2.5 px-4 rounded-xl border border-[#004c68] text-[#004c68] text-xs font-bold"
                                    >
                                        Connexion Espace Client
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="w-full text-center py-2.5 px-4 rounded-xl bg-[#ff8806] text-white text-xs font-bold shadow-md shadow-[#ff8806]/20"
                                    >
                                        Créer un Compte Marchand
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
