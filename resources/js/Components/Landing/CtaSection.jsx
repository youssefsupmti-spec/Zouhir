import React from 'react';
import { Link } from '@inertiajs/react';
import Button from '@/components/ui/AppButton';
import { ArrowRight, CheckCircle2, ShieldCheck, Sparkles } from 'lucide-react';

export default function CtaSection() {
    return (
        <section className="py-20 bg-slate-50 relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="relative rounded-3xl bg-gradient-to-r from-[#004c68] via-[#0d6386] to-[#004c68] p-8 sm:p-14 text-white shadow-2xl shadow-[#004c68]/30 overflow-hidden">
                    {/* Decorative Background Elements */}
                    <div className="absolute top-0 right-0 -mr-20 -mt-20 w-80 h-80 rounded-full bg-[#ff8806]/20 blur-3xl pointer-events-none" />
                    <div className="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-white/10 blur-3xl pointer-events-none" />

                    <div className="relative z-10 max-w-3xl mx-auto text-center space-y-6">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md text-xs font-semibold text-white border border-white/20">
                            <Sparkles className="w-3.5 h-3.5 text-[#ff8806]" />
                            <span>Ouverture de compte instantanée et sans engagement</span>
                        </div>

                        <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black font-['Outfit'] tracking-tight leading-tight">
                            Prêt à offrir à vos clients une expérience de livraison irréprochable ?
                        </h2>

                        <p className="text-sm sm:text-base text-slate-200 max-w-2xl mx-auto leading-relaxed">
                            Rejoignez notre réseau de marchands partenaires dès aujourd'hui. Ramassage gratuit dès votre premier colis et reversement CRBT sous 48h.
                        </p>

                        <div className="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                            <Link href="/register">
                                <Button 
                                    variant="secondary" 
                                    size="lg" 
                                    className="w-full sm:w-auto font-bold text-base shadow-xl shadow-black/20"
                                >
                                    <span>Créer mon Compte Marchand</span>
                                    <ArrowRight className="w-4 h-4 ml-2" />
                                </Button>
                            </Link>
                            <Link href="/login">
                                <Button 
                                    variant="outline" 
                                    size="lg" 
                                    className="w-full sm:w-auto text-white border-white hover:bg-white/10"
                                >
                                    <span>Accéder à l'Espace Client</span>
                                </Button>
                            </Link>
                        </div>

                        <div className="pt-6 flex flex-wrap items-center justify-center gap-6 text-xs text-slate-200">
                            <div className="flex items-center gap-1.5">
                                <CheckCircle2 className="w-4 h-4 text-[#ff8806]" />
                                <span>Pas de frais d'inscription</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <CheckCircle2 className="w-4 h-4 text-[#ff8806]" />
                                <span>Activation sous 2 heures</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <CheckCircle2 className="w-4 h-4 text-[#ff8806]" />
                                <span>Assurance colis incluse</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
