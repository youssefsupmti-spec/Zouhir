import React from 'react';
import Badge from '@/components/ui/AppBadge';
import { ShieldCheck, Zap, HeartHandshake, Eye, Map, CheckCircle2, Award } from 'lucide-react';

export default function WhyUsSection({ statistics = [] }) {
    const pillars = [
        {
            icon: Zap,
            title: "Trésorerie Préservée : CRBT à J+2",
            description: "Contrairement aux transporteurs traditionnels qui bloquent votre argent 2 à 3 semaines, Ouais Express verse les fonds collectés sous 48h chrono par virement bancaire sécurisé avec relevé clair.",
        },
        {
            icon: ShieldCheck,
            title: "Cellule Anti-Retour Spécialisée",
            description: "Avant chaque passage, nos télé-opérateurs vérifient l'adresse par WhatsApp ou appel. En cas d'indisponibilité du client, nous reprogrammons sans marquer d'échec précipité.",
        },
        {
            icon: Eye,
            title: "Transparence Totale des Scans",
            description: "Aucun statut opaque. Dès que votre colis bouge (ramassage, départ navette, arrivée agence, livreur en route), vous et votre client êtes notifiés en temps réel.",
        },
        {
            icon: HeartHandshake,
            title: "Chargé de Compte Dédié 7j/7",
            description: "Fini les standards téléphoniques sans réponse. Vous disposez d’un interlocuteur direct joignable sur WhatsApp pour résoudre le moindre imprévu en quelques minutes.",
        },
    ];

    return (
        <section id="pourquoi-nous" className="py-24 bg-[#05161f] text-white relative overflow-hidden">
            {/* Background glowing effects */}
            <div className="absolute top-1/2 left-0 w-96 h-96 bg-[#004c68]/20 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute top-10 right-10 w-96 h-96 bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto mb-16">
                    <Badge variant="secondary" size="md" className="mb-3">
                        <Award className="w-3.5 h-3.5 text-[#ff8806]" />
                        <span>Les Standards Ouais Express</span>
                    </Badge>
                    <h2 className="text-3xl sm:text-4xl font-extrabold text-white font-sans tracking-tight">
                        Pourquoi les meilleurs e-commerçants nous confient leur logistique
                    </h2>
                    <p className="mt-4 text-slate-300 text-sm sm:text-base leading-relaxed">
                        Nous avons éliminé les points de friction qui freinent l'e-commerce marocain : retards d'encaissement, colis perdus et retours injustifiés.
                    </p>
                </div>

                {/* 4 Pillars Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-20">
                    {pillars.map((pillar, idx) => {
                        const Icon = pillar.icon;
                        return (
                            <div 
                                key={idx}
                                className="bg-[#0a2533]/90 rounded-3xl p-8 border border-[#1a4961]/70 hover:border-[#ff8806]/50 transition-all duration-300 shadow-xl group"
                            >
                                <div className="flex items-start gap-4">
                                    <div className="w-12 h-12 rounded-2xl bg-[#004c68] text-[#ff8806] flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                                        <Icon className="w-6 h-6" />
                                    </div>
                                    <div className="space-y-2">
                                        <h3 className="text-xl font-bold text-white font-sans group-hover:text-[#ff8806] transition-colors">
                                            {pillar.title}
                                        </h3>
                                        <p className="text-slate-300 text-xs sm:text-sm leading-relaxed">
                                            {pillar.description}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Live Statistics Counter Row */}
                <div className="bg-gradient-to-r from-[#0a2533] via-[#0f3244] to-[#0a2533] p-8 sm:p-10 rounded-3xl border border-[#1a4961] shadow-2xl">
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center divide-y sm:divide-y-0 sm:divide-x divide-slate-700/50">
                        {statistics.map((stat, sIdx) => (
                            <div key={sIdx} className="pt-4 sm:pt-0 sm:px-4 space-y-1">
                                <div className="text-3xl sm:text-4xl lg:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-white via-slate-100 to-[#ff8806] font-sans">
                                    {stat.value}
                                </div>
                                <div className="text-xs sm:text-sm font-bold text-white mt-1">
                                    {stat.label}
                                </div>
                                <div className="text-[11px] text-slate-400">
                                    {stat.description}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
