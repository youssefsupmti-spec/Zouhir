import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import Button from '@/components/ui/AppButton';
import { 
    Search, 
    ArrowRight, 
    ShieldCheck, 
    Clock, 
    Truck, 
    Banknote, 
    MapPin,
    ChevronDown,
    PackageCheck,
    CheckCircle2
} from 'lucide-react';

export default function HeroSection({ onTrackSearch, statistics = [] }) {
    const [trackingInput, setTrackingInput] = useState('');

    const handleSubmit = (e) => {
        e.preventDefault();
        if (trackingInput.trim()) {
            onTrackSearch(trackingInput.trim());
        }
    };

    return (
        <section className="relative w-full min-h-screen min-h-[100dvh] pt-24 sm:pt-28 lg:pt-32 pb-8 sm:pb-10 lg:pb-12 overflow-hidden bg-[#05161f] text-white flex flex-col justify-between items-center">
            {/* Background Hero Image HD (/images/Bannière logistique Ouaiss Express au Maroc.png) with Envato Index-3 Style Overlay */}
            <div 
                className="absolute inset-0 z-0 bg-cover bg-center bg-no-repeat transition-all duration-700"
                style={{ backgroundImage: "url('/images/Banni%C3%A8re%20logistique%20Ouaiss%20Express%20au%20Maroc.png')" }}
            >
                {/* Lighter Cinematic Gradient Overlay to let the official vehicle & Morocco map shine while keeping text crystal clear */}
                <div className="absolute inset-0 bg-gradient-to-r from-[#05161f]/90 via-[#05161f]/60 to-transparent" />
                <div className="absolute inset-0 bg-gradient-to-b from-[#05161f]/75 via-transparent to-[#05161f]/85" />
            </div>

            {/* Ambient Background Glowing Accents */}
            <div className="absolute top-12 right-12 w-96 h-96 bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none z-0" />
            <div className="absolute bottom-12 left-12 w-96 h-96 bg-[#004c68]/25 rounded-full blur-3xl pointer-events-none z-0" />

            {/* Main Content Grid : Left Section (Pitch, Tracking Bar, CTAs) | Right Section (Open to show Van & Map) */}
            <div className="my-auto w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                    
                    {/* ======================================================== */}
                    {/* LEFT SECTION (col-span-12 lg:col-span-7)                 */}
                    {/* ======================================================== */}
                    <div className="lg:col-span-7 text-left space-y-6 sm:space-y-7">
                        
                        {/* 1. Innovation Pill / Badge */}
                        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#05161f]/85 backdrop-blur-md border border-white/20 text-xs font-semibold text-white shadow-lg">
                            <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-pulse" />
                            <span className="text-[#ff8806] font-bold">N°1 DE LA LIVRAISON E-COMMERCE AU MAROC</span>
                            <span className="text-white/40">•</span>
                            <span className="text-slate-200">Partout au Maroc en 24–48h</span>
                        </div>

                        {/* 2. Main Title (High Contrast with Drop Shadow) */}
                        <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black font-sans tracking-tight leading-[1.15] text-white drop-shadow-[0_4px_16px_rgba(0,0,0,0.85)]">
                            Livrez plus vite,{' '}
                            <span className="text-[#ff8806]">
                                vendez partout
                            </span>{' '}
                            au Maroc.
                        </h1>

                        {/* 3. Short & Professional Subtitle */}
                        <p className="text-sm sm:text-base lg:text-lg text-slate-100 max-w-xl leading-relaxed font-medium drop-shadow-[0_2px_8px_rgba(0,0,0,0.8)]">
                            La solution de livraison e-commerce la plus fiable du Royaume. Ramassage offert, expédition express en <strong className="text-white font-bold underline decoration-[#ff8806]/60">24–48h</strong> et reversement garanti de vos fonds sous <strong className="text-white font-bold underline decoration-[#ff8806]/60">48h</strong>.
                        </p>

                        {/* 4. Tracking Search Box (Single Bar with Search Icon, Input & CTA) */}
                        <div className="space-y-2.5 max-w-xl">
                            <form onSubmit={handleSubmit} className="w-full">
                                <div className="relative flex items-center bg-[#072432]/95 backdrop-blur-md border border-white/20 rounded-2xl p-1.5 shadow-2xl focus-within:border-[#ff8806] focus-within:ring-2 focus-within:ring-[#ff8806]/30 transition-all">
                                    <Search className="w-5 h-5 ml-3 text-[#ff8806] shrink-0" />
                                    <input
                                        type="text"
                                        value={trackingInput}
                                        onChange={(e) => setTrackingInput(e.target.value)}
                                        placeholder="Entrez votre N° de suivi (ex: OUA-782941)..."
                                        className="w-full pl-3 pr-2 py-2.5 text-sm font-medium text-white placeholder:text-slate-400 bg-transparent border-none focus:outline-none focus:ring-0"
                                    />
                                    <Button 
                                        type="submit" 
                                        variant="secondary" 
                                        size="md" 
                                        className="shrink-0 font-bold shadow-lg shadow-[#ff8806]/40 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 px-6 py-2.5 rounded-xl cursor-pointer text-sm"
                                    >
                                        <span>Suivre mon colis</span>
                                        <ArrowRight className="w-4 h-4 ml-1.5" />
                                    </Button>
                                </div>
                            </form>

                            {/* Trust Badge below Search */}
                            <p className="text-xs text-slate-300 flex items-center gap-2 pl-1">
                                <ShieldCheck className="w-4 h-4 text-emerald-400 shrink-0" />
                                <span>Traçabilité en temps réel certifiée OUAISS EXPRESS</span>
                            </p>
                        </div>

                        {/* 5. Action Buttons (CTAs) */}
                        <div className="pt-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4 max-w-xl">
                            <Link href="/register">
                                <Button 
                                    variant="secondary" 
                                    size="lg" 
                                    className="w-full sm:w-auto font-extrabold shadow-xl shadow-[#ff8806]/40 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 px-8 py-3.5 text-sm cursor-pointer rounded-2xl"
                                >
                                    <span>Ouvrir un Compte Marchand</span>
                                    <ArrowRight className="w-4 h-4 ml-2" />
                                </Button>
                            </Link>
                            <a href="#calculateur">
                                <Button 
                                    variant="outline" 
                                    size="lg" 
                                    className="w-full sm:w-auto text-white bg-[#05161f]/80 backdrop-blur-md border border-white/20 hover:bg-white/15 hover:text-white px-7 py-3.5 text-sm cursor-pointer shadow-lg rounded-2xl"
                                >
                                    <span>Simuler les Tarifs</span>
                                </Button>
                            </a>
                        </div>

                    </div>

                    {/* ======================================================== */}
                    {/* RIGHT SECTION (col-span-12 lg:col-span-5)                */}
                    {/* Unobstructed viewport letting the Van & Morocco map shine*/}
                    {/* ======================================================== */}
                    <div className="lg:col-span-5 hidden lg:block">
                        {/* Intentionally transparent to let the high-res Peugeot vehicle & illuminated network map be 100% visible */}
                    </div>

                </div>
            </div>

            {/* Subtle Scroll Down Indicator */}
            <div className="relative z-10 pt-2 pb-1">
                <a 
                    href="#services" 
                    className="group inline-flex flex-col items-center gap-1 text-slate-300 hover:text-white transition-colors text-[11px] font-medium opacity-85 hover:opacity-100"
                >
                    <span className="tracking-wide">Découvrir nos solutions</span>
                    <ChevronDown className="w-4 h-4 text-[#ff8806] animate-bounce" />
                </a>
            </div>
        </section>
    );
}
