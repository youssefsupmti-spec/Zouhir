import React, { useState, useEffect, useRef } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { 
    Menu, 
    X, 
    ChevronDown, 
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
    Globe
} from 'lucide-react';

export default function Navbar({ onOpenTracking }) {
    const { auth } = usePage().props;
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [offcanvasOpen, setOffcanvasOpen] = useState(false);
    const [langDropdownOpen, setLangDropdownOpen] = useState(false);
    const [currentLang, setCurrentLang] = useState('Français');
    const [homeSubmenuOpen, setHomeSubmenuOpen] = useState(false);
    const [servicesSubmenuOpen, setServicesSubmenuOpen] = useState(false);

    const langRef = useRef(null);

    useEffect(() => {
        const handleScroll = () => {
            setScrolled(window.scrollY > 40);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Close dropdowns on outside click
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (langRef.current && !langRef.current.contains(event.target)) {
                setLangDropdownOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Social media icons data
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
                        : 'max-h-16 opacity-100 bg-[#05161f]/85 backdrop-blur-md border-b border-white/10 text-white'
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
                {/* 2. MAIN HEADER NAVIGATION (Inspired by Envato Header Three)    */}
                {/* ============================================================== */}
                <header className="transition-all duration-300">
                    <div className="max-w-[1760px] mx-auto px-2 sm:px-4 lg:px-6 mt-1 sm:mt-2">
                        <nav className={`flex items-center justify-between rounded-lg sm:rounded-xl transition-all duration-300 border border-white/10 shadow-2xl ${
                            scrolled
                                ? 'bg-[#004c68]/95 backdrop-blur-xl shadow-slate-950/40 py-2 sm:py-2.5 px-3 sm:px-6'
                                : 'bg-[#004c68] py-2 sm:py-3 px-3 sm:px-6'
                        }`}>

                            {/* Left Side: Hexagon Logo Container + Desktop Navigation */}
                            <div className="flex items-center gap-4 lg:gap-8">
                                
                                {/* Hexagon-Right Pointed Brand Logo Container (.hexagon-right) */}
                                <Link 
                                    href="/"
                                    className="hexagon-right bg-gradient-to-r from-[#ff8806] via-[#ff8806] to-[#e07400] flex items-center justify-center pl-4 sm:pl-7 pr-8 sm:pr-12 py-2 sm:py-2.5 transition-transform duration-200 hover:scale-[1.02] shadow-md group"
                                    title="OUAISS EXPRESS - Accueil"
                                >
                                    <img 
                                        src="/images/logo-full-white.png" 
                                        alt="OUAISS EXPRESS" 
                                        className="h-7 sm:h-8.5 w-auto max-w-[150px] sm:max-w-[185px] object-contain drop-shadow-sm"
                                    />
                                </Link>

                                {/* Desktop Navigation Menu */}
                                <div className="hidden xl:flex items-center gap-6 text-sm font-semibold text-white">
                                    
                                    {/* Item 1: Solutions + */}
                                    <div 
                                        className="relative group py-2"
                                        onMouseEnter={() => setHomeSubmenuOpen(true)}
                                        onMouseLeave={() => setHomeSubmenuOpen(false)}
                                    >
                                        <button 
                                            type="button" 
                                            className="flex items-center gap-1 hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                        >
                                            <span>Solutions</span>
                                            <span className="text-[#ff8806] font-bold text-xs transition-transform duration-200 group-hover:rotate-45">+</span>
                                        </button>

                                        {/* Dropdown Submenu */}
                                        {homeSubmenuOpen && (
                                            <div className="absolute left-0 top-full pt-2 w-56 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                                                <div className="bg-[#05161f] border border-white/10 rounded-xl shadow-2xl p-2 text-xs space-y-1">
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
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Item 2: Services + */}
                                    <div 
                                        className="relative group py-2"
                                        onMouseEnter={() => setServicesSubmenuOpen(true)}
                                        onMouseLeave={() => setServicesSubmenuOpen(false)}
                                    >
                                        <button 
                                            type="button" 
                                            className="flex items-center gap-1 hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                        >
                                            <span>Services</span>
                                            <span className="text-[#ff8806] font-bold text-xs transition-transform duration-200 group-hover:rotate-45">+</span>
                                        </button>

                                        {/* Dropdown Submenu */}
                                        {servicesSubmenuOpen && (
                                            <div className="absolute left-0 top-full pt-2 w-60 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                                                <div className="bg-[#05161f] border border-white/10 rounded-xl shadow-2xl p-2 text-xs space-y-1">
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
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Item 3: Comment ça marche */}
                                    <a 
                                        href="#comment-ca-marche" 
                                        className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                    >
                                        Comment ça marche
                                    </a>

                                    {/* Item 4: Simulateur de Tarifs */}
                                    <a 
                                        href="#calculateur" 
                                        className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                    >
                                        Simulateur Tarifs
                                    </a>

                                    {/* Item 5: Pourquoi Nous */}
                                    <a 
                                        href="#pourquoi-nous" 
                                        className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                    >
                                        Pourquoi Nous
                                    </a>

                                    {/* Item 6: Contact */}
                                    <a 
                                        href="#contact" 
                                        className="hover:text-[#ff8806] transition-all hover:-translate-y-0.5"
                                    >
                                        Contact
                                    </a>

                                </div>

                            </div>

                            {/* Right Side: Language + Tracking Icon + Divider + Offcanvas 9-dots + Auth Button */}
                            <div className="flex items-center gap-3 sm:gap-5">
                                
                                {/* 1. Language Dropdown Selector (Morocco / Arab / English) */}
                                <div className="relative" ref={langRef}>
                                    <button
                                        type="button"
                                        onClick={() => setLangDropdownOpen(!langDropdownOpen)}
                                        className="flex items-center gap-1.5 text-xs font-semibold text-white hover:text-[#ff8806] transition-colors py-1 px-2 rounded-lg hover:bg-white/10"
                                    >
                                        <span className="text-sm">🇲🇦</span>
                                        <span className="hidden sm:inline">{currentLang}</span>
                                        <ChevronDown className="w-3.5 h-3.5 opacity-70" />
                                    </button>

                                    {langDropdownOpen && (
                                        <div className="absolute right-0 top-full mt-2 w-36 bg-[#05161f] border border-white/10 rounded-xl shadow-2xl p-1.5 text-xs text-white z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                                            <button
                                                type="button"
                                                onClick={() => { setCurrentLang('Français'); setLangDropdownOpen(false); }}
                                                className={`w-full text-left px-3 py-2 rounded-lg flex items-center gap-2 hover:bg-white/10 transition-colors ${
                                                    currentLang === 'Français' ? 'text-[#ff8806] font-bold bg-white/5' : 'text-slate-200'
                                                }`}
                                            >
                                                <span>🇲🇦</span>
                                                <span>Français</span>
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setCurrentLang('العربية'); setLangDropdownOpen(false); }}
                                                className={`w-full text-left px-3 py-2 rounded-lg flex items-center gap-2 hover:bg-white/10 transition-colors font-arabic ${
                                                    currentLang === 'العربية' ? 'text-[#ff8806] font-bold bg-white/5' : 'text-slate-200'
                                                }`}
                                            >
                                                <span>🇲🇦</span>
                                                <span>العربية</span>
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setCurrentLang('English'); setLangDropdownOpen(false); }}
                                                className={`w-full text-left px-3 py-2 rounded-lg flex items-center gap-2 hover:bg-white/10 transition-colors ${
                                                    currentLang === 'English' ? 'text-[#ff8806] font-bold bg-white/5' : 'text-slate-200'
                                                }`}
                                            >
                                                <span>🇬🇧</span>
                                                <span>English</span>
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {/* 2. Cart / Parcel Tracking Shortcut with Pulse Badge */}
                                <button
                                    type="button"
                                    onClick={onOpenTracking}
                                    className="relative flex items-center justify-center p-2 rounded-lg hover:bg-white/10 transition-transform hover:scale-105"
                                    title="Suivre un Colis en direct"
                                >
                                    <img 
                                        src="/images/landing/icons/cart-icon.svg" 
                                        alt="Suivi Colis" 
                                        className="w-4 h-5 object-contain" 
                                    />
                                    <span className="absolute -top-1 -right-1 bg-[#ff8806] text-white text-[10px] font-extrabold w-4 h-4 rounded-full flex items-center justify-center shadow-xs">
                                        0
                                    </span>
                                </button>

                                {/* 3. Vertical Separator Line */}
                                <span className="h-6 w-px bg-white/20 hidden sm:block" />

                                {/* 4. 3x3 Dots Offcanvas Menu Button (bars-two.svg) */}
                                <button
                                    type="button"
                                    onClick={() => setOffcanvasOpen(true)}
                                    className="flex items-center justify-center p-2 rounded-lg hover:bg-white/10 transition-transform hover:scale-110"
                                    title="Menu latéral & Informations"
                                >
                                    <img 
                                        src="/images/landing/icons/bars-two.svg" 
                                        alt="Menu" 
                                        className="w-5 h-5 object-contain" 
                                    />
                                </button>

                                {/* 5. Espace Client Button (Direct CTA) */}
                                <div className="hidden md:flex items-center">
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
                                            className="px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 border border-white/20 text-white text-xs font-bold transition-all flex items-center gap-1.5"
                                        >
                                            <User className="w-3.5 h-3.5" />
                                            <span>Espace Client</span>
                                        </Link>
                                    )}
                                </div>

                                {/* Mobile Hamburger Toggle */}
                                <button
                                    type="button"
                                    onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                    className="xl:hidden p-2 text-white hover:text-[#ff8806] transition-colors"
                                    aria-label="Menu Mobile"
                                >
                                    {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                                </button>

                            </div>

                        </nav>
                    </div>
                </header>

                {/* Mobile Dropdown Menu */}
                {mobileMenuOpen && (
                    <div className="xl:hidden max-w-[1760px] mx-auto px-2 sm:px-4 mt-2">
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
                            {/* Header: Logo + Close Button */}
                            <div className="flex items-center justify-between">
                                <img 
                                    src="/images/logo-full-white.png" 
                                    alt="OUAISS EXPRESS" 
                                    className="h-8 w-auto max-w-[180px] object-contain" 
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
                                    className="w-full py-2.5 px-4 rounded-xl bg-[#ff8806] hover:bg-[#e07400] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 shadow-md shadow-[#ff8806]/20"
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
