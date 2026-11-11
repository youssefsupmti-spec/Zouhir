import React from 'react';
import { 
    PackageOpen, 
    Truck, 
    Zap, 
    Banknote, 
    ArrowRight, 
    Sparkles,
    CheckCircle2
} from 'lucide-react';

export default function ServicesSection() {
    const steps = [
        {
            step: "01",
            title: "Créer un Envoi",
            badge: "Étape 1",
            icon: PackageOpen,
            description: "Saisissez les détails de la commande dans votre tableau de bord marchand.",
            details: "Import Excel en 1 clic ou saisie directe avec génération immédiate de bordereaux AWB code-barres.",
            img: "/images/landing/thumbs/ouaiss-service-1-envoi.png",
            cta: "Créer un envoi",
            href: "/register"
        },
        {
            step: "02",
            title: "Ramassage",
            badge: "Étape 2",
            icon: Truck,
            description: "Notre livreur collecte les colis depuis votre localisation.",
            details: "Collecte quotidienne gratuite dès votre 1er colis, directement à votre entrepôt, boutique ou domicile.",
            img: "/images/landing/thumbs/ouaiss-service-2-ramassage.png",
            cta: "Demander un ramassage",
            href: "#calculateur"
        },
        {
            step: "03",
            title: "Livraison Rapide",
            badge: "Étape 3",
            icon: Zap,
            description: "Nous livrons à votre client à domicile.",
            details: "Distribution express 24h/48h partout au Maroc avec géolocalisation et confirmation WhatsApp destinataire.",
            img: "/images/landing/thumbs/ouaiss-service-3-livraison.png",
            cta: "Suivre la livraison",
            href: "#calculateur"
        },
        {
            step: "04",
            title: "Recevoir le Paiement",
            badge: "Étape 4",
            icon: Banknote,
            description: "Nous transférons vos fonds PAD régulièrement.",
            details: "Encaissement scrupuleux du cash à la livraison (CRBT) et versement direct sur votre compte bancaire à J+2.",
            img: "/images/landing/thumbs/ouaiss-service-4-paiement.png",
            cta: "Consulter les tarifs",
            href: "#calculateur"
        }
    ];

    return (
        <section id="services" className="py-24 bg-white relative overflow-hidden">
            {/* Background Shape Patterns from index-3 */}
            <div 
                className="absolute inset-0 opacity-[0.025] pointer-events-none bg-repeat"
                style={{ backgroundImage: `url('/images/landing/shapes/bg-pattern.png')` }}
            />
            <div className="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-[#ff8806]/5 blur-3xl pointer-events-none" />
            <div className="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-[#004c68]/5 blur-3xl pointer-events-none" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                
                {/* Header: Exact Title & Subtitle from index-3 Inspiration */}
                <div className="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <div>
                        <span className="text-sm uppercase tracking-wider font-extrabold text-[#ff8806] underline decoration-[#ff8806]/40 underline-offset-8 inline-block italic">
                            SOLUTIONS SUR-MESURE
                        </span>
                    </div>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#004c68] font-sans tracking-tight">
                        Une Suite Logistique Complète pour Votre Succès
                    </h2>
                    <p className="text-base text-slate-600 leading-relaxed max-w-2xl mx-auto">
                        De la création de votre premier bordereau jusqu'au virement sécurisé de vos fonds PAD, découvrez notre processus fluide et rigoureux.
                    </p>
                </div>

                {/* Cards Container */}
                <div className="relative">
                    
                    {/* Responsive Grid: 4 Columns on Desktop, 2 on Tablet, 1 on Mobile */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-6">
                        {steps.map((item, index) => {
                            const IconComponent = item.icon;
                            return (
                                <div 
                                    key={index}
                                    className="group relative flex flex-col justify-between transition-all duration-300"
                                >
                                    {/* Top Image Box with triangle-before Cutout */}
                                    <div className="triangle-before relative w-full aspect-[16/10] overflow-hidden rounded-2xl bg-[#004c68] shadow-md">
                                        <img 
                                            src={item.img} 
                                            alt={item.title} 
                                            className="w-full h-full object-cover object-center transition-transform duration-700 group-hover:scale-105"
                                        />

                                        {/* Step Floating Badge Top-Right */}
                                        <div className="absolute top-3 right-3 bg-white/95 backdrop-blur-xs text-[#004c68] px-2.5 py-1 rounded-full text-xs font-black shadow-md border border-slate-200/60 flex items-center gap-1.5 z-20">
                                            <span className="w-2 h-2 rounded-full bg-[#ff8806] animate-pulse" />
                                            <span>{item.step}</span>
                                        </div>
                                    </div>

                                    {/* Bottom Floating Content with triangle-two-before & Shadow */}
                                    <div className="relative -mt-6 mx-2.5 sm:mx-3 z-10 flex-1 flex flex-col">
                                        <div className="bg-white triangle-two-before common-shadow-five border-b-4 border-[#ff8806] rounded-xl p-5 pt-6 flex-1 flex flex-col justify-between transition-transform duration-300 group-hover:-translate-y-1">
                                            
                                            <div>
                                                {/* Floating Icon Box (Envato square shape with offset) */}
                                                <div className="flex items-start gap-3.5 mb-3">
                                                    <div className="w-15 h-15 -ml-6 -mt-3 bg-[#ff8806] text-white rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:bg-[#004c68] group-hover:scale-105 transition-all duration-300">
                                                        <IconComponent className="w-7 h-7 text-white" />
                                                    </div>
                                                    <div className="pt-0.5">
                                                        <span className="text-[11px] font-bold text-[#ff8806] uppercase tracking-wide">
                                                            {item.badge}
                                                        </span>
                                                        <h3 className="text-base sm:text-lg font-bold text-[#004c68] group-hover:text-[#ff8806] transition-colors leading-snug">
                                                            {item.title}
                                                        </h3>
                                                    </div>
                                                </div>

                                                {/* Description Required by User */}
                                                <p className="text-xs text-slate-700 font-medium leading-relaxed mb-2">
                                                    {item.description}
                                                </p>

                                                {/* Additional logistics detail */}
                                                <p className="text-[11px] text-slate-500 leading-relaxed mb-4">
                                                    {item.details}
                                                </p>
                                            </div>

                                            {/* Action Link Footer */}
                                            <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
                                                <a 
                                                    href={item.href}
                                                    className="inline-flex items-center gap-2 text-xs font-bold text-[#004c68] group-hover:text-[#ff8806] transition-colors"
                                                >
                                                    <span>{item.cta}</span>
                                                    <ArrowRight className="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
                                                </a>
                                                <span className="w-6 h-6 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-[#ff8806]/15 group-hover:text-[#ff8806] transition-colors">
                                                    <CheckCircle2 className="w-3.5 h-3.5" />
                                                </span>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Bottom CTA Banner */}
                    <div className="mt-14 p-6 sm:p-8 bg-gradient-to-r from-[#004c68] via-[#072432] to-[#05161f] rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-center justify-between gap-6 border border-white/10">
                        <div className="flex items-center gap-4 text-center md:text-left">
                            <div className="w-12 h-12 rounded-2xl bg-[#ff8806] flex items-center justify-center font-bold text-white shadow-md flex-shrink-0">
                                <Sparkles className="w-6 h-6" />
                            </div>
                            <div>
                                <h4 className="font-extrabold text-base text-white">
                                    Une logistique rodée pour accélérer votre croissance e-commerce
                                </h4>
                                <p className="text-xs text-slate-300 mt-0.5">
                                    Expédiez vos premiers colis dès aujourd'hui sans frais d'inscription ni engagement.
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 flex-shrink-0 w-full md:w-auto">
                            <a 
                                href="/register" 
                                className="w-full md:w-auto px-6 py-3 rounded-xl bg-[#ff8806] hover:bg-[#e07400] text-white font-extrabold text-xs shadow-lg shadow-[#ff8806]/20 transition-all flex items-center justify-center gap-2"
                            >
                                <span>Ouvrir un compte marchand</span>
                                <ArrowRight className="w-4 h-4" />
                            </a>
                        </div>
                    </div>

                </div>

            </div>
        </section>
    );
}
