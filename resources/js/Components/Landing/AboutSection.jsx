import React, { useState } from 'react';
import { 
    MapPin, 
    ShieldCheck, 
    Headphones, 
    CheckCircle2, 
    PhoneCall, 
    Play,
    Sparkles,
    ArrowRight
} from 'lucide-react';

export default function AboutSection() {
    const [videoModalOpen, setVideoModalOpen] = useState(false);

    const reasons = [
        {
            title: "Couverture Nationale",
            description: "Nous atteignons les zones éloignées et les grandes villes à travers le Maroc.",
            icon: MapPin,
            color: "text-[#ff8806]",
            bg: "bg-[#ff8806]/10",
        },
        {
            title: "Paiements PAD Fiables",
            description: "Transferts transparents et réguliers de vos fonds de paiement à la livraison.",
            icon: ShieldCheck,
            color: "text-[#004c68]",
            bg: "bg-[#004c68]/10",
        },
        {
            title: "Support Dédié",
            description: "Notre équipe de support est disponible par téléphone et WhatsApp pour vous assister.",
            icon: Headphones,
            color: "text-[#ff8806]",
            bg: "bg-[#ff8806]/10",
        },
    ];

    return (
        <section id="pourquoi-nous" className="py-24 bg-slate-50 relative overflow-hidden">
            {/* Background Shape Patterns from index-3 */}
            <div 
                className="absolute inset-0 opacity-[0.03] pointer-events-none bg-repeat"
                style={{ backgroundImage: `url('/images/landing/shapes/bg-pattern.png')` }}
            />
            <div className="absolute top-1/2 -left-32 w-80 h-80 rounded-full bg-[#004c68]/5 blur-3xl pointer-events-none" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-center">
                    
                    {/* Left Column: Visual Imagery from index-3 About Three Section */}
                    <div className="lg:col-span-5 relative">
                        <div className="relative mx-auto max-w-lg lg:max-w-none pb-12 sm:pb-8">
                            
                            {/* Main Moroccan Delivery Promo Image */}
                            <div className="relative rounded-3xl overflow-hidden shadow-2xl border-4 border-white bg-slate-900 group">
                                <img 
                                    src="/images/landing/إشهار توصيل مغربي من OUAISS EXPRESS.png" 
                                    alt="OUAISS EXPRESS - Partenaire Logistique au Maroc" 
                                    className="w-full h-[460px] sm:h-[500px] object-cover transition-transform duration-700 group-hover:scale-105"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-[#05161f]/85 via-[#05161f]/20 to-transparent" />
                                
                                {/* Bottom caption over image */}
                                <div className="absolute bottom-5 left-5 right-5 text-white">
                                    <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#ff8806] text-white text-[10px] font-black uppercase tracking-wider mb-2 shadow-sm">
                                        <Sparkles className="w-3 h-3" />
                                        <span>Logistique d'Élite</span>
                                    </div>
                                    <h4 className="font-extrabold text-base sm:text-lg leading-snug text-white drop-shadow-md">
                                        Modern transport system & secure packaging
                                    </h4>
                                    <p className="text-xs text-slate-200 mt-1 line-clamp-2">
                                        Chaque colis acheminé avec soin, rapidité et transparence absolue.
                                    </p>
                                </div>
                            </div>

                            {/* Secondary Inset Image with Video Play Button (Envato Style) */}
                            <div className="hidden sm:block absolute -bottom-6 -left-6 w-48 h-40 rounded-2xl overflow-hidden border-4 border-white shadow-2xl bg-white z-20 group/play">
                                <img 
                                    src="/images/landing/thumbs/about-three-img2.png" 
                                    alt="Centre de tri & préparation" 
                                    className="w-full h-full object-cover transition-transform duration-500 group-hover/play:scale-105"
                                />
                                <div className="absolute inset-0 bg-black/30 flex items-center justify-center">
                                    <button 
                                        type="button"
                                        onClick={() => setVideoModalOpen(true)}
                                        aria-label="Voir la vidéo de présentation"
                                        className="play-button-pulse w-14 h-14 rounded-full bg-[#ff8806] hover:bg-[#e07400] text-white flex items-center justify-center shadow-lg transition-transform hover:scale-110 cursor-pointer"
                                    >
                                        <Play className="w-6 h-6 fill-white ml-1" />
                                    </button>
                                </div>
                            </div>

                            {/* Vertical Writing-Mode Badge from index-3 */}
                            <div className="hidden md:flex absolute -right-5 top-1/2 -translate-y-1/2 z-20 shadow-2xl rounded-2xl overflow-hidden border-2 border-white">
                                <div className="bg-[#ff8806] px-2.5 py-6 flex items-center justify-center">
                                    <span className="writing-mode text-xs font-black uppercase tracking-wider text-white">
                                        100% FIABLE & RAPIDE
                                    </span>
                                </div>
                            </div>

                            {/* Floating Performance Pill Top-Left */}
                            <div className="absolute -top-5 -left-4 sm:left-4 bg-gradient-to-br from-[#004c68] to-[#05161f] text-white px-4 py-2.5 rounded-2xl shadow-xl border border-white/20 flex items-center gap-3 z-20">
                                <div className="w-9 h-9 rounded-xl bg-[#ff8806] flex items-center justify-center text-white font-black text-sm shadow-md">
                                    99%
                                </div>
                                <div>
                                    <div className="text-xs font-black text-white">Remise Réussie</div>
                                    <div className="text-[10px] text-slate-300">24/48h Partout au Maroc</div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {/* Right Column: Narrative, 3 Pillars & Advisor Contact */}
                    <div className="lg:col-span-7 space-y-6 lg:ps-4">
                        
                        {/* Headers */}
                        <div className="space-y-3">
                            <span className="text-sm uppercase tracking-wider font-extrabold text-[#ff8806] underline decoration-[#ff8806]/40 underline-offset-8 inline-block italic">
                                POURQUOI NOUS CHOISIR
                            </span>
                            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#004c68] font-sans tracking-tight">
                                Pourquoi Choisir Ouaiss Express ?
                            </h2>
                            <p className="text-base text-slate-600 leading-relaxed border-s-4 border-[#ff8806] ps-4 pt-1">
                                Modern transport system & secure packaging. Nous combinons une infrastructure logistique robuste et des technologies de pointe pour protéger votre cash et sublimer l'expérience de vos acheteurs.
                            </p>
                        </div>

                        {/* 3 Core Pillars Required by User with before-circle Style */}
                        <div className="space-y-4 pt-2">
                            {reasons.map((reason, idx) => {
                                const ReasonIcon = reason.icon;
                                return (
                                    <div 
                                        key={idx}
                                        className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-[#ff8806]/50 transition-all duration-300 flex items-start gap-4"
                                    >
                                        <div className="before-circle relative flex-shrink-0 pt-1">
                                            <div className={`w-13 h-13 rounded-2xl ${reason.bg} flex items-center justify-center shadow-xs`}>
                                                <ReasonIcon className={`w-6 h-6 ${reason.color}`} />
                                            </div>
                                        </div>
                                        <div className="space-y-1 flex-1">
                                            <h3 className="text-base sm:text-lg font-bold text-[#004c68]">
                                                {reason.title}
                                            </h3>
                                            <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                                {reason.description}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Additional Trust Checklist */}
                        <ul className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 text-xs font-semibold text-slate-700">
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-[#ff8806] flex-shrink-0" />
                                <span>Ramassage quotidien gratuit dès le 1er colis</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-[#ff8806] flex-shrink-0" />
                                <span>3 tentatives avec confirmation WhatsApp live</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-[#ff8806] flex-shrink-0" />
                                <span>Rapprochement bancaire CRBT automatique</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-[#ff8806] flex-shrink-0" />
                                <span>Tableau de bord marchand temps réel 24/7</span>
                            </li>
                        </ul>

                        {/* Advisor Hotline Box (Inspiration index-3 about-advisor) */}
                        <div className="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-2xl bg-white border border-slate-200 shadow-sm">
                            <div className="flex items-center gap-3.5">
                                <div className="w-11 h-11 rounded-full bg-[#ff8806]/10 text-[#ff8806] flex items-center justify-center flex-shrink-0">
                                    <PhoneCall className="w-5 h-5" />
                                </div>
                                <div>
                                    <div className="text-xs font-bold text-slate-900">Besoin d'un accompagnement personnalisé ?</div>
                                    <div className="text-[11px] text-slate-500">Nos coordinateurs logistiques vous répondent sous 5 minutes</div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 w-full sm:w-auto">
                                <a 
                                    href="https://wa.me/212600000000" 
                                    target="_blank" 
                                    rel="noreferrer"
                                    className="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md flex items-center justify-center gap-2 transition-colors flex-shrink-0"
                                >
                                    <span>WhatsApp Direct</span>
                                    <ArrowRight className="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            {/* Video Presentation Modal */}
            {videoModalOpen && (
                <div 
                    className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4"
                    onClick={() => setVideoModalOpen(false)}
                >
                    <div 
                        className="bg-slate-900 border border-white/20 rounded-3xl p-4 max-w-2xl w-full shadow-2xl relative"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between pb-3 px-2 text-white">
                            <h4 className="font-bold text-sm">Présentation de la logistique OUAISS EXPRESS</h4>
                            <button 
                                onClick={() => setVideoModalOpen(false)}
                                className="text-slate-400 hover:text-white font-bold text-lg cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>
                        <div className="relative pt-[56.25%] rounded-2xl overflow-hidden bg-black">
                            <iframe 
                                className="absolute inset-0 w-full h-full"
                                src="https://www.youtube.com/embed/MFLVmAE4cqg?autoplay=1" 
                                title="Vidéo OUAISS EXPRESS" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowFullScreen
                            />
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
}
