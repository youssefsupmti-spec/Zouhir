import React from 'react';
import { 
    PackagePlus, 
    Layers, 
    Truck, 
    Banknote, 
    ArrowRight, 
    Sparkles,
    CheckCircle2
} from 'lucide-react';

export default function HowItWorksSection() {
    const steps = [
        {
            number: "01",
            icon: PackagePlus,
            title: "Demande de Ramassage",
            subtitle: "1 Clic ou Import Excel / API",
            description: "Déposez vos colis sur la plateforme ou demandez un passage livreur. Notre équipe vient récupérer votre marchandise gratuitement à votre stock ou domicile.",
            badge: "Ramassage Gratuit",
            badgeColor: "bg-[#004c68] text-white",
        },
        {
            number: "02",
            icon: Layers,
            title: "Tri & Expédition au Hub",
            subtitle: "Hub Central Casablanca",
            description: "Vos colis sont scannés, pesés et étiquetés avec code-barres thermique AWB. Ils partent le soir même vers nos lignes régionales de livraison.",
            badge: "Départs Quotidiens",
            badgeColor: "bg-[#072432] text-white",
        },
        {
            number: "03",
            icon: Truck,
            title: "Livraison & Suivi WhatsApp",
            subtitle: "24h / 48h Porte-à-Porte",
            description: "Le livreur appelle le client et valide l'adresse sur WhatsApp. Votre destinataire reçoit son colis et paie en espèces ou par virement.",
            badge: "Confirmation Live",
            badgeColor: "bg-[#ff8806] text-white",
        },
        {
            number: "04",
            icon: Banknote,
            title: "Remboursement CRBT Garanti",
            subtitle: "Virement Bancaire à J+2",
            description: "Chaque dirham encaissé est immédiatement réconcilié. Vous recevez votre argent directement sur votre RIB chaque mardi et vendredi sans faute.",
            badge: "Zéro Retard Cash",
            badgeColor: "bg-emerald-600 text-white",
        },
    ];

    return (
        <section id="comment-ca-marche" className="py-24 bg-white relative overflow-hidden">
            {/* Background Pattern */}
            <div 
                className="absolute inset-0 opacity-[0.03] pointer-events-none bg-repeat"
                style={{ backgroundImage: `url('/images/landing/shapes/bg-pattern.png')` }}
            />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                {/* Header */}
                <div className="max-w-3xl mx-auto text-center space-y-3.5 mb-16">
                    <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#ff8806]/10 text-xs font-bold text-[#ff8806]">
                        <Sparkles className="w-3.5 h-3.5" />
                        <span>PROCESSUS FLUIDE & ÉPROUVÉ</span>
                    </div>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#004c68] font-sans tracking-tight">
                        Comment Fonctionne OUAISS EXPRESS ?
                    </h2>
                    <p className="text-base text-slate-600 leading-relaxed">
                        Un workflow simplifié en 4 étapes pour expédier en toute confiance, suivre chaque colis à la trace et encaisser votre trésorerie à temps.
                    </p>
                </div>

                {/* Steps Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 relative">
                    {steps.map((step, index) => {
                        const Icon = step.icon;
                        return (
                            <div 
                                key={index} 
                                className="relative group bg-slate-50 hover:bg-white rounded-3xl p-6 border border-slate-200/80 hover:border-[#ff8806] hover:shadow-xl transition-all duration-300 flex flex-col justify-between"
                            >
                                {/* Top number and Icon */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-3xl font-black text-[#004c68]/20 group-hover:text-[#ff8806] font-sans transition-colors">
                                            {step.number}
                                        </span>
                                        <span className={`text-[10px] font-bold px-2.5 py-0.5 rounded-full ${step.badgeColor}`}>
                                            {step.badge}
                                        </span>
                                    </div>

                                    {/* Icon Squircle Box */}
                                    <div className="w-14 h-14 rounded-2xl bg-[#004c68] text-white flex items-center justify-center shadow-md group-hover:bg-[#ff8806] group-hover:scale-105 transition-all duration-300">
                                        <Icon className="w-7 h-7" />
                                    </div>

                                    <div className="space-y-1.5 pt-2">
                                        <h3 className="text-lg font-bold text-[#004c68] group-hover:text-[#ff8806] transition-colors leading-snug">
                                            {step.title}
                                        </h3>
                                        <div className="text-xs font-semibold text-[#ff8806]">
                                            {step.subtitle}
                                        </div>
                                    </div>

                                    <p className="text-xs text-slate-600 leading-relaxed pt-1">
                                        {step.description}
                                    </p>
                                </div>

                                {/* Connecting Rocket / Arrow Indicator */}
                                {index < steps.length - 1 && (
                                    <div className="hidden lg:block absolute -right-4 top-1/2 -translate-y-1/2 z-20 pointer-events-none">
                                        <div className="w-8 h-8 rounded-full bg-white border border-slate-200 shadow-sm flex items-center justify-center text-[#ff8806]">
                                            <ArrowRight className="w-4 h-4" />
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* Bottom Trust Proof Box */}
                <div className="mt-14 p-6 bg-gradient-to-r from-[#004c68] via-[#072432] to-[#05161f] rounded-3xl text-white shadow-xl flex flex-col sm:flex-row items-center justify-between gap-6 border border-white/10">
                    <div className="flex items-center gap-4 text-center sm:text-left">
                        <div className="w-12 h-12 rounded-2xl bg-[#ff8806] flex items-center justify-center font-bold text-white shadow-md flex-shrink-0">
                            <CheckCircle2 className="w-7 h-7" />
                        </div>
                        <div>
                            <h4 className="font-extrabold text-base text-white">
                                Prêt à tester la différence dès aujourd'hui ?
                            </h4>
                            <p className="text-xs text-slate-300">
                                Aucun engagement, zéro frais d'abonnement. Payez uniquement à la livraison réussie.
                            </p>
                        </div>
                    </div>
                    <a 
                        href="/register" 
                        className="px-6 py-3 rounded-xl bg-[#ff8806] hover:bg-[#e07400] text-white font-extrabold text-xs shadow-lg shadow-[#ff8806]/20 transition-all flex items-center gap-2 flex-shrink-0"
                    >
                        <span>Créer un compte marchand gratuit</span>
                        <ArrowRight className="w-4 h-4" />
                    </a>
                </div>
            </div>
        </section>
    );
}
