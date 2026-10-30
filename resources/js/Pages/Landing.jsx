import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import HeroSection from '@/Components/Landing/HeroSection';
import ServicesSection from '@/Components/Landing/ServicesSection';
import RateCalculatorSection from '@/Components/Landing/RateCalculatorSection';
import WhyUsSection from '@/Components/Landing/WhyUsSection';
import TestimonialsSection from '@/Components/Landing/TestimonialsSection';
import CtaSection from '@/Components/Landing/CtaSection';
import TrackingSimulatorModal from '@/Components/Landing/TrackingSimulatorModal';
import { CheckCircle2 } from 'lucide-react';

export default function Landing({ cities, statistics, testimonials }) {
    const { flash } = usePage().props;
    const [trackingModalOpen, setTrackingModalOpen] = useState(false);
    const [activeTrackingCode, setActiveTrackingCode] = useState('');

    const handleOpenTracking = (code = '') => {
        setActiveTrackingCode(code || 'OUA-782941');
        setTrackingModalOpen(true);
    };

    return (
        <div className="min-h-screen flex flex-col bg-white text-slate-900 selection:bg-[#ff8806] selection:text-white">
            <Head>
                <title>Ouais Express - Livraison Express 24/48h & Logistique E-commerce au Maroc</title>
                <meta 
                    name="description" 
                    content="Ouais Express : La solution de livraison express de référence au Maroc. Virement CRBT à J+2, ramassage gratuit et confirmation WhatsApp des adresses." 
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

            {/* Main Landing Page Content */}
            <main className="flex-1">
                <HeroSection onTrackSearch={(code) => handleOpenTracking(code)} />
                <ServicesSection />
                <RateCalculatorSection cities={cities} />
                <WhyUsSection statistics={statistics} />
                <TestimonialsSection testimonials={testimonials} />
                <CtaSection />
            </main>

            {/* Interactive Live Tracking Modal */}
            <TrackingSimulatorModal 
                isOpen={trackingModalOpen} 
                onClose={() => setTrackingModalOpen(false)} 
                initialCode={activeTrackingCode}
            />

            {/* Global Footer */}
            <Footer />
        </div>
    );
}
