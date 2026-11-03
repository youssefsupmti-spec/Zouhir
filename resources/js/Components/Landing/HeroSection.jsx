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
    MapPin
} from 'lucide-react';

export default function HeroSection({ onTrackSearch }) {
    const [trackingInput, setTrackingInput] = useState('');

    const handleSubmit = (e) => {
        e.preventDefault();
        if (trackingInput.trim()) {
            onTrackSearch(trackingInput.trim());
        }
    };

    return (
        <section className="relative min-h-[90vh] pt-36 sm:pt-40 lg:pt-44 pb-16 lg:pb-24 overflow-hidden bg-[#05161f] text-white flex items-center justify-center">
            {/* Background Hero Image HD (/images/image.png) with Envato Index-3 Style Overlay */}
            <div 
                className="absolute inset-0 z-0 bg-cover bg-center bg-no-repeat transition-all duration-700"
                style={{ backgroundImage: `url('/images/image.png')` }}
            >
                {/* Lighter Cinematic Gradient Overlay to let the official vehicle & Morocco map shine while keeping text crystal clear */}
                <div className="absolute inset-0 bg-gradient-to-b from-[#05161f]/70 via-[#072432]/45 to-[#05161f]/80" />
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

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-6">
                
                {/* 1. Innovation Pill / Badge */}
                <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#05161f]/80 backdrop-blur-md border border-white/20 text-xs font-semibold text-white shadow-lg">
                    <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-pulse" />
                    <span className="text-[#ff8806] font-bold">N°1 DE LA LIVRAISON E-COMMERCE AU MAROC</span>
                    <span className="text-white/40">•</span>
                    <span className="text-slate-200">Partout au Maroc en 24–48h</span>
                </div>

                {/* 2. Main Title (Punchy, High Contrast with Drop Shadow) */}
                <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black font-sans tracking-tight leading-[1.12] max-w-4xl mx-auto text-white drop-shadow-[0_4px_16px_rgba(0,0,0,0.85)]">
                    Livrez plus vite,{' '}
                    <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#ff8806] via-[#ffa733] to-[#ffc066] drop-shadow-[0_2px_10px_rgba(255,136,6,0.5)]">
                        vendez partout
                    </span>{' '}
                    au Maroc.
                </h1>

                {/* 3. Short & Professional Subtitle */}
                <p className="text-base sm:text-lg text-slate-100 max-w-2xl leading-relaxed mx-auto font-medium drop-shadow-[0_2px_8px_rgba(0,0,0,0.8)]">
                    La solution de livraison e-commerce la plus fiable du Royaume. Ramassage offert, expédition express en <strong className="text-white font-bold underline decoration-[#ff8806]/60">24–48h</strong> et reversement garanti de vos fonds sous <strong className="text-white font-bold underline decoration-[#ff8806]/60">48h</strong>.
                </p>

                {/* 4. Interactive Hero Tracking Input */}
                <div className="pt-2 max-w-xl mx-auto">
                    <form 
                        onSubmit={handleSubmit}
                        className="p-2 bg-[#05161f]/85 backdrop-blur-md rounded-2xl shadow-2xl border border-white/25 flex flex-col sm:flex-row gap-2 transition-all focus-within:border-[#ff8806] focus-within:ring-4 focus-within:ring-[#ff8806]/30"
                    >
                        <div className="relative flex-1 flex items-center">
                            <Search className="w-5 h-5 absolute left-3.5 text-[#ff8806]" />
                            <input
                                type="text"
                                value={trackingInput}
                                onChange={(e) => setTrackingInput(e.target.value)}
                                placeholder="Entrez votre N° de suivi (ex: OUA-782941)..."
                                className="w-full pl-11 pr-3 py-3 text-sm font-semibold text-white placeholder:text-slate-300 focus:outline-none bg-transparent"
                            />
                        </div>
                        <Button 
                            type="submit" 
                            variant="secondary" 
                            size="md" 
                            className="sm:w-auto w-full font-bold shadow-lg shadow-[#ff8806]/40 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 px-6 py-3 cursor-pointer"
                        >
                            <span>Suivre mon colis</span>
                            <ArrowRight className="w-4 h-4 ml-1.5" />
                        </Button>
                    </form>
                    <p className="text-[11.5px] text-slate-200 mt-2 text-center flex items-center justify-center gap-1.5 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                        <ShieldCheck className="w-4 h-4 text-emerald-400" />
                        <span>Traçabilité en temps réel certifiée OUAISS EXPRESS</span>
                    </p>
                </div>

                {/* 5. CTAs */}
                <div className="pt-2 flex flex-col sm:flex-row items-center justify-center gap-4">
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
                            className="w-full sm:w-auto text-white bg-[#05161f]/60 backdrop-blur-md border-white/30 hover:bg-white/15 hover:text-white px-7 py-3.5 text-sm cursor-pointer shadow-lg"
                        >
                            <span>Simuler les Tarifs</span>
                        </Button>
                    </a>
                </div>

                {/* 6. Key Guarantees Badges (4 Pillars) */}
                <div className="pt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl mx-auto text-left">
                    <div className="p-3 bg-[#05161f]/75 backdrop-blur-md rounded-2xl border border-white/20 shadow-lg hover:border-[#ff8806]/60 transition-colors">
                        <Truck className="w-4 h-4 text-[#ff8806] mb-1" />
                        <div className="text-xs font-bold text-white">0 DH</div>
                        <div className="text-[10.5px] text-slate-300">Ramassage offert</div>
                    </div>
                    <div className="p-3 bg-[#05161f]/75 backdrop-blur-md rounded-2xl border border-white/20 shadow-lg hover:border-[#ff8806]/60 transition-colors">
                        <Clock className="w-4 h-4 text-[#ff8806] mb-1" />
                        <div className="text-xs font-bold text-white">24h / 48h</div>
                        <div className="text-[10.5px] text-slate-300">Livraison express</div>
                    </div>
                    <div className="p-3 bg-[#05161f]/75 backdrop-blur-md rounded-2xl border border-white/20 shadow-lg hover:border-emerald-400/60 transition-colors">
                        <Banknote className="w-4 h-4 text-emerald-400 mb-1" />
                        <div className="text-xs font-bold text-white">Sous 48h</div>
                        <div className="text-[10.5px] text-slate-300">Retour de fonds (CRBT)</div>
                    </div>
                    <div className="p-3 bg-[#05161f]/75 backdrop-blur-md rounded-2xl border border-white/20 shadow-lg hover:border-cyan-400/60 transition-colors">
                        <MapPin className="w-4 h-4 text-cyan-400 mb-1" />
                        <div className="text-xs font-bold text-white">1 300+ Villes</div>
                        <div className="text-[10.5px] text-slate-300">Partout au Maroc</div>
                    </div>
                </div>

            </div>
        </section>
    );
}
