import React from 'react';
import { 
    ShieldCheck, 
    Truck, 
    Headphones, 
    CheckCircle2, 
    PhoneCall, 
    Award,
    MapPin,
    ArrowRight
} from 'lucide-react';

export default function AboutSection() {
    return (
        <section id="pourquoi-nous" className="py-24 bg-slate-50 relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-center">
                    
                    {/* Left Column: Visual Imagery from Envato About Three */}
                    <div className="lg:col-span-5 relative">
                        <div className="relative mx-auto max-w-md lg:max-w-none">
                            {/* Main photo */}
                            <div className="rounded-3xl overflow-hidden shadow-2xl border-4 border-white bg-slate-200 h-96 relative group">
                                <img 
                                    src="/images/landing/thumbs/about-three-img1.png" 
                                    alt="Équipe Logistique OUAISS EXPRESS" 
                                    className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-[#05161f]/80 via-transparent to-transparent" />
                                
                                <div className="absolute bottom-4 left-4 right-4 text-white">
                                    <span className="text-[10.5px] uppercase font-bold tracking-wider text-[#ff8806]">
                                        Engagement & Rigueur
                                    </span>
                                    <h4 className="font-bold text-sm">Chaque colis traité avec la plus haute priorité</h4>
                                </div>
                            </div>

                            {/* Secondary Floating Image (Envato About Style) */}
                            <div className="hidden sm:block absolute -bottom-8 -left-6 w-52 h-44 rounded-2xl overflow-hidden border-4 border-white shadow-xl bg-slate-100">
                                <img 
                                    src="/images/landing/thumbs/about-three-img2.png" 
                                    alt="Contrôle qualité" 
                                    className="w-full h-full object-cover"
                                />
                            </div>

                            {/* Floating Experience Badge */}
                            <div className="absolute -top-6 -right-6 bg-gradient-to-br from-[#004c68] to-[#05161f] text-white p-5 rounded-3xl shadow-xl border border-white/20 text-center">
                                <div className="text-3xl font-black text-[#ff8806] font-sans">99.4%</div>
                                <div className="text-[11px] font-semibold text-slate-200 mt-0.5">Taux de Remise Réussie</div>
                                <div className="text-[9.5px] text-slate-400">Hub Casablanca 2026</div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Narrative & Values */}
                    <div className="lg:col-span-7 space-y-6">
                        <div className="space-y-3">
                            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#004c68]/10 text-xs font-bold text-[#004c68]">
                                <Award className="w-3.5 h-3.5 text-[#ff8806]" />
                                <span>PARTENAIRE DE RÉFÉRENCE AU MAROC</span>
                            </div>
                            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#004c68] font-sans tracking-tight">
                                Une Infrastructure Pensée pour Protéger Votre Cash & Vos Clients
                            </h2>
                            <p className="text-base text-slate-600 leading-relaxed">
                                Chez <strong>OUAISS EXPRESS</strong>, nous savons que la livraison n'est pas un simple transport de carton : c'est le dernier contact avec votre acheteur et le garant de votre trésorerie e-commerce.
                            </p>
                        </div>

                        {/* 2 Feature Rows (Envato Style) */}
                        <div className="space-y-4 pt-2">
                            <div className="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs flex items-start gap-4">
                                <div className="w-12 h-12 rounded-xl bg-[#004c68] text-white flex items-center justify-center flex-shrink-0 shadow-md">
                                    <Truck className="w-6 h-6 text-[#ff8806]" />
                                </div>
                                <div className="space-y-1">
                                    <h4 className="font-bold text-sm text-[#004c68]">Flotte Dédiée & Réseau Urbain Hyper-Réactif</h4>
                                    <p className="text-xs text-slate-600 leading-relaxed">
                                        Livreurs salariés formés aux standards du service client, équipés de scanners mobiles pour une mise à jour instantanée de chaque étape.
                                    </p>
                                </div>
                            </div>

                            <div className="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs flex items-start gap-4">
                                <div className="w-12 h-12 rounded-xl bg-[#ff8806] text-white flex items-center justify-center flex-shrink-0 shadow-md">
                                    <ShieldCheck className="w-6 h-6 text-white" />
                                </div>
                                <div className="space-y-1">
                                    <h4 className="font-bold text-sm text-[#004c68]">Traçabilité Financière CRBT Sans Faille</h4>
                                    <p className="text-xs text-slate-600 leading-relaxed">
                                        Rapprochement bancaire automatisé. Chaque centime collecté auprès de vos acheteurs vous est restitué dans les délais les plus stricts du marché.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Bullet points */}
                        <ul className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2 text-xs text-slate-700">
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span>Confirmation WhatsApp obligatoire</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span>3 tentatives avant toute mise en retour</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span>Ramassage gratuit dès le 1er colis</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span>Support marchand direct via WhatsApp 7j/7</span>
                            </li>
                        </ul>

                        {/* Support Card Hotline (Envato Advisor Call Box) */}
                        <div className="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-full bg-[#004c68]/10 text-[#004c68] flex items-center justify-center flex-shrink-0">
                                    <Headphones className="w-5 h-5 text-[#ff8806]" />
                                </div>
                                <div>
                                    <div className="text-xs font-bold text-slate-900">Une question ou un besoin urgent ?</div>
                                    <div className="text-[11px] text-slate-500">Nos coordinateurs logistiques vous répondent immédiatement</div>
                                </div>
                            </div>
                            <a 
                                href="https://wa.me/212600000000" 
                                target="_blank" 
                                rel="noreferrer"
                                className="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md flex items-center gap-2 flex-shrink-0 transition-colors"
                            >
                                <PhoneCall className="w-3.5 h-3.5" />
                                <span>Contact WhatsApp Direct</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    );
}

