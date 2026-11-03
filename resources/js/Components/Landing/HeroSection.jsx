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

    // Fallback default statistics matching the company performance
    const statsList = statistics && statistics.length >= 3 ? statistics.slice(0, 3) : [
        { value: '+50 000', label: 'Colis / mois' },
        { value: '99.2%', label: 'Taux de succès' },
        { value: '58+', label: 'Villes & Hubs' }
    ];

    return (
        <section className="relative w-full min-h-screen min-h-[100dvh] pt-24 sm:pt-28 lg:pt-32 pb-8 sm:pb-10 lg:pb-12 overflow-hidden bg-[#05161f] text-white flex flex-col justify-between items-center">
            {/* Background Hero Image HD (/images/image.png) with Envato Index-3 Style Overlay */}
            <div 
                className="absolute inset-0 z-0 bg-cover bg-center bg-no-repeat transition-all duration-700"
                style={{ backgroundImage: `url('/images/image.png')` }}
            >
                {/* Lighter Cinematic Gradient Overlay to let the official vehicle & Morocco map shine while keeping text crystal clear */}
                <div className="absolute inset-0 bg-gradient-to-b from-[#05161f]/75 via-[#072432]/45 to-[#05161f]/85" />
                <div className="absolute inset-0 bg-radial from-transparent via-[#05161f]/20 to-[#05161f]/60" />
            </div>

            {/* Ambient Background Glowing Accents */}
            <div className="absolute top-12 right-12 w-96 h-96 bg-[#ff8806]/20 rounded-full blur-3xl pointer-events-none z-0" />
            <div className="absolute bottom-12 left-12 w-96 h-96 bg-[#004c68]/30 rounded-full blur-3xl pointer-events-none z-0" />

            {/* Flying Plane Shape (Envato Theme Element) */}
            <img 
                src="/images/landing/shapes/banner--three-plane.png" 
                alt="" 
                className="hidden md:block absolute top-28 right-16 w-24 opacity-60 pointer-events-none animate-pulse z-0" 
            />

            {/* Main Content Grid : 2 Columns (Left Section: Pitch & CTAs | Right Section: Tracking & Stats) */}
            <div className="my-auto w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                    
                    {/* ======================================================== */}
                    {/* LEFT SECTION (col-span-12 lg:col-span-7)                 */}
                    {/* ======================================================== */}
                    <div className="lg:col-span-7 text-left space-y-5 sm:space-y-6">
                        
                        {/* 1. Innovation Pill / Badge */}
                        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#05161f]/85 backdrop-blur-md border border-white/20 text-xs font-semibold text-white shadow-lg">
                            <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-pulse" />
                            <span className="text-[#ff8806] font-bold">N°1 DE LA LIVRAISON E-COMMERCE AU MAROC</span>
                            <span className="text-white/40">•</span>
                            <span className="text-slate-200">Partout au Maroc en 24–48h</span>
                        </div>

                        {/* 2. Main Title (High Contrast with Drop Shadow) */}
                        <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black font-sans tracking-tight leading-[1.12] text-white drop-shadow-[0_4px_16px_rgba(0,0,0,0.85)]">
                            Livrez plus vite,{' '}
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#ff8806] via-[#ffa733] to-[#ffc066] drop-shadow-[0_2px_10px_rgba(255,136,6,0.5)]">
                                vendez partout
                            </span>{' '}
                            au Maroc.
                        </h1>

                        {/* 3. Short & Professional Subtitle */}
                        <p className="text-sm sm:text-base lg:text-lg text-slate-100 max-w-xl leading-relaxed font-medium drop-shadow-[0_2px_8px_rgba(0,0,0,0.8)]">
                            La solution de livraison e-commerce la plus fiable du Royaume. Ramassage offert, expédition express en <strong className="text-white font-bold underline decoration-[#ff8806]/60">24–48h</strong> et reversement garanti de vos fonds sous <strong className="text-white font-bold underline decoration-[#ff8806]/60">48h</strong>.
                        </p>

                        {/* 4. Action Buttons (CTAs) */}
                        <div className="pt-1 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4">
                            <Link href="/register">
                                <Button 
                                    variant="secondary" 
                                    size="lg" 
                                    className="w-full sm:w-auto font-extrabold shadow-xl shadow-[#ff8806]/40 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 px-8 py-3.5 text-sm cursor-pointer"
                                >
                                    <span>Ouvrir un Compte Marchand</span>
                                    <ArrowRight className="w-4 h-4 ml-2" />
                                </Button>
                            </Link>
                            <a href="#calculateur">
                                <Button 
                                    variant="outline" 
                                    size="lg" 
                                    className="w-full sm:w-auto text-white bg-[#05161f]/70 backdrop-blur-md border-white/30 hover:bg-white/15 hover:text-white px-7 py-3.5 text-sm cursor-pointer shadow-lg"
                                >
                                    <span>Simuler les Tarifs</span>
                                </Button>
                            </a>
                        </div>

                        {/* 5. Key Guarantees Badges (4 Pillars in Compact Row) */}
                        <div className="pt-2 grid grid-cols-2 sm:grid-cols-4 gap-2.5 max-w-2xl text-left">
                            <div className="p-3 bg-[#05161f]/80 backdrop-blur-md rounded-2xl border border-white/20 shadow-md hover:border-[#ff8806]/60 transition-colors">
                                <Truck className="w-4 h-4 text-[#ff8806] mb-1" />
                                <div className="text-xs font-bold text-white">0 DH</div>
                                <div className="text-[10.5px] text-slate-300">Ramassage offert</div>
                            </div>
                            <div className="p-3 bg-[#05161f]/80 backdrop-blur-md rounded-2xl border border-white/20 shadow-md hover:border-[#ff8806]/60 transition-colors">
                                <Clock className="w-4 h-4 text-[#ff8806] mb-1" />
                                <div className="text-xs font-bold text-white">24h / 48h</div>
                                <div className="text-[10.5px] text-slate-300">Livraison express</div>
                            </div>
                            <div className="p-3 bg-[#05161f]/80 backdrop-blur-md rounded-2xl border border-white/20 shadow-md hover:border-emerald-400/60 transition-colors">
                                <Banknote className="w-4 h-4 text-emerald-400 mb-1" />
                                <div className="text-xs font-bold text-white">Sous 48h</div>
                                <div className="text-[10.5px] text-slate-300">Retour de fonds (CRBT)</div>
                            </div>
                            <div className="p-3 bg-[#05161f]/80 backdrop-blur-md rounded-2xl border border-white/20 shadow-md hover:border-cyan-400/60 transition-colors">
                                <MapPin className="w-4 h-4 text-cyan-400 mb-1" />
                                <div className="text-xs font-bold text-white">1 300+ Villes</div>
                                <div className="text-[10.5px] text-slate-300">Partout au Maroc</div>
                            </div>
                        </div>

                    </div>

                    {/* ======================================================== */}
                    {/* RIGHT SECTION (col-span-12 lg:col-span-5)                */}
                    {/* ======================================================== */}
                    <div className="lg:col-span-5 space-y-4">
                        
                        {/* Interactive Tracking Simulator Card */}
                        <div className="p-6 sm:p-7 rounded-3xl bg-[#05161f]/90 backdrop-blur-xl border border-white/20 shadow-2xl text-left space-y-4">
                            
                            <div className="flex items-center justify-between pb-3 border-b border-white/10">
                                <div>
                                    <h3 className="text-base sm:text-lg font-bold text-white tracking-tight flex items-center gap-2">
                                        <PackageCheck className="w-5 h-5 text-[#ff8806]" />
                                        <span>Suivez votre colis</span>
                                    </h3>
                                    <p className="text-xs text-slate-300 mt-0.5">
                                        Accédez au statut de livraison en temps réel
                                    </p>
                                </div>
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[10.5px] font-semibold">
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                                    En direct
                                </span>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-300 mb-1.5">
                                        Numéro de suivi (Tracking Code)
                                    </label>
                                    <div className="relative flex items-center">
                                        <Search className="w-5 h-5 absolute left-3.5 text-[#ff8806]" />
                                        <input
                                            type="text"
                                            value={trackingInput}
                                            onChange={(e) => setTrackingInput(e.target.value)}
                                            placeholder="Ex: OUA-782941..."
                                            className="w-full pl-11 pr-3 py-3 rounded-xl text-sm font-semibold text-white placeholder:text-slate-400 bg-white/10 border border-white/20 focus:border-[#ff8806] focus:ring-2 focus:ring-[#ff8806]/30 focus:outline-none transition-all"
                                        />
                                    </div>
                                </div>

                                <Button 
                                    type="submit" 
                                    variant="secondary" 
                                    size="md" 
                                    className="w-full font-bold shadow-lg shadow-[#ff8806]/40 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 py-3 cursor-pointer text-sm"
                                >
                                    <span>Suivre mon expédition</span>
                                    <ArrowRight className="w-4 h-4 ml-1.5" />
                                </Button>
                            </form>

                            <p className="text-[11.5px] text-slate-300 pt-1 flex items-center gap-1.5">
                                <ShieldCheck className="w-4 h-4 text-emerald-400 shrink-0" />
                                <span>Traçabilité certifiée OUAISS EXPRESS avec géolocalisation</span>
                            </p>
                        </div>

                        {/* Key Performance Indicators (3 Metrics under the Tracking Card) */}
                        <div className="grid grid-cols-3 gap-3">
                            {statsList.map((st, idx) => (
                                <div 
                                    key={idx}
                                    className="p-3.5 rounded-2xl bg-[#05161f]/80 backdrop-blur-md border border-white/15 text-center shadow-lg hover:border-[#ff8806]/50 transition-colors"
                                >
                                    <div className="text-xl sm:text-2xl font-black text-white font-sans tracking-tight">
                                        {st.value}
                                    </div>
                                    <div className="text-[10.5px] sm:text-xs text-slate-300 mt-0.5 font-medium leading-tight">
                                        {st.label}
                                    </div>
                                </div>
                            ))}
                        </div>

                    </div>

                </div>
            </div>

            {/* 7. Subtle Scroll Down Indicator */}
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
