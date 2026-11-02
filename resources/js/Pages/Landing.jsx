import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import Navbar from '@/components/Navbar';
import Footer from '@/components/Footer';
import HeroSection from '@/components/Landing/HeroSection';
import ServicesSection from '@/components/Landing/ServicesSection';
import HowItWorksSection from '@/components/Landing/HowItWorksSection';
import AboutSection from '@/components/Landing/AboutSection';
import RateCalculatorSection from '@/components/Landing/RateCalculatorSection';
import WhyUsSection from '@/components/Landing/WhyUsSection';
import TestimonialsSection from '@/components/Landing/TestimonialsSection';
import CtaSection from '@/components/Landing/CtaSection';
import TrackingSimulatorModal from '@/components/Landing/TrackingSimulatorModal';
import { CheckCircle2 } from 'lucide-react';

export default function Landing({ cities = [], statistics = [], testimonials = [] }) {
    const { flash } = usePage().props;
    const [trackingModalOpen, setTrackingModalOpen] = useState(false);
    const [activeTrackingCode, setActiveTrackingCode] = useState('');

    const handleOpenTracking = (code = '') => {
        setActiveTrackingCode(code || 'OUA-782941');
        setTrackingModalOpen(true);
    };

    return (
        <div className="min-h-screen flex flex-col bg-white text-slate-900 selection:bg-[#ff8806] selection:text-white font-sans antialiased">
            <Head>
                <title>OUAISS EXPRESS - Livraison Express 24/48h & Logistique E-commerce au Maroc</title>
                <meta 
                    name="description" 
                    content="OUAISS EXPRESS : Plateforme logistique de référence au Maroc. Virement CRBT garanti à J+2, ramassage gratuit, confirmation WhatsApp et livraison express nationale 24/48h." 
                />
            </Head>

            {/* Flash Success Notification Toast */}
            {flash?.success && (
                <div className="fixed top-20 right-6 z-50 max-w-md bg-emerald-600 text-white p-4 rounded-2xl shadow-xl flex items-center gap-3 animate-in slide-in-from-top duration-300">
                    <CheckCircle2 className="w-5 h-5 flex-shrink-0" />
                    <span className="text-xs font-semibold">{flash.success}</span>
                </div>
            )}

            {/* Global Navbar */}
            <Navbar onOpenTracking={() => handleOpenTracking()} />

            {/* Main Landing Page Content Inspired by Envato Template Structure */}
            <main className="flex-1">
                {/* 1. Hero Banner with live tracking & logistics imagery */}
                <HeroSection onTrackSearch={(code) => handleOpenTracking(code)} />

                {/* 2. Services Suite with cards, badges & checkmarks */}
                <ServicesSection />

                {/* 3. 4-Step Workflow (Ramassage -> Tri -> Livraison -> Virement CRBT) */}
                <HowItWorksSection />

                {/* 4. About & Value Pillars (Infrastructure, Flotte, Hotline WhatsApp) */}
                <AboutSection />

                {/* 5. Live Interactive Moroccan Rate Calculator */}
                <RateCalculatorSection cities={cities} />

                {/* 6. Why Us & Key Statistics Counters */}
                <WhyUsSection statistics={statistics} />

                {/* 7. Moroccan Merchants Testimonials */}
                <TestimonialsSection testimonials={testimonials} />

                {/* 8. Conversion CTA Section */}
                <CtaSection />
            </main>

            {/* Interactive Live Tracking Modal */}
            <TrackingSimulatorModal 
                isOpen={trackingModalOpen} 
                onClose={() => setTrackingModalOpen(false)} 
                initialCode={activeTrackingCode}
            />

            {/* Global High-End Footer */}
            <Footer />
        </div>
    );
}
