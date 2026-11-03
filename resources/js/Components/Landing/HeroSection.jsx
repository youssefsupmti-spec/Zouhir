import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import Button from '@/components/ui/AppButton';
import Badge from '@/components/ui/AppBadge';
import { 
    Search, 
    ArrowRight, 
    ShieldCheck, 
    Clock, 
    Truck, 
    Banknote, 
    MapPin, 
    CheckCircle2,
    PhoneCall
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
        <section className="relative pt-36 sm:pt-44 lg:pt-48 pb-20 lg:pb-28 overflow-hidden bg-gradient-to-br from-[#05161f] via-[#072432] to-[#004c68] text-white">
            {/* Ambient Background Glowing Orbs */}
            <div className="absolute top-10 right-10 w-[500px] h-[500px] bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute bottom-10 left-10 w-[600px] h-[600px] bg-[#004c68]/40 rounded-full blur-3xl pointer-events-none" />

            {/* Road Curve Shape (Envato Theme Element) */}
            <img 
                src="/images/landing/shapes/curve-road.png" 
                alt="" 
                className="hidden lg:block absolute bottom-0 right-0 w-80 opacity-40 pointer-events-none z-0" 
            />

            {/* Flying Plane Shape (Envato Theme Element) */}
            <img 
                src="/images/landing/shapes/banner--three-plane.png" 
                alt="" 
                className="hidden md:block absolute top-28 right-16 w-24 opacity-60 pointer-events-none animate-pulse z-0" 
            />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-7">
                
                {/* 1. Innovation Pill / Badge */}
                <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs font-semibold text-white shadow-xs">
                    <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-pulse" />
                    <span className="text-[#ff8806] font-bold">N°1 DE LA LIVRAISON E-COMMERCE AU MAROC</span>
                    <span className="text-white/30">•</span>
                    <span className="text-slate-200">Partout au Maroc en 24–48h</span>
                </div>

                {/* 2. Main Title (Inspired by top Moroccan logistics leaders: Short, Punchy, ROI-driven) */}
                <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black font-sans tracking-tight leading-[1.14] max-w-4xl mx-auto">
                    Livrez plus vite,{' '}
                    <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#ff8806] via-[#ffa733] to-[#ffc066]">
                        vendez partout
                    </span>{' '}
                    au Maroc.
                </h1>

                {/* 3. Short & Professional Subtitle */}
                <p className="text-base sm:text-lg text-slate-300 max-w-2xl leading-relaxed mx-auto font-normal">
                    La solution de livraison e-commerce la plus fiable du Royaume. Ramassage offert, expédition express en <strong>24–48h</strong> et reversement garanti de vos fonds sous <strong>48h</strong>.
                </p>

                {/* 4. Interactive Hero Tracking Input */}
                <div className="pt-1 max-w-xl mx-auto">
                    <form 
                        onSubmit={handleSubmit}
                        className="p-2 bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 flex flex-col sm:flex-row gap-2 transition-all focus-within:border-[#ff8806] focus-within:ring-4 focus-within:ring-[#ff8806]/20"
                    >
                        <div className="relative flex-1 flex items-center">
                            <Search className="w-5 h-5 absolute left-3.5 text-[#ff8806]" />
                            <input
                                type="text"
                                value={trackingInput}
                                onChange={(e) => setTrackingInput(e.target.value)}
                                placeholder="Entrez votre N° de suivi (ex: OUA-782941)..."
                                className="w-full pl-11 pr-3 py-3 text-sm font-semibold text-white placeholder:text-slate-400 focus:outline-none bg-transparent"
                            />
                        </div>
                        <Button 
                            type="submit" 
                            variant="secondary" 
                            size="md" 
                            className="sm:w-auto w-full font-bold shadow-lg shadow-[#ff8806]/30 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 px-6 py-3"
                        >
                            <span>Suivre mon colis</span>
                            <ArrowRight className="w-4 h-4 ml-1.5" />
                        </Button>
                    </form>
                    <p className="text-[11.5px] text-slate-400 mt-2 text-center flex items-center justify-center gap-1.5">
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
                            className="w-full sm:w-auto font-extrabold shadow-xl shadow-[#ff8806]/30 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 px-8 py-3.5 text-sm"
                        >
                            <span>Ouvrir un Compte Marchand</span>
                            <ArrowRight className="w-4 h-4 ml-2" />
                        </Button>
                    </Link>
                    <a href="#calculateur">
                        <Button 
                            variant="outline" 
                            size="lg" 
                            className="w-full sm:w-auto text-white border-white/30 hover:bg-white/10 hover:text-white px-7 py-3.5 text-sm"
                        >
                            <span>Simuler les Tarifs</span>
                        </Button>
                    </a>
                </div>

                {/* 6. Key Guarantees Badges (4 Pillars) */}
                <div className="pt-4 grid grid-cols-2 sm:grid-cols-4 gap-3.5 max-w-3xl mx-auto text-left">
                    <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-[#ff8806]/40 transition-colors">
                        <Truck className="w-4 h-4 text-[#ff8806] mb-1.5" />
                        <div className="text-xs font-bold text-white">0 DH</div>
                        <div className="text-[10.5px] text-slate-400">Ramassage offert</div>
                    </div>
                    <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-[#ff8806]/40 transition-colors">
                        <Clock className="w-4 h-4 text-[#ff8806] mb-1.5" />
                        <div className="text-xs font-bold text-white">24h / 48h</div>
                        <div className="text-[10.5px] text-slate-400">Livraison express</div>
                    </div>
                    <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-emerald-400/40 transition-colors">
                        <Banknote className="w-4 h-4 text-emerald-400 mb-1.5" />
                        <div className="text-xs font-bold text-white">Sous 48h</div>
                        <div className="text-[10.5px] text-slate-400">Retour de fonds (CRBT)</div>
                    </div>
                    <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-cyan-400/40 transition-colors">
                        <MapPin className="w-4 h-4 text-cyan-400 mb-1.5" />
                        <div className="text-xs font-bold text-white">1 300+ Villes</div>
                        <div className="text-[10.5px] text-slate-400">Partout au Maroc</div>
                    </div>
                </div>

                {/* 7. Clean Official OUAISS EXPRESS Vehicle Banner Showcase */}
                <div className="pt-6 max-w-4xl mx-auto">
                    <div className="relative rounded-3xl overflow-hidden shadow-2xl border border-white/15 group bg-[#004c68]/20">
                        <img 
                            src="/images/landing/thumbs/banner-three-img.png" 
                            alt="OUAISS EXPRESS - Partout au Maroc, plus proche de vous !" 
                            className="w-full h-auto object-cover transition-transform duration-700 group-hover:scale-102"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-[#05161f]/70 via-transparent to-transparent pointer-events-none" />
                        <div className="absolute bottom-4 right-4 z-10">
                            <span className="text-[11px] bg-black/70 backdrop-blur-md px-3.5 py-1.5 rounded-full font-bold border border-white/20 text-[#ff8806] inline-flex items-center gap-1.5 shadow-md">
                                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                                Flotte Nationale & Départs Quotidiens
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    );
}
