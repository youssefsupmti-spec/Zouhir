import React, { useState } from 'react';
import { 
    CheckCircle2, 
    PhoneCall, 
    Play 
} from 'lucide-react';

export default function AboutSection() {
    const [videoModalOpen, setVideoModalOpen] = useState(false);

    const reasons = [
        {
            title: "Couverture Nationale",
            description: "Nous atteignons les zones éloignées et les grandes villes à travers le Maroc (Rabat, Salé, Témara, Kénitra, Harhoura, Tamesna, Skhirat, Bouknadel et tout le Royaume).",
            iconSvg: "/images/landing/icons/features-icon1.svg",
        },
        {
            title: "Paiements PAD Fiables",
            description: "Transferts transparents et réguliers de vos fonds de paiement à la livraison.",
            iconSvg: "/images/landing/icons/features-icon3.svg",
        },
        {
            title: "Support Dédié",
            description: "Notre équipe de support est disponible par téléphone et WhatsApp au 06 64 62 61 18 / 06 49 39 67 59 pour vous assister.",
            iconSvg: "/images/landing/icons/features-icon2.svg",
        },
    ];

    return (
        <section id="pourquoi-nous" className="py-28 bg-white relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-10 items-center">
                    
                    {/* Left Column: Affiche officielle OUAISS EXPRESS non rognée + éléments index-3 */}
                    <div className="lg:col-span-5">
                        <div className="pe-6 pb-8 ps-2 relative">
                            {/* Main Image (aspect-square pour afficher 100% des villes à gauche/droite et les numéros en bas) */}
                            <div className="p-2 bg-white common-shadow-five rounded-xl overflow-hidden border border-slate-100">
                                <img 
                                    src="/images/landing/إشهار توصيل مغربي من OUAISS EXPRESS.png" 
                                    alt="OUAISS EXPRESS - Livraison Rabat, Salé, Témara, Kénitra et tout le Maroc" 
                                    className="w-full h-auto aspect-square object-contain rounded-lg"
                                />
                            </div>

                            {/* Right-Top Vertical Writing-Mode Dual Box (positionné sans masquer les villes) */}
                            <div className="bg-[#004c68] rounded-xl py-1.5 absolute -end-2 top-8 z-20 shadow-xl hidden sm:block">
                                <div className="bg-[#f78304] rounded-xl px-4 py-6">
                                    <h5 className="writing-mode text-center text-white font-normal text-xs tracking-wider uppercase">
                                        Livraison <span className="font-extrabold">24h / 48h</span>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Exact index-3 About Three Content + Advisor Box */}
                    <div className="lg:col-span-7 lg:ps-6">
                        <div>
                            <span className="text-xl font-bold italic underline text-[#f78304] mb-3.5 inline-block">
                                POURQUOI NOUS CHOISIR
                            </span>
                            <h2 className="text-3xl sm:text-4xl lg:text-[42px] font-extrabold text-[#05161f] leading-[1.2] mb-6">
                                Pourquoi Choisir Ouaiss Express ?
                            </h2>
                            <p className="text-slate-700 ps-3.5 border-s-2 border-[#f78304] text-base leading-relaxed">
                                Nous vous libérons des contraintes de livraison sur toute la région (Rabat, Salé, Témara, Kénitra, Harhoura, Ain Atiq, Tamesna, Skhirat, Bouknadel, Sidi Yahya, Sidi Taibi) et partout au Maroc avec un service rapide, sécurisé et un retour de fonds garanti.
                            </p>

                            <div className="mt-8">
                                <div className="grid grid-cols-1 sm:grid-cols-12 gap-6 items-center">
                                    
                                    {/* Sub-column Left (7 cols): 3 Feature Items with before-circle & heartBeat */}
                                    <div className="sm:col-span-7">
                                        {reasons.map((item, index) => (
                                            <React.Fragment key={index}>
                                                <div className="animation-item flex items-center gap-4 group">
                                                    <span className="before-circle relative z-10 pb-5 pe-3 flex-shrink-0">
                                                        <img 
                                                            src={item.iconSvg} 
                                                            alt={item.title} 
                                                            className="animate__heartBeat w-12 h-12 object-contain"
                                                        />
                                                    </span>
                                                    <div>
                                                        <h6 className="text-lg font-bold text-[#05161f] group-hover:text-[#f78304] transition-colors">
                                                            {item.title}
                                                        </h6>
                                                        <p className="text-slate-600 text-sm mt-1.5 leading-relaxed">
                                                            {item.description}
                                                        </p>
                                                    </div>
                                                </div>
                                                {index < reasons.length - 1 && (
                                                    <span className="my-5 border-b border-slate-200 block" />
                                                )}
                                            </React.Fragment>
                                        ))}

                                        {/* Bottom Checkpoints */}
                                        <div className="mt-7">
                                            <ul className="flex flex-col gap-3">
                                                <li className="flex items-center gap-3">
                                                    <span className="text-[#f78304] flex text-xl">
                                                        <CheckCircle2 className="w-5 h-5 fill-[#f78304] text-white" />
                                                    </span>
                                                    <span className="text-[#05161f] font-medium text-sm">
                                                        Ramassage quotidien gratuit dès le 1er colis
                                                    </span>
                                                </li>
                                                <li className="flex items-center gap-3">
                                                    <span className="text-[#f78304] flex text-xl">
                                                        <CheckCircle2 className="w-5 h-5 fill-[#f78304] text-white" />
                                                    </span>
                                                    <span className="text-[#05161f] font-medium text-sm">
                                                        Virements PAD rapides avec relevé détaillé
                                                    </span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    {/* Sub-column Right (5 cols): Exact half-gradient-white Advisor Card with Poster Phone Numbers */}
                                    <div className="sm:col-span-5">
                                        <div className="half-gradient-white relative z-10 text-center pt-6">
                                            <div className="relative">
                                                <div className="max-w-[158px] mx-auto">
                                                    <img 
                                                        src="/images/landing/thumbs/about-advisor.png" 
                                                        alt="Conseiller OUAISS EXPRESS" 
                                                        className="w-full h-full object-cover"
                                                    />
                                                </div>
                                                <span className="flex text-amber-400 absolute start-0 top-1/2 -translate-y-1/2 ms-5">
                                                    <img src="/images/landing/icons/star-icon.svg" alt="" />
                                                </span>
                                            </div>
                                            <h6 className="mt-5 mb-1.5 text-lg font-bold text-[#05161f]">
                                                Service Client Dédié
                                            </h6>
                                            <p className="text-[#004c68] text-xs pb-6 px-3">
                                                Pour profiter de nos services :{' '}
                                                <a 
                                                    href="https://wa.me/212664626118" 
                                                    target="_blank" 
                                                    rel="noreferrer"
                                                    className="underline text-[#f78304] font-semibold hover:text-[#004c68] transition-colors block mt-1"
                                                >
                                                    Contactez-nous sur WhatsApp
                                                </a>
                                            </p>
                                            <div className="bg-[#f78304] p-3 flex flex-col justify-center items-center gap-1.5 w-full">
                                                <div className="flex items-center gap-2.5">
                                                    <span className="w-8 h-8 border border-white/40 rounded-full flex justify-center items-center text-white flex-shrink-0">
                                                        <PhoneCall className="w-4 h-4" />
                                                    </span>
                                                    <div className="flex flex-col text-start">
                                                        <a 
                                                            href="tel:+212664626118" 
                                                            className="font-extrabold text-white text-sm hover:text-[#05161f] transition-colors leading-tight"
                                                        >
                                                            06 64 62 61 18
                                                        </a>
                                                        <a 
                                                            href="tel:+212649396759" 
                                                            className="font-extrabold text-white text-sm hover:text-[#05161f] transition-colors leading-tight"
                                                        >
                                                            06 49 39 67 59
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {/* Video Modal */}
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
                            <h4 className="font-bold text-sm">Présentation OUAISS EXPRESS</h4>
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
