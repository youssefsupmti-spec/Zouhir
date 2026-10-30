import React, { useState, useEffect } from 'react';
import { Card, CardContent } from '@/components/ui/Card';
import Button from '@/components/ui/AppButton';
import Badge from '@/components/ui/Badge';
import { Calculator, ArrowRight, CheckCircle2, ShieldCheck, Sparkles, Truck } from 'lucide-react';

export default function RateCalculatorSection({ cities = [] }) {
    const [departure, setDeparture] = useState('Casablanca');
    const [destination, setDestination] = useState('Rabat');
    const [weight, setWeight] = useState(1.5);
    const [hasCrbt, setHasCrbt] = useState(true);
    const [crbtAmount, setCrbtAmount] = useState(250);
    const [rateResult, setRateResult] = useState({
        total_rate: 35.0,
        estimated_delay: '24 à 48 heures',
        base_rate: 35.0,
        crbt_fee: 5.0,
    });
    const [loading, setLoading] = useState(false);

    const calculate = async () => {
        setLoading(true);
        try {
            const response = await fetch('/api/tarification/calculate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    departure,
                    destination,
                    weight: parseFloat(weight) || 1,
                    has_crbt: hasCrbt,
                    crbt_amount: hasCrbt ? parseFloat(crbtAmount) || 0 : 0,
                }),
            });
            const data = await response.json();
            if (response.ok && data.success) {
                setRateResult(data);
            }
        } catch (e) {
            console.error('Erreur calcul tarif', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            calculate();
        }, 300);
        return () => clearTimeout(timer);
    }, [departure, destination, weight, hasCrbt, crbtAmount]);

    return (
        <section id="calculateur" className="py-20 bg-slate-50 relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                {/* Section Header */}
                <div className="text-center max-w-3xl mx-auto mb-14">
                    <Badge variant="primary" size="md" className="mb-3">
                        <Sparkles className="w-3.5 h-3.5 text-[#ff8806]" />
                        <span>Transparence Tarifaire Totale</span>
                    </Badge>
                    <h2 className="text-3xl sm:text-4xl font-extrabold text-[#004c68] font-['Outfit'] tracking-tight">
                        Estimez vos frais d'expédition en quelques secondes
                    </h2>
                    <p className="mt-3 text-slate-600 text-sm sm:text-base">
                        Aucun frais caché, aucun abonnement requis. Bénéficiez des meilleurs tarifs négociés du marché marocain.
                    </p>
                </div>

                {/* Simulator Card */}
                <div className="max-w-4xl mx-auto">
                    <Card className="border-slate-200/90 shadow-xl overflow-hidden rounded-3xl">
                        <div className="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
                            {/* Input Form Column */}
                            <div className="lg:col-span-7 p-6 sm:p-8 space-y-6">
                                <div className="flex items-center gap-2 text-slate-800 font-bold text-base">
                                    <Calculator className="w-5 h-5 text-[#004c68]" />
                                    <span>Paramètres de votre envoi</span>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {/* Departure */}
                                    <div>
                                        <label className="block text-xs font-bold uppercase text-slate-600 tracking-wider mb-2">
                                            Ville de Ramassage
                                        </label>
                                        <select
                                            value={departure}
                                            onChange={(e) => setDeparture(e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-800 focus:border-[#004c68] focus:ring-2 focus:ring-[#004c68]/20 focus:outline-none"
                                        >
                                            {cities.map((city) => (
                                                <option key={city} value={city}>
                                                    {city}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Destination */}
                                    <div>
                                        <label className="block text-xs font-bold uppercase text-slate-600 tracking-wider mb-2">
                                            Ville de Destination
                                        </label>
                                        <select
                                            value={destination}
                                            onChange={(e) => setDestination(e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-800 focus:border-[#004c68] focus:ring-2 focus:ring-[#004c68]/20 focus:outline-none"
                                        >
                                            {cities.map((city) => (
                                                <option key={city} value={city}>
                                                    {city}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                {/* Weight Slider */}
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-xs font-bold">
                                        <span className="uppercase text-slate-600 tracking-wider">
                                            Poids Estimé du Colis
                                        </span>
                                        <span className="text-[#004c68] bg-[#004c68]/10 px-2.5 py-0.5 rounded-full text-xs">
                                            {weight} kg
                                        </span>
                                    </div>
                                    <input
                                        type="range"
                                        min="0.5"
                                        max="20"
                                        step="0.5"
                                        value={weight}
                                        onChange={(e) => setWeight(parseFloat(e.target.value))}
                                        className="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[#ff8806]"
                                    />
                                    <div className="flex justify-between text-[11px] text-slate-400">
                                        <span>0.5 kg (Standard)</span>
                                        <span>5 kg</span>
                                        <span>20 kg (Lourd)</span>
                                    </div>
                                </div>

                                {/* CRBT Option */}
                                <div className="pt-2 border-t border-slate-100 space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <span className="text-sm font-bold text-slate-800 block">
                                                Paiement à la livraison (CRBT)
                                            </span>
                                            <span className="text-xs text-slate-500">
                                                Le livreur encaisse l'argent en espèces auprès du client
                                            </span>
                                        </div>
                                        <input
                                            type="checkbox"
                                            checked={hasCrbt}
                                            onChange={(e) => setHasCrbt(e.target.checked)}
                                            className="w-5 h-5 rounded text-[#ff8806] focus:ring-[#ff8806] cursor-pointer"
                                        />
                                    </div>

                                    {hasCrbt && (
                                        <div className="pl-0 sm:pl-2">
                                            <label className="block text-xs font-semibold text-slate-600 mb-1">
                                                Montant à encaisser (MAD)
                                            </label>
                                            <input
                                                type="number"
                                                value={crbtAmount}
                                                onChange={(e) => setCrbtAmount(e.target.value)}
                                                placeholder="Ex: 350"
                                                className="w-full sm:w-1/2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-[#004c68] focus:ring-2 focus:ring-[#004c68]/20 focus:outline-none"
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Result Summary Column */}
                            <div className="lg:col-span-5 bg-gradient-to-br from-[#0a2533] to-[#05161f] text-white p-6 sm:p-8 flex flex-col justify-between">
                                <div className="space-y-5">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs uppercase font-bold tracking-wider text-slate-400">
                                            Estimation Express
                                        </span>
                                        <Badge variant="secondary" size="sm">
                                            Tarif Préférentiel
                                        </Badge>
                                    </div>

                                    <div>
                                        <span className="text-xs text-slate-400">Frais de livraison total</span>
                                        <div className="flex items-baseline gap-1 mt-1">
                                            <span className="text-4xl sm:text-5xl font-black text-white font-['Outfit']">
                                                {rateResult.total_rate}
                                            </span>
                                            <span className="text-lg font-bold text-[#ff8806]">MAD HT</span>
                                        </div>
                                        <p className="text-xs text-emerald-400 mt-2 flex items-center gap-1.5 font-medium">
                                            <Truck className="w-3.5 h-3.5" />
                                            <span>Délai : {rateResult.estimated_delay}</span>
                                        </p>
                                    </div>

                                    <div className="space-y-2 pt-4 border-t border-slate-700/60 text-xs text-slate-300">
                                        <div className="flex justify-between">
                                            <span>Tarif base ({departure} → {destination})</span>
                                            <span className="font-semibold text-white">{rateResult.base_rate} MAD</span>
                                        </div>
                                        {hasCrbt && (
                                            <div className="flex justify-between">
                                                <span>Frais gestion CRBT</span>
                                                <span className="font-semibold text-white">{rateResult.crbt_fee} MAD</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between">
                                            <span>Ramassage à domicile / local</span>
                                            <span className="font-semibold text-emerald-400">0 MAD (Offert)</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Suivi WhatsApp destinataire</span>
                                            <span className="font-semibold text-emerald-400">Inclus</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="pt-6">
                                    <a href="/register">
                                        <Button variant="secondary" size="lg" className="w-full">
                                            <span>Expédier avec ce tarif</span>
                                            <ArrowRight className="w-4 h-4 ml-2" />
                                        </Button>
                                    </a>
                                    <p className="text-[11px] text-center text-slate-400 mt-2.5">
                                        Tarifs dégressifs disponibles à partir de 30 colis / jour.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </section>
    );
}
