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
    Sparkles,
    CheckCircle2,
    Check,
    PhoneCall,
    PackageCheck
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
        <section className="relative pt-32 pb-20 lg:pt-36 lg:pb-28 overflow-hidden bg-gradient-to-br from-[#05161f] via-[#072432] to-[#004c68] text-white">
            {/* Background Texture & Decorative Shapes from Envato Template */}
            <div 
                className="absolute inset-0 z-0 opacity-25 mix-blend-screen pointer-events-none bg-cover bg-center"
                style={{ backgroundImage: `url('/images/landing/thumbs/banner-three-img.png')` }}
            />
            
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

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                    
                    {/* Left Column: Value Proposition & Direct Tracking */}
                    <div className="lg:col-span-7 space-y-6 text-center lg:text-left">
                        {/* Innovation Pill */}
                        <div className="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs font-semibold text-white shadow-xs">
                            <span className="flex h-2.5 w-2.5 rounded-full bg-[#ff8806] animate-ping" />
                            <span className="text-[#ff8806] font-bold">NOUVEAU 2026</span>
                            <span className="text-white/40">|</span>
                            <span>Réseau Logistique & Livraison Express Maroc</span>
                        </div>

                        {/* Main Title Inspired by Envato Banner Typography */}
                        <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black font-sans tracking-tight leading-[1.12]">
                            La Logistique Express{' '}
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#ff8806] via-[#ffa733] to-[#ffc066]">
                                Intelligente & Rapide
                            </span>{' '}
                            pour votre E-commerce.
                        </h1>

                        {/* Subtitle */}
                        <p className="text-base sm:text-lg text-slate-300 max-w-2xl leading-relaxed mx-auto lg:mx-0">
                            Propulsez vos ventes au Maroc avec un réseau national qui livre vos colis sous <strong>24/48h</strong>, garantit vos reversements <strong>CRBT à J+2</strong> sans retard et confirme les adresses par WhatsApp pour éliminer les retours.
                        </p>

                        {/* Interactive Hero Tracking Input (Envato Style Track Order Box) */}
                        <div className="pt-2 max-w-xl mx-auto lg:mx-0">
                            <form 
                                onSubmit={handleSubmit}
                                className="p-2.5 bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 flex flex-col sm:flex-row gap-2 transition-all focus-within:border-[#ff8806] focus-within:ring-4 focus-within:ring-[#ff8806]/20"
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
                                    className="sm:w-auto w-full font-bold shadow-lg shadow-[#ff8806]/30 bg-[#ff8806] hover:bg-[#e07400] text-white border-0"
                                >
                                    <span>Suivre mon colis</span>
                                    <ArrowRight className="w-4 h-4 ml-1.5" />
                                </Button>
                            </form>
                            <p className="text-[11px] text-slate-400 mt-2 text-center lg:text-left flex items-center justify-center lg:justify-start gap-1.5">
                                <ShieldCheck className="w-4 h-4 text-emerald-400" />
                                <span>Traçabilité satellite horodatée certifiée OUAISS EXPRESS</span>
                            </p>
                        </div>

                        {/* CTAs */}
                        <div className="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <Link href="/register">
                                <Button 
                                    variant="secondary" 
                                    size="lg" 
                                    className="w-full sm:w-auto font-extrabold shadow-xl shadow-[#ff8806]/30 bg-[#ff8806] hover:bg-[#e07400] text-white border-0 px-7"
                                >
                                    <span>Ouvrir un Compte Marchand</span>
                                    <ArrowRight className="w-4 h-4 ml-2" />
                                </Button>
                            </Link>
                            <a href="#calculateur">
                                <Button 
                                    variant="outline" 
                                    size="lg" 
                                    className="w-full sm:w-auto text-white border-white/30 hover:bg-white/10 hover:text-white"
                                >
                                    <span>Simuler les Tarifs</span>
                                </Button>
                            </a>
                        </div>

                        {/* Key Guarantees Badges (Envato Grid Highlights) */}
                        <div className="pt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 text-left">
                            <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-[#ff8806]/40 transition-colors">
                                <Clock className="w-4 h-4 text-[#ff8806] mb-1.5" />
                                <div className="text-xs font-bold text-white">24h / 48h</div>
                                <div className="text-[10.5px] text-slate-400">Délais garantis</div>
                            </div>
                            <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-emerald-400/40 transition-colors">
                                <Banknote className="w-4 h-4 text-emerald-400 mb-1.5" />
                                <div className="text-xs font-bold text-white">J+2 Garanti</div>
                                <div className="text-[10.5px] text-slate-400">Virement CRBT rapide</div>
                            </div>
                            <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-[#ff8806]/40 transition-colors">
                                <Truck className="w-4 h-4 text-[#ff8806] mb-1.5" />
                                <div className="text-xs font-bold text-white">0 DH</div>
                                <div className="text-[10.5px] text-slate-400">Ramassage offert</div>
                            </div>
                            <div className="p-3.5 bg-white/5 backdrop-blur-sm rounded-2xl border border-white/10 shadow-xs hover:border-[#004c68]/40 transition-colors">
                                <MapPin className="w-4 h-4 text-cyan-400 mb-1.5" />
                                <div className="text-xs font-bold text-white">1 300+ Villes</div>
                                <div className="text-[10.5px] text-slate-400">Couverture nationale</div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Hero Visual composition inspired by Envato Banner-Three */}
                    <div className="lg:col-span-5 relative">
                        <div className="relative mx-auto max-w-md lg:max-w-none">
                            {/* Main Visual Card with Hub status and Fleet Preview */}
                            <div className="relative rounded-3xl bg-gradient-to-br from-[#004c68]/90 via-[#072432]/95 to-[#05161f] p-7 text-white shadow-2xl shadow-black/50 border border-white/15 overflow-hidden">
                                
                                {/* Header of the Hero Card */}
                                <div className="flex items-center justify-between pb-5 border-b border-white/10">
                                    <div className="flex items-center gap-3">
                                        <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-[#ff8806] to-[#e07400] flex items-center justify-center font-black text-white shadow-md">
                                            <PackageCheck className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h4 className="font-bold text-sm tracking-wide">OUAISS EXPRESS</h4>
                                            <span className="text-[10.5px] text-slate-300">Hub National Casablanca</span>
                                        </div>
                                    </div>
                                    <Badge variant="secondary" size="sm" dot={true}>
                                        Opérationnel
                                    </Badge>
                                </div>

                                {/* Logistics Vehicle / Fleet Showcase (Envato Track Visual) */}
                                <div className="mt-5 relative h-48 w-full rounded-2xl overflow-hidden border border-white/10 shadow-lg group bg-[#004c68]/30">
                                    <img 
                                        src="/images/landing/thumbs/banner-three-img.png" 
                                        alt="Réseau OUAISS EXPRESS Maroc" 
                                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-[#05161f] via-transparent to-black/20" />
                                    
                                    {/* Truck Overlay Accent */}
                                    <img 
                                        src="/images/landing/thumbs/only-track.png" 
                                        alt="Camionnette de livraison" 
                                        className="absolute -bottom-2 -left-2 w-48 object-contain drop-shadow-2xl" 
                                    />

                                    <div className="absolute bottom-3 right-3 text-right">
                                        <span className="text-[10.5px] bg-black/60 backdrop-blur-md px-3 py-1 rounded-full font-bold border border-white/15 text-[#ff8806] inline-block">
                                            Flotte & Départs Quotidiens
                                        </span>
                                    </div>
                                </div>

                                {/* Live Operational Metric Cards */}
                                <div className="py-4 space-y-3.5">
                                    <div className="bg-[#05161f]/80 p-4 rounded-2xl border border-white/10 shadow-inner">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs text-slate-300 font-medium">Colis en cours de livraison</span>
                                            <span className="text-xs font-bold text-emerald-400">+14% vs hier</span>
                                        </div>
                                        <div className="text-3xl font-black mt-1 text-white font-sans tracking-tight">
                                            4 180 <span className="text-xs font-normal text-slate-400">expéditions actives</span>
                                        </div>
                                        <div className="w-full bg-slate-800 h-2 rounded-full mt-3 overflow-hidden">
                                            <div className="bg-gradient-to-r from-[#ff8806] to-emerald-400 h-full rounded-full w-[92%]" />
                                        </div>
                                        <div className="flex justify-between text-[10.5px] text-slate-400 mt-1.5">
                                            <span>Hub Casablanca & Régions</span>
                                            <span className="text-emerald-400 font-semibold">Taux de succès : 99.4%</span>
                                        </div>
                                    </div>

                                    {/* Live Notification Toast Mock */}
                                    <div className="bg-white/10 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 flex items-center gap-3">
                                        <div className="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0">
                                            <CheckCircle2 className="w-5 h-5" />
                                        </div>
                                        <div className="text-xs flex-1">
                                            <div className="font-bold text-white">Virement CRBT exécuté</div>
                                            <div className="text-slate-300 text-[11px]">12 450 MAD virés à Boutique Chic Maroc</div>
                                        </div>
                                        <span className="text-[10px] text-slate-400 ml-auto font-medium">Il y a 2m</span>
                                    </div>
                                </div>

                                {/* Footer Trust */}
                                <div className="pt-3.5 border-t border-white/10 flex items-center justify-between text-xs text-slate-400">
                                    <span>Partenaire officiel des e-merchants</span>
                                    <span className="text-[#ff8806] font-bold">Support Dédié 7j/7</span>
                                </div>
                            </div>

                            {/* Floating WhatsApp Card Badge */}
                            <div className="absolute -bottom-6 -left-6 bg-white text-slate-900 p-4 rounded-2xl shadow-2xl border border-slate-100 hidden sm:flex items-center gap-3.5 animate-in slide-in-from-bottom-2 duration-300">
                                <div className="w-11 h-11 rounded-xl bg-[#ff8806]/15 text-[#ff8806] flex items-center justify-center flex-shrink-0 font-bold">
                                    <Sparkles className="w-6 h-6" />
                                </div>
                                <div>
                                    <div className="text-xs font-bold text-slate-900">Confirmation WhatsApp</div>
                                    <div className="text-[11px] text-slate-500 font-medium">-32% d'annulations clients</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    );
}
