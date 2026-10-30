import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import { 
    Search, 
    ArrowRight, 
    ShieldCheck, 
    Clock, 
    Truck, 
    Banknote, 
    MapPin, 
    Sparkles,
    CheckCircle2
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
        <section className="relative pt-32 pb-20 lg:pt-36 lg:pb-28 overflow-hidden bg-gradient-to-b from-[#e6f1f5]/60 via-white to-slate-50">
            {/* Ambient Background Blur Rings */}
            <div className="absolute top-10 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-tr from-[#004c68]/10 via-[#ff8806]/10 to-transparent rounded-full blur-3xl pointer-events-none" />
            <div className="absolute top-20 right-10 w-72 h-72 bg-[#ff8806]/10 rounded-full blur-2xl pointer-events-none" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                    {/* Left Column: Value Proposition & Direct Tracking */}
                    <div className="lg:col-span-7 space-y-6 text-center lg:text-left">
                        {/* Innovation Pill */}
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white border border-[#004c68]/15 shadow-xs text-xs font-semibold text-[#004c68]">
                            <span className="flex h-2 w-2 rounded-full bg-[#ff8806] animate-pulse" />
                            <span>Plateforme Logistique Express Maroc 2026</span>
                            <span className="text-slate-300">|</span>
                            <span className="text-slate-500 font-normal">Zouhir & Partenaires</span>
                        </div>

                        {/* Main Title */}
                        <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-[#004c68] font-['Outfit'] tracking-tight leading-[1.12]">
                            La Livraison Express{' '}
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#004c68] via-[#0d6386] to-[#ff8806]">
                                Sans Faux Pas
                            </span>{' '}
                            pour votre E-commerce.
                        </h1>

                        {/* Subtitle */}
                        <p className="text-base sm:text-lg text-slate-600 max-w-2xl leading-relaxed mx-auto lg:mx-0">
                            Propulsez vos ventes au Maroc avec un partenaire qui livre sous 24/48h, reverse votre <strong>cash CRBT à J+2</strong> sans retard et confirme les adresses par WhatsApp pour éliminer les retours.
                        </p>

                        {/* Interactive Hero Tracking Input */}
                        <div className="pt-2 max-w-lg mx-auto lg:mx-0">
                            <form 
                                onSubmit={handleSubmit}
                                className="p-2 bg-white rounded-2xl shadow-xl shadow-[#004c68]/10 border border-slate-200/90 flex flex-col sm:flex-row gap-2 transition-all focus-within:border-[#004c68] focus-within:ring-4 focus-within:ring-[#004c68]/10"
                            >
                                <div className="relative flex-1 flex items-center">
                                    <Search className="w-5 h-5 absolute left-3.5 text-[#ff8806]" />
                                    <input
                                        type="text"
                                        value={trackingInput}
                                        onChange={(e) => setTrackingInput(e.target.value)}
                                        placeholder="Entrez votre N° de suivi (ex: OUA-782941)..."
                                        className="w-full pl-11 pr-3 py-3 text-sm font-semibold text-slate-800 placeholder:text-slate-400 focus:outline-none bg-transparent"
                                    />
                                </div>
                                <Button type="submit" variant="primary" size="md" className="sm:w-auto w-full">
                                    <span>Suivre mon colis</span>
                                    <ArrowRight className="w-4 h-4 ml-1.5" />
                                </Button>
                            </form>
                            <p className="text-[11px] text-slate-400 mt-2 text-center lg:text-left flex items-center justify-center lg:justify-start gap-1">
                                <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
                                <span>Suivi satellite temps réel certifié Ouais Express</span>
                            </p>
                        </div>

                        {/* CTAs */}
                        <div className="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3.5">
                            <Link href="/register">
                                <Button variant="secondary" size="lg" className="w-full sm:w-auto font-bold shadow-lg shadow-[#ff8806]/20">
                                    <span>Ouvrir un Compte Gratuit</span>
                                    <ArrowRight className="w-4 h-4 ml-2" />
                                </Button>
                            </Link>
                            <a href="#calculateur">
                                <Button variant="outline" size="lg" className="w-full sm:w-auto">
                                    <span>Simuler les Tarifs</span>
                                </Button>
                            </a>
                        </div>

                        {/* Key Guarantees Badges */}
                        <div className="pt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 text-left">
                            <div className="p-3 bg-white/80 rounded-xl border border-slate-200/60 shadow-xs">
                                <Clock className="w-4 h-4 text-[#ff8806] mb-1" />
                                <div className="text-xs font-bold text-slate-900">24h / 48h</div>
                                <div className="text-[10px] text-slate-500">Livraison garantie</div>
                            </div>
                            <div className="p-3 bg-white/80 rounded-xl border border-slate-200/60 shadow-xs">
                                <Banknote className="w-4 h-4 text-emerald-600 mb-1" />
                                <div className="text-xs font-bold text-slate-900">J+2 Garanti</div>
                                <div className="text-[10px] text-slate-500">Virement CRBT rapide</div>
                            </div>
                            <div className="p-3 bg-white/80 rounded-xl border border-slate-200/60 shadow-xs">
                                <Truck className="w-4 h-4 text-[#004c68] mb-1" />
                                <div className="text-xs font-bold text-slate-900">0 MAD</div>
                                <div className="text-[10px] text-slate-500">Ramassage offert</div>
                            </div>
                            <div className="p-3 bg-white/80 rounded-xl border border-slate-200/60 shadow-xs">
                                <MapPin className="w-4 h-4 text-[#004c68] mb-1" />
                                <div className="text-xs font-bold text-slate-900">58+ Villes</div>
                                <div className="text-[10px] text-slate-500">Réseau national</div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Dynamic Corporate Card Showcase */}
                    <div className="lg:col-span-5 relative">
                        <div className="relative mx-auto max-w-md lg:max-w-none">
                            {/* Main Visual Card */}
                            <div className="relative rounded-3xl bg-gradient-to-br from-[#004c68] to-[#05161f] p-7 text-white shadow-2xl shadow-[#004c68]/30 border border-[#1a4961]/80 overflow-hidden">
                                {/* Decorative badge */}
                                <div className="flex items-center justify-between pb-6 border-b border-slate-700/60">
                                    <div className="flex items-center gap-2.5">
                                        <div className="w-10 h-10 rounded-xl bg-[#ff8806] flex items-center justify-center font-black text-white font-['Outfit'] text-lg shadow-md">
                                            OE
                                        </div>
                                        <div>
                                            <h4 className="font-bold text-sm tracking-wide">OUAISS EXPRESS</h4>
                                            <span className="text-[10px] text-slate-400">Réseau Certifié 2026</span>
                                        </div>
                                    </div>
                                    <Badge variant="secondary" size="sm" dot={true}>
                                        En direct
                                    </Badge>
                                </div>

                                {/* Hero Logistics Fleet Showcase */}
                                <div className="mt-4 relative h-44 w-full rounded-2xl overflow-hidden border border-slate-700/60 shadow-lg group">
                                    <img 
                                        src="/images/hero-delivery.webp" 
                                        alt="Flotte OUAISS EXPRESS Maroc" 
                                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        onError={(e) => {
                                            e.target.src = '/images/hero-delivery.jpg';
                                        }}
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-[#05161f] via-transparent to-black/30" />
                                    <div className="absolute bottom-2.5 left-3 right-3 flex items-center justify-between text-xs text-white">
                                        <span className="font-bold flex items-center gap-1.5 drop-shadow-md">
                                            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                                            Flotte & Hubs Dédiés
                                        </span>
                                        <span className="text-[10px] bg-black/60 backdrop-blur-sm px-2.5 py-0.5 rounded-full font-bold border border-white/15 text-[#ff8806]">
                                            Réseau Maroc
                                        </span>
                                    </div>
                                </div>

                                {/* Active Delivery Stats Counter */}
                                <div className="py-4 space-y-4">
                                    <div className="bg-[#0a2533] p-4 rounded-2xl border border-slate-700/50">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs text-slate-400">Colis en acheminement</span>
                                            <span className="text-xs font-bold text-emerald-400">+12% vs hier</span>
                                        </div>
                                        <div className="text-3xl font-extrabold mt-1 text-white font-['Outfit']">
                                            3 842 <span className="text-xs font-normal text-slate-400">colis</span>
                                        </div>
                                        <div className="w-full bg-slate-800 h-2 rounded-full mt-3 overflow-hidden">
                                            <div className="bg-gradient-to-r from-[#ff8806] to-emerald-400 h-full rounded-full w-[88%]" />
                                        </div>
                                        <div className="flex justify-between text-[10px] text-slate-400 mt-1">
                                            <span>Hub Casablanca</span>
                                            <span>Taux succès : 99.2%</span>
                                        </div>
                                    </div>

                                    {/* Live notification mock */}
                                    <div className="bg-white/10 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 flex items-center gap-3">
                                        <div className="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0">
                                            <CheckCircle2 className="w-5 h-5" />
                                        </div>
                                        <div className="text-xs">
                                            <div className="font-bold text-white">Virement CRBT exécuté</div>
                                            <div className="text-slate-300 text-[11px]">8 450 MAD versés à Boutique Moroccan Chic</div>
                                        </div>
                                        <span className="text-[10px] text-slate-400 ml-auto font-medium">Il y a 3m</span>
                                    </div>
                                </div>

                                {/* Footer Trust */}
                                <div className="pt-4 border-t border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                                    <span>Partenaire officiel e-merchants</span>
                                    <span className="text-[#ff8806] font-semibold">Support 7j/7</span>
                                </div>
                            </div>

                            {/* Floating Accent Card */}
                            <div className="absolute -bottom-6 -left-6 bg-white p-4 rounded-2xl shadow-xl border border-slate-100 hidden sm:flex items-center gap-3.5 animate-in slide-in-from-bottom-2 duration-300">
                                <div className="w-11 h-11 rounded-xl bg-[#ff8806]/15 text-[#ff8806] flex items-center justify-center flex-shrink-0 font-bold">
                                    <Sparkles className="w-6 h-6" />
                                </div>
                                <div>
                                    <div className="text-xs font-bold text-slate-900">WhatsApp Confirmation</div>
                                    <div className="text-[11px] text-slate-500">-30% d'annulations clients</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
