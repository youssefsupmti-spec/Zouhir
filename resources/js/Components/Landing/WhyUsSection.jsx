import React from 'react';
import Badge from '@/components/ui/AppBadge';
import { 
    ShieldCheck, 
    Zap, 
    HeartHandshake, 
    Eye, 
    MapPin, 
    CheckCircle2, 
    Award,
    TrendingUp,
    Users,
    Clock
} from 'lucide-react';

export default function WhyUsSection({ statistics = [] }) {
    const pillars = [
        {
            icon: Zap,
            title: "Trésorerie Préservée : CRBT à J+2",
            description: "Contrairement aux transporteurs traditionnels qui bloquent votre argent 2 à 3 semaines, OUAISS EXPRESS verse les fonds collectés sous 48h chrono par virement bancaire sécurisé avec relevé clair.",
        },
        {
            icon: ShieldCheck,
            title: "Cellule Anti-Retour Proactive",
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

    const stats = [
        { value: "1 300+", label: "Villes & Centres Couverts", sub: "Réseau National Intégral", icon: MapPin },
        { value: "99.4%", label: "Taux de Livraison Réussie", sub: "Moins de 2% de retours", icon: TrendingUp },
        { value: "J+2", label: "Délai Versement CRBT", sub: "Mardi & Vendredi sans retard", icon: Clock },
        { value: "500k+", label: "Colis Livrés en 2026", sub: "Partenaire des e-commerçants", icon: Users },
    ];

    return (
        <section className="py-24 bg-[#05161f] text-white relative overflow-hidden">
            {/* Background Texture from Envato Template */}
            <div 
                className="absolute inset-0 opacity-15 pointer-events-none bg-cover bg-center"
                style={{ backgroundImage: `url('/images/landing/shapes/counter-bg.png')` }}
            />

            {/* Glowing accents */}
            <div className="absolute top-1/2 left-0 w-96 h-96 bg-[#004c68]/30 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute top-10 right-10 w-96 h-96 bg-[#ff8806]/20 rounded-full blur-3xl pointer-events-none" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto mb-16 space-y-3.5">
                    <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md text-xs font-bold text-[#ff8806] border border-white/10">
                        <Award className="w-3.5 h-3.5" />
                        <span>LES STANDARDS D'EXCELLENCE OUAISS EXPRESS</span>
                    </div>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-white font-sans tracking-tight">
                        Pourquoi les meilleurs e-commerçants nous font confiance
                    </h2>
                    <p className="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Nous avons éliminé les points de friction majeurs du e-commerce marocain : retards d'encaissement, colis mal acheminés et retours injustifiés.
                    </p>
                </div>

                {/* 4 Pillars Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-20">
                    {pillars.map((pillar, idx) => {
                        const Icon = pillar.icon;
                        return (
                            <div 
                                key={idx}
                                className="bg-[#072432]/90 rounded-3xl p-8 border border-white/10 hover:border-[#ff8806]/60 transition-all duration-300 shadow-xl group flex flex-col justify-between"
                            >
                                <div className="flex items-start gap-5">
                                    <div className="w-14 h-14 rounded-2xl bg-[#004c68] text-[#ff8806] flex items-center justify-center flex-shrink-0 group-hover:scale-110 group-hover:bg-[#ff8806] group-hover:text-white transition-all shadow-md">
                                        <Icon className="w-7 h-7" />
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

                {/* Counter & Statistics Grid (Envato Counter Section Style) */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-6 p-8 rounded-3xl bg-gradient-to-r from-[#004c68]/80 via-[#072432]/90 to-[#05161f] border border-white/15 shadow-2xl backdrop-blur-md">
                    {stats.map((item, index) => {
                        const Icon = item.icon;
                        return (
                            <div key={index} className="text-center space-y-2">
                                <div className="w-12 h-12 rounded-2xl bg-[#ff8806]/15 text-[#ff8806] flex items-center justify-center mx-auto mb-2">
                                    <Icon className="w-6 h-6" />
                                </div>
                                <div className="text-3xl sm:text-4xl lg:text-5xl font-black text-white font-sans tracking-tight">
                                    {item.value}
                                </div>
                                <div className="text-xs sm:text-sm font-bold text-slate-200">
                                    {item.label}
                                </div>
                                <div className="text-[11px] text-[#ff8806] font-medium">
                                    {item.sub}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
