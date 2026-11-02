import React from 'react';
import Badge from '@/components/ui/AppBadge';
import { 
    Truck, 
    Banknote, 
    RotateCcw, 
    Warehouse, 
    Boxes, 
    Check, 
    ArrowRight,
    ShieldCheck,
    Clock,
    Zap
} from 'lucide-react';

export default function ServicesSection() {
    const services = [
        {
            icon: Truck,
            img: "/images/landing/thumbs/service-three-img1.png",
            badge: "Livraison 24/48h",
            badgeColor: "bg-[#004c68] text-white",
            title: "Distribution E-commerce Express",
            description: "Livraison à domicile et en points relais partout au Maroc sous 24 à 48 heures. Prise de rendez-vous téléphonique et confirmation WhatsApp géolocalisée pour maximiser le taux de remise au premier passage.",
            perks: [
                "Ramassage quotidien gratuit à votre stock ou domicile",
                "3 tentatives de livraison avec reprogrammation flexible",
                "Confirmation WhatsApp du destinataire avec géolocalisation",
            ],
            cta: "Détails de la livraison",
        },
        {
            icon: Banknote,
            img: "/images/landing/thumbs/service-three-img2.png",
            badge: "CRBT J+2 Garanti",
            badgeColor: "bg-[#ff8806] text-white",
            title: "Encaissement Cash & Virement Rapide",
            description: "Encaissement scrupuleux des montants en espèces (Cash On Delivery). Vos fonds sont réconciliés automatiquement et virés directement sur votre compte bancaire à J+2 avec relevé détaillé.",
            perks: [
                "Zéro retard : virements bancaires chaque mardi et vendredi",
                "Relevé financier clair ligne par ligne avec bordereau",
                "Notification instantanée par SMS / WhatsApp à chaque versement",
            ],
            cta: "Processus d'encaissement",
        },
        {
            icon: RotateCcw,
            img: "/images/landing/thumbs/service-three-img3.png",
            badge: "Anti-Retour Proactif",
            badgeColor: "bg-[#05161f] text-white",
            title: "Gestion Active des Retours & Échanges",
            description: "Réduction prouvée de plus de 30% des taux de retour grâce à notre cellule de relance proactive avant toute mise en échec. Gestion simplifiée des échanges en un seul passage livreur.",
            perks: [
                "Contrôle de l’état du produit et de l'emballage au retour",
                "Échange de colis direct chez le client en une seule visite",
                "Rapports détaillés et transparents des motifs d’échec",
            ],
            cta: "Stratégie anti-retour",
        },
        {
            icon: Warehouse,
            img: "/images/landing/thumbs/quote-three-img.png",
            badge: "Fulfillment Hub",
            badgeColor: "bg-[#004c68] text-white",
            title: "Entreposage & Préparation de Commandes",
            description: "Stockez vos produits dans notre hub logistique ultra-sécurisé de Casablanca. Nous prenons en charge la réception, le picking, l'emballage soigné et l'étiquetage thermique en flux tendu.",
            perks: [
                "Entrepôt climatisé, sécurisé et télésurveillé 24h/24",
                "Inventaire en temps réel synchronisé avec votre boutique",
                "Emballage neutre ou personnalisé avec votre logo et ruban",
            ],
            cta: "Découvrir le fulfillment",
        },
        {
            icon: Boxes,
            img: "/images/landing/thumbs/counter-img.png",
            badge: "Grands Comptes & B2B",
            badgeColor: "bg-[#ff8806] text-white",
            title: "Messagerie & Fret Régional B2B",
            description: "Acheminement inter-villes de marchandises volumineuses, approvisionnement de succursales et transferts réguliers entre vos dépôts de Casablanca, Rabat, Marrakech, Tanger et Agadir.",
            perks: [
                "Tarifs dégressifs au volume et à la palette",
                "Bordereaux de transport et lettres de voiture électroniques",
                "Service client dédié et interlocuteur commercial unique",
            ],
            cta: "Devis B2B sur-mesure",
        },
        {
            icon: Zap,
            img: "/images/landing/thumbs/banner-three-img.png",
            badge: "Connexion 100% API",
            badgeColor: "bg-[#05161f] text-white",
            title: "Intégration Shopify, WooCommerce & YouCan",
            description: "Connectez votre boutique e-commerce en quelques minutes. Vos commandes sont automatiquement synchronisées, les étiquettes générées en un clic et les statuts mis à jour en direct.",
            perks: [
                "Plugins prêts à l'emploi et API REST documentée",
                "Impression thermique A6 en lot des bordereaux d'expédition",
                "Webhooks en temps réel pour notifier vos clients",
            ],
            cta: "Documentation technique",
        },
    ];

    return (
        <section id="services" className="py-24 bg-slate-50 relative overflow-hidden">
            {/* Subtle background logistics contour shape from Envato template */}
            <img 
                src="/images/landing/shapes/thrree-sections-bg.png" 
                alt="" 
                className="absolute inset-0 w-full h-full object-cover opacity-15 pointer-events-none" 
            />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                {/* Section Header */}
                <div className="max-w-3xl mx-auto text-center space-y-3.5 mb-16">
                    <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#004c68]/10 text-xs font-bold text-[#004c68]">
                        <Zap className="w-3.5 h-3.5 text-[#ff8806]" />
                        <span>SOLUTIONS SUR-MESURE</span>
                    </div>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#004c68] font-sans tracking-tight">
                        Une Suite Logistique Complète pour Votre Succès
                    </h2>
                    <p className="text-base text-slate-600 leading-relaxed">
                        De la collecte de vos colis jusqu’au versement de votre argent, nous avons conçu chaque étape pour vous faire gagner du temps, sécuriser votre marge et ravir vos clients.
                    </p>
                </div>

                {/* Services Grid (Inspired by Envato Service Three Layout) */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {services.map((service, index) => {
                        const Icon = service.icon;
                        return (
                            <div 
                                key={index}
                                className="group bg-white rounded-3xl overflow-hidden border border-slate-200/80 shadow-md hover:shadow-2xl hover:border-[#ff8806]/40 transition-all duration-300 flex flex-col justify-between"
                            >
                                {/* Card Header Image with Floating Icon */}
                                <div className="relative h-48 w-full overflow-hidden bg-slate-100">
                                    <img 
                                        src={service.img} 
                                        alt={service.title} 
                                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                                    
                                    {/* Badge */}
                                    <span className={`absolute top-4 left-4 text-[11px] font-bold px-3 py-1 rounded-full shadow-md ${service.badgeColor}`}>
                                        {service.badge}
                                    </span>

                                    {/* Icon Floating Badge */}
                                    <div className="absolute -bottom-5 right-6 w-14 h-14 rounded-2xl bg-[#ff8806] text-white flex items-center justify-center shadow-lg group-hover:scale-110 group-hover:bg-[#004c68] transition-all duration-300">
                                        <Icon className="w-7 h-7" />
                                    </div>
                                </div>

                                {/* Card Body */}
                                <div className="p-6 pt-8 flex-1 flex flex-col justify-between">
                                    <div className="space-y-3">
                                        <h3 className="text-xl font-bold text-[#004c68] group-hover:text-[#ff8806] transition-colors leading-snug">
                                            {service.title}
                                        </h3>
                                        <p className="text-xs text-slate-600 leading-relaxed">
                                            {service.description}
                                        </p>
                                        
                                        {/* Perks Checklist */}
                                        <ul className="pt-2 space-y-2 border-t border-slate-100 text-xs text-slate-700">
                                            {service.perks.map((perk, i) => (
                                                <li key={i} className="flex items-start gap-2">
                                                    <span className="w-4 h-4 rounded-full bg-[#ff8806]/15 text-[#ff8806] flex items-center justify-center flex-shrink-0 mt-0.5">
                                                        <Check className="w-2.5 h-2.5 stroke-[3]" />
                                                    </span>
                                                    <span>{perk}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>

                                    {/* Card Footer Link */}
                                    <div className="pt-5 mt-4 border-t border-slate-100 flex items-center justify-between">
                                        <a 
                                            href="#calculateur" 
                                            className="text-xs font-bold text-[#004c68] group-hover:text-[#ff8806] flex items-center gap-1.5 transition-colors"
                                        >
                                            <span>{service.cta}</span>
                                            <ArrowRight className="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
                                        </a>
                                        <span className="w-7 h-7 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-[#ff8806]/10 group-hover:text-[#ff8806] transition-colors">
                                            <Clock className="w-3.5 h-3.5" />
                                        </span>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
