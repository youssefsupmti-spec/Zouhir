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
            description: "Nous atteignons les zones éloignées et les grandes villes à travers le Maroc.",
            iconSvg: "/images/landing/icons/features-icon1.svg",
        },
        {
            title: "Paiements PAD Fiables",
            description: "Transferts transparents et réguliers de vos fonds de paiement à la livraison.",
            iconSvg: "/images/landing/icons/features-icon3.svg",
        },
        {
            title: "Support Dédié",
            description: "Notre équipe de support est disponible par téléphone et WhatsApp pour vous assister.",
            iconSvg: "/images/landing/icons/features-icon2.svg",
        },
    ];

    return (
        <section id="pourquoi-nous" className="py-28 bg-white relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8 items-center">
                    
                    {/* Left Column: Exact index-3 About Three Visual Composition */}
                    <div className="lg:col-span-5">
                        <div className="pe-9 pb-10 ps-5 relative h-full min-h-[500px]">
                            {/* Main Image */}
                            <img 
                                src="/images/landing/إشهار توصيل مغربي من OUAISS EXPRESS.png" 
                                alt="OUAISS EXPRESS - Logistique & Livraison au Maroc" 
                                className="w-full h-[500px] object-cover rounded-xs shadow-md"
                            />

                            {/* Bottom-Left Floating Secondary Image + Ripple Play Button */}
                            <div className="absolute start-0 bottom-0 bg-white p-2.5 rounded-lg common-shadow-five z-20 max-w-[230px]">
                                <div className="relative">
                                    <img 
                                        src="/images/landing/thumbs/about-three-img2.png" 
                                        alt="Centre de tri OUAISS EXPRESS" 
                                        className="w-full h-full object-cover rounded-lg"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setVideoModalOpen(true)}
                                        aria-label="Voir la vidéo"
                                        className="play-button bg-light-animation w-[70px] h-[70px] inline-flex justify-center items-center bg-[#004c68] hover:bg-[#f78304] text-white rounded-full text-xl absolute start-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 transition-colors duration-300 cursor-pointer"
                                    >
                                        <Play className="w-6 h-6 fill-white ml-1" />
                                    </button>
                                </div>
                            </div>

                            {/* Right-Center Vertical Writing-Mode Dual Box */}
                            <div className="bg-[#004c68] rounded-xl py-1.5 absolute end-0 top-1/2 -translate-y-1/2 z-20 shadow-xl">
                                <div className="bg-[#f78304] rounded-xl px-6 py-10">
                                    <h5 className="writing-mode text-center text-white font-normal text-base tracking-wide">
                                        Service <span className="font-extrabold">Rapide & Fiable</span> au Maroc
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Exact index-3 About Three Content + Advisor Box */}
                    <div className="lg:col-span-7 lg:ps-8">
                        <div>
                            <span className="text-xl font-bold italic underline text-[#f78304] mb-3.5 inline-block">
                                POURQUOI NOUS CHOISIR
                            </span>
                            <h2 className="text-3xl sm:text-4xl lg:text-[42px] font-extrabold text-[#05161f] leading-[1.2] mb-6">
                                Pourquoi Choisir Ouaiss Express ?
                            </h2>
                            <p className="text-slate-700 ps-3.5 border-s-2 border-[#f78304] text-base leading-relaxed">
                                Modern transport system & secure packaging — Une infrastructure logistique moderne et un suivi rigoureux de chaque expédition pour garantir la satisfaction de vos clients et la sécurité de vos encaissements.
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

                                    {/* Sub-column Right (5 cols): Exact half-gradient-white Advisor Card */}
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
                                            <h6 className="mt-5 mb-2 text-lg font-bold text-[#05161f]">
                                                Support Ouaiss Express
                                            </h6>
                                            <p className="text-[#004c68] text-sm pb-10 px-3">
                                                Parler à un conseiller ?{' '}
                                                <a 
                                                    href="https://wa.me/212600000000" 
                                                    target="_blank" 
                                                    rel="noreferrer"
                                                    className="underline text-[#f78304] font-semibold hover:text-[#004c68] transition-colors"
                                                >
                                                    Cliquez Maintenant
                                                </a>
                                            </p>
                                            <div className="bg-[#f78304] p-2.5 flex justify-center items-center gap-3 w-full">
                                                <span className="w-9 h-9 border border-white/40 rounded-full flex justify-center items-center text-white">
                                                    <PhoneCall className="w-4 h-4" />
                                                </span>
                                                <a 
                                                    href="https://wa.me/212600000000" 
                                                    target="_blank" 
                                                    rel="noreferrer"
                                                    className="font-bold text-white text-sm hover:text-[#05161f] transition-colors text-start"
                                                >
                                                    WhatsApp Direct 7j/7
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
