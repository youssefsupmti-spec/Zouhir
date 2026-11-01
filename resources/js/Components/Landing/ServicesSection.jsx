import React from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/AppCard';
import Badge from '@/components/ui/AppBadge';
import { 
    Truck, 
    Banknote, 
    RotateCcw, 
    Warehouse, 
    Boxes, 
    Cpu, 
    Check, 
    ArrowRight 
} from 'lucide-react';

export default function ServicesSection() {
    const services = [
        {
            icon: Truck,
            badge: "Incontournable",
            title: "Livraison E-commerce Express",
            description: "Distribution porte-à-porte au Maroc sous 24 à 48 heures. Prise de rendez-vous téléphonique et confirmation WhatsApp avec géolocalisation pour un taux de succès maximal.",
            perks: [
                "Ramassage quotidien gratuit à votre stock",
                "Tentatives multiples de livraison incluses",
                "Confirmation SMS et WhatsApp du destinataire",
            ],
            color: "#004c68",
        },
        {
            icon: Banknote,
            badge: "Prioritaire",
            title: "Encaissement CRBT Garanti J+2",
            description: "Encaissement sécurisé des montants en espèces à la livraison. Vos fonds sont centralisés, rapprochés et virés sur votre compte bancaire tous les mardis et vendredis.",
            perks: [
                "Zéro retard de virement bancaire",
                "Rapprochement financier ligne par ligne",
                "Notification instantanée de versement",
            ],
            color: "#ff8806",
        },
        {
            icon: RotateCcw,
            badge: "Anti-Gaspillage",
            title: "Gestion Active des Retours",
            description: "Réduction prouvée de 30% des taux de retour grâce à notre processus de relance proactive avant toute mise en échec. Les retours inévitables sont renvoyés soigneusement étiquetés.",
            perks: [
                "Contrôle de l’état du produit au retour",
                "Consolidation hebdomadaire gratuite",
                "Suivi transparent des motifs d’échec",
            ],
            color: "#004c68",
        },
        {
            icon: Warehouse,
            badge: "Évolutif",
            title: "Stockage & Fulfillment Hub",
            description: "Déléguez l'entreposage de vos marchandises dans notre hub central sécurisé de Casablanca. Nous gérons le picking, l'emballage soigné et l'étiquetage en flux tendu.",
            perks: [
                "Entrepôt climatisé et télésurveillé 24/7",
                "Inventaire synchronisé en temps réel",
                "Emballage neutre ou personnalisé à votre marque",
            ],
            color: "#ff8806",
        },
        {
            icon: Boxes,
            badge: "Entreprises",
            title: "Messagerie & Fret B2B",
            description: "Pour les transferts inter-magasins, approvisionnements de succursales et envois de colis volumineux entre Casablanca, Tanger, Marrakech, Fès et Agadir.",
            perks: [
                "Tarifs dégressifs au volume et à la palette",
                "Documents de livraison contre-signés",
                "Chargement et déchargement sécurisés",
            ],
            color: "#004c68",
        },
        {
            icon: Cpu,
            badge: "Digital",
            title: "API & Modules E-commerce",
            description: "Connectez votre boutique Shopify, WooCommerce ou YouCan en quelques minutes pour synchroniser les commandes et imprimer les bordereaux en un clic.",
            perks: [
                "Création automatique d’expéditions",
                "Impression thermique des étiquettes codes-barres",
                "Webhooks et statut en temps réel",
            ],
            color: "#004c68",
        },
    ];

    return (
        <section id="services" className="py-24 bg-white relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto mb-16">
                    <Badge variant="secondary" size="md" className="mb-3">
                        Notre Expertise Terrain
                    </Badge>
                    <h2 className="text-3xl sm:text-4xl font-extrabold text-[#004c68] font-sans tracking-tight">
                        Une suite complète d'opérations pour accélérer votre commerce
                    </h2>
                    <p className="mt-4 text-slate-600 text-sm sm:text-base leading-relaxed">
                        Conçue spécifiquement pour les défis du marché marocain : du ramassage à l'encaissement du cash, nous sécurisons chaque étape.
                    </p>
                </div>

                {/* Services Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {services.map((item, index) => {
                        const Icon = item.icon;
                        return (
                            <Card 
                                key={index} 
                                hoverEffect={true} 
                                className="flex flex-col justify-between border-slate-200/80 hover:border-[#004c68]/30 transition-all rounded-3xl p-6 sm:p-7 group"
                            >
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div 
                                            className="w-12 h-12 rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110 duration-300"
                                            style={{ 
                                                backgroundColor: item.color === '#ff8806' ? 'rgba(255, 136, 6, 0.12)' : 'rgba(0, 76, 104, 0.1)',
                                                color: item.color
                                            }}
                                        >
                                            <Icon className="w-6 h-6" />
                                        </div>
                                        <Badge variant={item.color === '#ff8806' ? 'secondary' : 'primary'} size="sm">
                                            {item.badge}
                                        </Badge>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-bold text-slate-900 font-sans group-hover:text-[#004c68] transition-colors">
                                            {item.title}
                                        </h3>
                                        <p className="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                                            {item.description}
                                        </p>
                                    </div>

                                    <div className="pt-3 border-t border-slate-100 space-y-2">
                                        {item.perks.map((perk, pIdx) => (
                                            <div key={pIdx} className="flex items-center gap-2 text-xs text-slate-700">
                                                <div className="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                                    <Check className="w-2.5 h-2.5 stroke-[3]" />
                                                </div>
                                                <span>{perk}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="pt-6 mt-4 border-t border-slate-100">
                                    <a 
                                        href="/register" 
                                        className="text-xs font-bold text-[#004c68] group-hover:text-[#ff8806] transition-colors inline-flex items-center gap-1.5"
                                    >
                                        <span>Démarrer avec cette solution</span>
                                        <ArrowRight className="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
                                    </a>
                                </div>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
