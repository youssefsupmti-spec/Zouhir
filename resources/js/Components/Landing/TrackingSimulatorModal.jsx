import React, { useState } from 'react';
import Modal from '@/Components/ui/Modal';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import { Search, PackageCheck, Truck, MapPin, CheckCircle2, Clock, AlertCircle } from 'lucide-react';

export default function TrackingSimulatorModal({ isOpen, onClose, initialCode = '' }) {
    const [code, setCode] = useState(initialCode || 'OUA-782941');
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');

    const handleSearch = async (e) => {
        if (e) e.preventDefault();
        if (!code.trim()) {
            setError('Veuillez entrer un numéro de suivi valide.');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const response = await fetch('/api/tracking/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ tracking_code: code }),
            });

            const data = await response.json();
            if (response.ok && data.success) {
                setResult(data);
            } else {
                setError(data.message || 'Numéro de suivi introuvable.');
            }
        } catch (err) {
            setError('Une erreur est survenue lors de la recherche.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Suivi de Colis en Temps Réel"
            description="Entrez votre numéro de bordereau Ouais Express pour consulter l'acheminement"
            maxWidth="max-w-2xl"
        >
            <div className="space-y-6">
                {/* Search Bar */}
                <form onSubmit={handleSearch} className="flex gap-2">
                    <div className="relative flex-1">
                        <Search className="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            value={code}
                            onChange={(e) => setCode(e.target.value.toUpperCase())}
                            placeholder="Ex: OUA-782941, ZOU-10492..."
                            className="w-full pl-11 pr-4 py-2.5 text-sm uppercase font-semibold text-slate-800 rounded-xl border border-slate-200 focus:border-[#004c68] focus:ring-2 focus:ring-[#004c68]/20 focus:outline-none"
                        />
                    </div>
                    <Button type="submit" variant="primary" isLoading={loading}>
                        Rechercher
                    </Button>
                </form>

                {error && (
                    <div className="flex items-center gap-2 p-3 bg-red-50 text-red-700 text-xs rounded-xl border border-red-100">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />
                        <span>{error}</span>
                    </div>
                )}

                {/* Tracking Result View */}
                {result && (
                    <div className="space-y-5 animate-in fade-in duration-200">
                        {/* Summary Header */}
                        <div className="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                    Numéro de Suivi
                                </span>
                                <h4 className="text-lg font-black text-[#004c68] font-['Outfit']">
                                    {result.tracking_code}
                                </h4>
                                <p className="text-xs text-slate-600 mt-0.5 flex items-center gap-1.5">
                                    <MapPin className="w-3.5 h-3.5 text-[#ff8806]" />
                                    <span>Destination : {result.recipient_city}</span>
                                </p>
                            </div>

                            <div className="text-left sm:text-right">
                                <Badge variant="secondary" size="lg" dot={true}>
                                    {result.current_status}
                                </Badge>
                                <p className="text-[11px] text-slate-500 mt-1 flex items-center sm:justify-end gap-1">
                                    <Clock className="w-3.5 h-3.5" />
                                    <span>Livraison estimée : <strong>{result.eta}</strong></span>
                                </p>
                            </div>
                        </div>

                        {/* Interactive Timeline */}
                        <div className="space-y-4 pt-2">
                            <h5 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Historique d'acheminement
                            </h5>
                            <div className="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                                {result.timeline.map((step, idx) => (
                                    <div key={idx} className="relative group">
                                        {/* Status Dot */}
                                        <div className={`absolute -left-6 top-1 w-5 h-5 rounded-full flex items-center justify-center transition-colors ${
                                            step.current
                                                ? 'bg-[#ff8806] ring-4 ring-[#ff8806]/20 text-white'
                                                : step.completed
                                                ? 'bg-[#004c68] text-white'
                                                : 'bg-slate-200 text-slate-400'
                                        }`}>
                                            {step.completed ? (
                                                <CheckCircle2 className="w-3.5 h-3.5" />
                                            ) : (
                                                <span className="w-2 h-2 rounded-full bg-slate-400" />
                                            )}
                                        </div>

                                        <div className="bg-white p-3.5 rounded-xl border border-slate-100 hover:border-slate-200 shadow-xs transition-all">
                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                                <h6 className="text-sm font-bold text-slate-800">
                                                    {step.status}
                                                </h6>
                                                <span className="text-[11px] font-semibold text-slate-400">
                                                    {step.date}
                                                </span>
                                            </div>
                                            <p className="text-xs text-slate-600 mt-1">
                                                {step.description}
                                            </p>
                                            <div className="flex items-center gap-1 text-[11px] text-slate-400 mt-2 font-medium">
                                                <MapPin className="w-3 h-3 text-[#004c68]" />
                                                <span>{step.location}</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}
