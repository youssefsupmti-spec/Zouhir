import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { 
    Menu, 
    X, 
    MapPin, 
    Phone, 
    Mail, 
    PackageSearch, 
    User, 
    LogOut, 
    ArrowRight,
    Truck,
    ShieldCheck,
    CheckCircle2,
    Clock,
    Globe,
    ChevronDown
} from 'lucide-react';

export default function Navbar({ onOpenTracking }) {
    const { auth } = usePage().props;
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [offcanvasOpen, setOffcanvasOpen] = useState(false);
    const [homeSubmenuOpen, setHomeSubmenuOpen] = useState(false);
    const [servicesSubmenuOpen, setServicesSubmenuOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            setScrolled(window.scrollY > 40);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Social media links data
    const socialLinks = [
        { name: 'Twitter', icon: '𝕏', href: 'https://twitter.com' },
        { name: 'Facebook', icon: 'f', href: 'https://facebook.com' },
        { name: 'Instagram', icon: '📷', href: 'https://instagram.com' },
        { name: 'LinkedIn', icon: 'in', href: 'https://linkedin.com' },
    ];

    return (
        <>
            <div className={`fixed top-0 left-0 right-0 z-40 transition-all duration-300 w-full ${
                scrolled ? 'top-0' : 'top-0'
            }`}>
                {/* ============================================================== */}
                {/* 1. TOP BAR (Inspired by Envato index-3.html top-header-three)   */}
                {/* ============================================================== */}
                <div className={`transition-all duration-300 overflow-hidden ${
                    scrolled 
                        ? 'max-h-0 opacity-0 pointer-events-none' 
                        : 'max-h-16 opacity-100 bg-[#05161f]/90 backdrop-blur-md border-b border-white/10 text-white'
                }`}>
                    <div className="max-w-[1760px] mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-10 text-xs">
                            
                            {/* Left: Hub Address & Contact Info */}
                            <div className="flex items-center gap-4 sm:gap-6">
                                <div className="flex items-center gap-1.5 text-slate-200">
                                    <MapPin className="w-3.5 h-3.5 text-[#ff8806] shrink-0" />
                                    <span className="hidden sm:inline font-medium">
                                        Zone Industrielle Sidi Maârouf, Casablanca, Maroc
                                    </span>
                                    <span className="sm:hidden font-medium">
                                        Casablanca, Maroc
                                    </span>
                                </div>

                                <div className="hidden md:flex items-center gap-1.5 text-slate-200">
                                    <Mail className="w-3.5 h-3.5 text-[#ff8806] shrink-0" />
                                    <a 
                                        href="mailto:contact@ouaissexpress.com" 
                                        className="hover:text-[#ff8806] transition-colors"
                                    >
                                        contact@ouaissexpress.com
                                    </a>
                                </div>
                            </div>

                            {/* Right: Clipped Badge (Help/Support/FAQ) + Social Links */}
                            <div className="flex items-center gap-4 sm:gap-6">
                                {/* Envato Trapezoid Badge (.both-clipped) */}
                                <div className="both-clipped bg-gradient-to-r from-[#ff8806] to-[#e07400] text-white px-7 sm:px-9 py-1.5 font-bold text-[11px] tracking-wide flex items-center gap-4 shadow-sm select-none">
                                    <a href="#faq" className="hover:underline transition-transform hover:-translate-y-0.5">
                                        Aide
                                    </a>
                                    <span className="text-white/40 font-normal">|</span>
                                    <a 
                                        href="https://wa.me/212600000000?text=Bonjour%20OUAISS%20EXPRESS" 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        className="hover:underline transition-transform hover:-translate-y-0.5"
                                    >
                                        Support WhatsApp
                                    </a>
                                    <span className="text-white/40 font-normal">|</span>
                                    <a href="#faq" className="hover:underline transition-transform hover:-translate-y-0.5">
                                        FAQ
                                    </a>
                                </div>

                                {/* Social Icons */}
                                <div className="hidden lg:flex items-center gap-3 text-slate-300">
                                    {socialLinks.map((s) => (
                                        <a
                                            key={s.name}
                                            href={s.href}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="w-5 h-5 flex items-center justify-center font-bold text-xs hover:text-[#ff8806] transition-transform hover:scale-125"
                                            title={s.name}
                                        >
                                            {s.icon}
                                        </a>
                                    ))}
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {/* ============================================================== */}
                {/* 2. HEADER START (Strict Structure Index-3.html lines 283-475)  */}
                {/* ============================================================== */}
                <header className="transition-all duration-300 links-white">
                    <div className="max-w-[1760px] mx-auto px-4 lg:pe-0">
                        <nav className={`bg-[#07485e] flex items-center justify-between ps-3 pe-4 sm:pe-8 lg:pe-[100px] py-3 lg:py-0 transition-all duration-300 shadow-xl ${
                            scrolled ? 'bg-[#07485e]/95 backdrop-blur-md shadow-2xl' : ''
                        }`}>

                            {/* Left Side: Logo & Desktop Menu */}
                            <div className="flex items-center">
                                
                                {/* Desktop Logo with Hexagon-Right (.hexagon-right) on Vibrant Orange #f78304 */}
                                <Link 
                                    href="/"
                                    className="cursor-pointer bg-[#f78304] hidden lg:flex items-center justify-center ps-6 lg:ps-10 pe-12 lg:pe-18 py-3.5 lg:py-4.5 hexagon-right shrink-0 transition-transform duration-200 hover:brightness-105 shadow-md"
                                    title="OUAISS EXPRESS - Accueil"
                                >
                                    <img 
                                        src="/images/logo-full-dark.png" 
                                        alt="OUAISS EXPRESS" 
                                        className="h-9.5 lg:h-11 w-auto max-w-[210px] lg:max-w-[245px] object-contain drop-shadow-sm"
                                    />
                                </Link>

                                {/* Mobile Logo (Without Hexagon, Direct on Nav Parent) */}
                                <Link 
                                    href="/"
                                    className="lg:hidden flex items-center justify-center py-1.5 ps-1"
                                    title="OUAISS EXPRESS - Accueil"
                                >
                                    <img 
                                        src="/images/logo-full-dark.png" 
                                        alt="OUAISS EXPRESS" 
                                        className="h-8.5 sm:h-9.5 w-auto max-w-[170px] sm:max-w-[195px] object-contain"
                                    />
                                </Link>

                                {/* Desktop Navigation Menu (with .ps-108-px offset after hexagon point) */}
                                <div className="header-menu hidden lg:block ps-108-px">
                                    <ul className="flex items-center gap-4 xl:gap-8 text-sm font-semibold text-white">
                                        
                                        {/* Item 1: Solutions + Dropdown */}
                                        <li 
                                            className="relative group py-5"
                                            onMouseEnter={() => setHomeSubmenuOpen(true)}
                                            onMouseLeave={() => setHomeSubmenuOpen(false)}
                                        >
                                            <a 
                                                href="#services"
                                                className="flex items-center gap-1 hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                            >
                                                <span>Solutions</span>
                                                <span className="text-[#ff8806] font-bold text-xs transition-transform duration-200 group-hover:rotate-45">+</span>
                                            </a>

                                            {/* Submenu Dropdown */}
                                            {homeSubmenuOpen && (
                                                <ul className="absolute left-0 top-full pt-2 w-56 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                                                    <li className="bg-[#05161f] border border-white/10 rounded-xl shadow-2xl p-2 text-xs space-y-1">
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Marchands & E-commerce
                                                        </a>
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Ramassage 100% Gratuit
                                                        </a>
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Confirmation WhatsApp
                                                        </a>
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Virement CRBT à J+2
                                                        </a>
                                                    </li>
                                                </ul>
                                            )}
                                        </li>

                                        {/* Item 2: Services + Dropdown */}
                                        <li 
                                            className="relative group py-5"
                                            onMouseEnter={() => setServicesSubmenuOpen(true)}
                                            onMouseLeave={() => setServicesSubmenuOpen(false)}
                                        >
                                            <a 
                                                href="#services"
                                                className="flex items-center gap-1 hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                            >
                                                <span>Services</span>
                                                <span className="text-[#ff8806] font-bold text-xs transition-transform duration-200 group-hover:rotate-45">+</span>
                                            </a>

                                            {/* Submenu Dropdown */}
                                            {servicesSubmenuOpen && (
                                                <ul className="absolute left-0 top-full pt-2 w-60 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                                                    <li className="bg-[#05161f] border border-white/10 rounded-xl shadow-2xl p-2 text-xs space-y-1">
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Livraison Express 24/48h
                                                        </a>
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Cash on Delivery (CRBT)
                                                        </a>
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Gestion des Retours
                                                        </a>
                                                        <a 
                                                            href="#services" 
                                                            className="block px-3 py-2 rounded-lg text-slate-200 hover:text-white hover:bg-white/10 transition-colors"
                                                        >
                                                            Stockage & Fulfillment
                                                        </a>
                                                    </li>
                                                </ul>
                                            )}
                                        </li>

                                        {/* Item 3: Comment ça marche */}
                                        <li className="py-5">
                                            <a 
                                                href="#comment-ca-marche" 
                                                className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                            >
                                                Comment ça marche
                                            </a>
                                        </li>

                                        {/* Item 4: Simulateur de Tarifs */}
                                        <li className="py-5">
                                            <a 
                                                href="#calculateur" 
                                                className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                            >
                                                Simulateur Tarifs
                                            </a>
                                        </li>

                                        {/* Item 5: Pourquoi Nous */}
                                        <li className="py-5">
                                            <a 
                                                href="#pourquoi-nous" 
                                                className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                            >
                                                Pourquoi Nous
                                            </a>
                                        </li>

                                        {/* Item 6: Contact */}
                                        <li className="py-5">
                                            <a 
                                                href="#contact" 
                                                className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                            >
                                                Contact
                                            </a>
                                        </li>

                                    </ul>
                                </div>

                            </div>

                            {/* Header Right (Matches index-3.html gap-xxl-5 gap-3) */}
                            <div className="flex items-center gap-3 xl:gap-6">
                                <div className="flex items-center gap-3 xl:gap-6 flex-shrink-0">
                                    
                                    {/* Suivi Colis Quick Action (Styled like Cart / Tracker in Envato) */}
                                    <button
                                        type="button"
                                        onClick={() => onOpenTracking()}
                                        className="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 hover:bg-[#ff8806] text-white text-xs font-semibold transition-all hover:-translate-y-0.5 group"
                                        title="Suivre un colis OUAISS EXPRESS en direct"
                                    >
                                        <PackageSearch className="w-4 h-4 text-[#ff8806] group-hover:text-white transition-colors" />
                                        <span className="hidden xl:inline">Suivi Colis</span>
                                        <span className="w-2 h-2 rounded-full bg-[#ff8806] group-hover:bg-white animate-pulse" />
                                    </button>

                                    {/* Line Separator: matches index-3.html <span class="line light-white h-100 d-xl-block d-none"></span> */}
                                    <span className="h-8 w-px bg-white/20 hidden xl:block" />

                                    {/* Offcanvas Bar Icon: matches index-3.html <button class="offcanvas-bar-icon ..."><img src="...bars-two.svg"></button> */}
                                    <button
                                        type="button"
                                        onClick={() => setOffcanvasOpen(true)}
                                        className="p-2 rounded-lg hover:bg-white/10 transition-transform hover:scale-110 hidden sm:flex items-center justify-center cursor-pointer"
                                        title="Menu latéral & Informations Hub"
                                    >
                                        <img 
                                            src="/images/landing/icons/bars-two.svg" 
                                            alt="Menu" 
                                            className="w-5 h-5 object-contain" 
                                        />
                                    </button>

                                    {/* Espace Client CTA Button */}
                                    {auth?.user ? (
                                        <Link 
                                            href="/dashboard"
                                            className="px-4 py-2 rounded-lg bg-[#ff8806] hover:bg-[#e07400] text-white text-xs font-bold transition-all shadow-md shadow-[#ff8806]/20 flex items-center gap-1.5"
                                        >
                                            <User className="w-3.5 h-3.5" />
                                            <span>Mon Espace</span>
                                        </Link>
                                    ) : (
                                        <Link 
                                            href="/login"
                                            className="px-4 py-2 rounded-lg bg-[#ff8806] hover:bg-[#e07400] text-white text-xs font-bold transition-all shadow-md shadow-[#ff8806]/20 flex items-center gap-1.5"
                                        >
                                            <User className="w-3.5 h-3.5" />
                                            <span>Espace Client</span>
                                        </Link>
                                    )}

                                </div>

                                {/* Mobile Hamburger Button: matches index-3.html <button class="toggle-mobileMenu leading-none d-lg-none text-white tw-text-9"> */}
                                <button
                                    type="button"
                                    onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                    className="lg:hidden p-2 text-white hover:text-[#ff8806] transition-colors"
                                    aria-label="Menu Mobile"
                                >
                                    {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                                </button>

                            </div>

                        </nav>
                    </div>
                </header>

                {/* Mobile Dropdown Menu (matches index-3.html mobile nav drawer) */}
                {mobileMenuOpen && (
                    <div className="lg:hidden max-w-[1760px] mx-auto px-4 mt-2">
                        <div className="bg-[#05161f]/98 backdrop-blur-2xl border border-white/10 rounded-2xl p-5 shadow-2xl text-white space-y-3 animate-in fade-in slide-in-from-top-4 duration-200">
                            <a 
                                href="#services"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block py-2 text-sm font-semibold border-b border-white/10 hover:text-[#ff8806]"
                            >
                                Solutions & Services
                            </a>
                            <a 
                                href="#comment-ca-marche"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block py-2 text-sm font-semibold border-b border-white/10 hover:text-[#ff8806]"
                            >
                                Comment ça marche
                            </a>
                            <a 
                                href="#calculateur"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block py-2 text-sm font-semibold border-b border-white/10 hover:text-[#ff8806]"
                            >
                                Simulateur de Tarifs
                            </a>
                            <a 
                                href="#pourquoi-nous"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block py-2 text-sm font-semibold border-b border-white/10 hover:text-[#ff8806]"
                            >
                                Pourquoi Nous
                            </a>
                            <a 
                                href="#temoignages"
                                onClick={() => setMobileMenuOpen(false)}
                                className="block py-2 text-sm font-semibold border-b border-white/10 hover:text-[#ff8806]"
                            >
                                Témoignages
                            </a>
                            
                            <button
                                type="button"
                                onClick={() => {
                                    setMobileMenuOpen(false);
                                    onOpenTracking();
                                }}
                                className="w-full py-2.5 px-4 rounded-xl bg-[#ff8806]/15 text-[#ff8806] text-xs font-bold flex items-center justify-between"
                            >
                                <span className="flex items-center gap-2">
                                    <PackageSearch className="w-4 h-4" />
                                    <span>Suivre un Colis en direct</span>
                                </span>
                                <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-ping" />
                            </button>

                            <div className="pt-3 flex flex-col gap-2">
                                {auth?.user ? (
                                    <Link 
                                        href="/dashboard"
                                        className="w-full text-center py-2.5 rounded-xl bg-[#004c68] text-white text-xs font-bold"
                                    >
                                        Tableau de Bord
                                    </Link>
                                ) : (
                                    <>
                                        <Link 
                                            href="/login"
                                            className="w-full text-center py-2.5 rounded-xl border border-white/20 text-white text-xs font-bold hover:bg-white/10"
                                        >
                                            Connexion Espace Client
                                        </Link>
                                        <Link 
                                            href="/register"
                                            className="w-full text-center py-2.5 rounded-xl bg-[#ff8806] text-white text-xs font-bold shadow-md shadow-[#ff8806]/20"
                                        >
                                            Créer un Compte Marchand
                                        </Link>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* ============================================================== */}
            {/* 3. OFFCANVAS SIDEBAR (Slide-in Drawer inspired by Envato)       */}
            {/* ============================================================== */}
            {offcanvasOpen && (
                <div className="fixed inset-0 z-50">
                    {/* Backdrop Overlay */}
                    <div 
                        className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity animate-in fade-in duration-200"
                        onClick={() => setOffcanvasOpen(false)}
                    />

                    {/* Drawer Content */}
                    <div className="fixed inset-y-0 right-0 w-full max-w-sm sm:max-w-md bg-[#05161f] text-white shadow-2xl p-6 sm:p-8 flex flex-col justify-between overflow-y-auto z-50 animate-in slide-in-from-right duration-300 border-l border-white/10">
                        
                        <div className="space-y-8">
                            {/* Header: Logo Adapted + Close Button */}
                            <div className="flex items-center justify-between">
                                <img 
                                    src="/images/logo-full-dark.png" 
                                    alt="OUAISS EXPRESS" 
                                    className="h-9.5 w-auto max-w-[210px] object-contain" 
                                />
                                <button
                                    type="button"
                                    onClick={() => setOffcanvasOpen(false)}
                                    className="w-9 h-9 rounded-full border border-[#ff8806] text-[#ff8806] hover:bg-[#ff8806] hover:text-white transition-colors flex items-center justify-center text-sm font-bold"
                                    aria-label="Fermer"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            {/* Presentation */}
                            <div className="space-y-3">
                                <h4 className="text-lg font-bold text-white flex items-center gap-2">
                                    <span>À Propos d'OUAISS EXPRESS</span>
                                    <span className="w-8 h-0.5 bg-[#ff8806]" />
                                </h4>
                                <p className="text-xs sm:text-sm text-slate-300 leading-relaxed">
                                    Partenaire logistique et livraison express de référence au Maroc pour les e-commerçants. Nous combinons un réseau national 24/48h, confirmation WhatsApp des adresses et reversements CRBT garantis à J+2.
                                </p>
                            </div>

                            {/* Contact Details */}
                            <div className="space-y-4">
                                <h4 className="text-lg font-bold text-white flex items-center gap-2">
                                    <span>Coordonnées & Hub</span>
                                    <span className="w-8 h-0.5 bg-[#ff8806]" />
                                </h4>

                                <div className="space-y-3.5 text-xs sm:text-sm">
                                    <div className="flex items-start gap-3.5">
                                        <div className="w-8 h-8 rounded-full bg-[#ff8806]/15 border border-[#ff8806]/30 text-[#ff8806] flex items-center justify-center shrink-0">
                                            <Phone className="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div className="text-slate-400 text-[11px]">Téléphone Support</div>
                                            <a href="tel:+212522000000" className="font-semibold text-white hover:text-[#ff8806]">
                                                +212 5 22 00 00 00
                                            </a>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3.5">
                                        <div className="w-8 h-8 rounded-full bg-[#ff8806]/15 border border-[#ff8806]/30 text-[#ff8806] flex items-center justify-center shrink-0">
                                            <Mail className="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div className="text-slate-400 text-[11px]">Email Service Client</div>
                                            <a href="mailto:contact@ouaissexpress.com" className="font-semibold text-white hover:text-[#ff8806]">
                                                contact@ouaissexpress.com
                                            </a>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3.5">
                                        <div className="w-8 h-8 rounded-full bg-[#ff8806]/15 border border-[#ff8806]/30 text-[#ff8806] flex items-center justify-center shrink-0">
                                            <MapPin className="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div className="text-slate-400 text-[11px]">Hub Central National</div>
                                            <p className="font-semibold text-white">
                                                Zone Industrielle Sidi Maârouf, Casablanca
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Quick Actions */}
                            <div className="space-y-2.5 pt-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setOffcanvasOpen(false);
                                        onOpenTracking();
                                    }}
                                    className="w-full py-2.5 px-4 rounded-xl bg-[#ff8806] hover:bg-[#e07400] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 shadow-md shadow-[#ff8806]/20 cursor-pointer"
                                >
                                    <PackageSearch className="w-4 h-4" />
                                    <span>Suivre un Colis Express</span>
                                </button>

                                <Link
                                    href="/register"
                                    onClick={() => setOffcanvasOpen(false)}
                                    className="w-full py-2.5 px-4 rounded-xl border border-white/20 hover:border-white/40 text-white text-xs font-bold transition-all flex items-center justify-center gap-2 hover:bg-white/5"
                                >
                                    <span>Devenir Marchand Partenaire</span>
                                    <ArrowRight className="w-3.5 h-3.5" />
                                </Link>
                            </div>
                        </div>

                        {/* Footer in Drawer: Social Media */}
                        <div className="pt-6 border-t border-white/10 flex items-center justify-between text-xs text-slate-400">
                            <span>© 2026 OUAISS EXPRESS</span>
                            <div className="flex items-center gap-3">
                                {socialLinks.map((s) => (
                                    <a
                                        key={s.name}
                                        href={s.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="hover:text-[#ff8806] transition-colors font-bold"
                                        title={s.name}
                                    >
                                        {s.icon}
                                    </a>
                                ))}
                            </div>
                        </div>

                    </div>
                </div>
            )}
        </>
    );
}
