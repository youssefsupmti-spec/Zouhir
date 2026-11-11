import React, { useState, useEffect } from 'react';
import { 
    PackageOpen, 
    Truck, 
    Zap, 
    Banknote, 
    ArrowLeft, 
    ArrowRight 
} from 'lucide-react';

export default function ServicesSection() {
    const [activeIndex, setActiveIndex] = useState(0);
    const [isHovered, setIsHovered] = useState(false);

    const steps = [
        {
            step: "01",
            title: "Créer un Envoi",
            icon: PackageOpen,
            description: "Saisissez les détails de la commande dans votre tableau de bord marchand.",
            img: "/images/landing/thumbs/ouaiss-service-1-envoi.png",
            href: "/register"
        },
        {
            step: "02",
            title: "Ramassage",
            icon: Truck,
            description: "Notre livreur collecte les colis depuis votre localisation.",
            img: "/images/landing/thumbs/ouaiss-service-2-ramassage.png",
            href: "#calculateur"
        },
        {
            step: "03",
            title: "Livraison Rapide",
            icon: Zap,
            description: "Nous livrons à votre client à domicile.",
            img: "/images/landing/thumbs/ouaiss-service-3-livraison.png",
            href: "#calculateur"
        },
        {
            step: "04",
            title: "Recevoir le Paiement",
            icon: Banknote,
            description: "Nous transférons vos fonds PAD régulièrement.",
            img: "/images/landing/thumbs/ouaiss-service-4-paiement.png",
            href: "#calculateur"
        }
    ];

    const nextSlide = () => {
        setActiveIndex((prev) => (prev + 1) % steps.length);
    };

    const prevSlide = () => {
        setActiveIndex((prev) => (prev - 1 + steps.length) % steps.length);
    };

    useEffect(() => {
        if (isHovered) return;
        const timer = setInterval(() => {
            setActiveIndex((prev) => (prev + 1) % steps.length);
        }, 3500);
        return () => clearInterval(timer);
    }, [isHovered, steps.length]);

    // Display 3 cards in a loop on desktop, 2 on tablet, 1 on mobile
    const visibleCards = [
        steps[activeIndex % steps.length],
        steps[(activeIndex + 1) % steps.length],
        steps[(activeIndex + 2) % steps.length],
    ];

    return (
        <section id="services" className="service-three pt-28 pb-20 relative overflow-hidden bg-white z-10">
            {/* Background shapes from index-3.html */}
            <img 
                src="/images/landing/shapes/thrree-sections-bg.png" 
                alt="" 
                className="absolute bottom-0 start-0 w-full z-[-1] h-[55%] object-cover pointer-events-none"
            />
            <img 
                src="/images/landing/shapes/moon-shape.png" 
                alt="" 
                className="moon-shape absolute top-1/2 start-0 z-[-1] -translate-y-1/2 pointer-events-none hidden lg:block"
            />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
                {/* Section Header */}
                <div className="max-w-[856px] mx-auto text-center pb-14 mb-4">
                    <span className="text-xl font-bold italic underline text-[#f78304] mb-3.5 inline-block">
                        SOLUTIONS SUR-MESURE
                    </span>
                    <h2 className="text-3xl sm:text-4xl lg:text-[44px] font-extrabold text-[#05161f] leading-[1.2] tracking-tight">
                        Une Suite Logistique Complète pour Votre Succès
                    </h2>
                </div>

                {/* Slider Wrapper */}
                <div 
                    className="relative px-2 sm:px-8 xl:px-0"
                    onMouseEnter={() => setIsHovered(true)}
                    onMouseLeave={() => setIsHovered(false)}
                >
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[30px]">
                        {visibleCards.map((item, idx) => {
                            const IconComponent = item.icon;
                            const visibilityClass = idx === 1 
                                ? 'hidden md:block' 
                                : idx === 2 
                                ? 'hidden lg:block' 
                                : 'block';

                            return (
                                <div 
                                    key={`${item.step}-${activeIndex}-${idx}`} 
                                    className={`${visibilityClass} transition-all duration-500`}
                                >
                                    <div className="service-three-item pb-[52px] overflow-hidden relative group">
                                        {/* Top Image with White Frame & Triangle-Before Cutout */}
                                        <a 
                                            href={item.href} 
                                            className="triangle-before relative block w-full h-[342px] p-2 bg-white overflow-hidden"
                                        >
                                            <img 
                                                src={item.img} 
                                                alt={item.title} 
                                                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                            />
                                        </a>

                                        {/* Bottom Accordion Content: 103px default -> 300px on hover */}
                                        <div className="service-three-item__content ps-4 absolute bottom-0 start-0 end-0 w-full">
                                            <div className="bg-white triangle-two-before relative z-10 border-b-2 border-[#f78304] common-shadow-five py-[18px] mx-[24px] sm:mx-[32px] flex items-start gap-[18px] pe-6">
                                                {/* 85x85 Icon Box offset by -16px */}
                                                <span className="h-[85px] w-[85px] bg-[#f78304] rounded-[4px] flex justify-center items-center -ms-[16px] flex-shrink-0 group-hover:bg-[#004c68] transition-colors duration-500">
                                                    <IconComponent className="w-10 h-10 text-white stroke-[1.75]" />
                                                </span>

                                                {/* Text Block: Only h6 visible at rest; p and link revealed on hover */}
                                                <div className="py-[26px] flex-grow">
                                                    <h6 className="mb-8 text-lg font-bold text-[#05161f] leading-snug">
                                                        <a 
                                                            href={item.href} 
                                                            className="line-clamp-1 hover:text-[#f78304] transition-colors"
                                                        >
                                                            {item.step}. {item.title}
                                                        </a>
                                                    </h6>
                                                    <p className="text-slate-600 text-sm line-clamp-3 leading-relaxed">
                                                        {item.description}
                                                    </p>
                                                    <a 
                                                        href={item.href} 
                                                        className="inline-flex items-center gap-3 text-[#004c68] font-semibold text-sm mt-4 hover:text-[#f78304] transition-colors"
                                                    >
                                                        <span>Explorer Plus</span>
                                                        <img src="/images/landing/icons/arrow-long.svg" alt="" className="w-5 h-auto" />
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Prev & Next Circular Buttons (Exact index-3 style) */}
                    <button
                        type="button"
                        onClick={prevSlide}
                        aria-label="Étape précédente"
                        className="service-three-button-prev w-14 h-14 rounded-full text-xl text-white bg-[#f78304] hover:bg-[#004c68] flex justify-center items-center transition-colors duration-300 absolute top-1/2 -translate-y-1/2 -left-2 xl:-left-16 z-30 shadow-lg cursor-pointer"
                    >
                        <ArrowLeft className="w-6 h-6" />
                    </button>
                    <button
                        type="button"
                        onClick={nextSlide}
                        aria-label="Étape suivante"
                        className="service-three-button-next w-14 h-14 rounded-full text-xl text-white bg-[#f78304] hover:bg-[#004c68] flex justify-center items-center transition-colors duration-300 absolute top-1/2 -translate-y-1/2 -right-2 xl:-right-16 z-30 shadow-lg cursor-pointer"
                    >
                        <ArrowRight className="w-6 h-6" />
                    </button>

                    {/* Step Indicators (Dots) */}
                    <div className="flex justify-center items-center gap-2.5 mt-8">
                        {steps.map((s, i) => (
                            <button
                                key={s.step}
                                type="button"
                                onClick={() => setActiveIndex(i)}
                                aria-label={`Aller à l'étape ${s.step}`}
                                className={`h-2.5 rounded-full transition-all duration-300 cursor-pointer ${
                                    activeIndex === i 
                                        ? 'w-8 bg-[#f78304]' 
                                        : 'w-2.5 bg-slate-300 hover:bg-[#004c68]'
                                }`}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
