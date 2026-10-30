import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Search, PackageCheck, Truck, MapPin, CheckCircle2, Clock, AlertCircle } from 'lucide-react';

export default function TrackingSimulatorModal({ isOpen, onClose, initialTrackingCode = '', initialCode = '' }) {
    const defaultCode = initialTrackingCode || initialCode || 'OUA-782941';
    const [code, setCode] = useState(defaultCode);
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
                // Simulation dynamique si l'API renvoie 404 (pour démo interactive immédiate)
                setResult({
                    tracking_code: code,
                    recipient_city: 'Casablanca (Maârif)',
                    current_status: 'En cours de distribution',
                    eta: 'Aujourd\'hui avant 18h00',
                    timeline: [
                        { status: 'Colis ramassé au hub', location: 'Hub Casablanca', date: '30/10/2026 08:30', completed: true },
                        { status: 'Tri & Expédition inter-agences', location: 'Plateforme Centrale', date: '30/10/2026 11:15', completed: true },
                        { status: 'Assigné au coursier de zone', location: 'Casablanca Centre', date: '30/10/2026 14:00', current: true },
                        { status: 'Livraison finale & Encaissement CRBT', location: 'Adresse Destinataire', date: 'En attente', completed: false },
                    ]
                });
            }
        } catch (err) {
            setError('Une erreur est survenue lors de la recherche.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="rounded-lg bg-card text-card-foreground border-border shadow-2xl p-6 sm:max-w-xl">
                <DialogHeader className="space-y-1 pb-3 border-b border-border/60">
                    <DialogTitle className="text-base sm:text-lg font-bold text-foreground flex items-center gap-2">
                        <Truck className="size-5 text-primary" />
                        <span>Suivi de Colis en Temps Réel</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Entrez votre numéro d'expédition Ouais Express pour consulter l'acheminement en direct.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-5 pt-3">
                    {/* Search Bar using shadcn Input & Button */}
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <div className="relative flex-1">
                            <Search className="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="text"
                                value={code}
                                onChange={(e) => setCode(e.target.value.toUpperCase())}
                                placeholder="Ex: OUA-782941, OIS-11831..."
                                className="pl-9 text-xs uppercase font-mono font-bold tracking-wider rounded-md"
                            />
                        </div>
                        <Button type="submit" variant="default" size="default" disabled={loading} className="rounded-md text-xs font-semibold">
                            {loading ? 'Recherche...' : 'Rechercher'}
                        </Button>
                    </form>

                    {error && (
                        <div className="flex items-center gap-2 p-3 bg-destructive/10 text-destructive text-xs rounded-md border border-destructive/20 font-medium">
                            <AlertCircle className="size-4 shrink-0" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Tracking Result View */}
                    {result && (
                        <div className="space-y-4 animate-in fade-in duration-200">
                            {/* Summary Card with pure shadcn Card */}
                            <Card className="rounded-md border-border bg-muted/40 shadow-none">
                                <CardContent className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div className="space-y-1">
                                        <span className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider font-mono">
                                            Code Suivi National
                                        </span>
                                        <h4 className="text-base font-extrabold text-foreground font-mono">
                                            {result.tracking_code}
                                        </h4>
                                        <p className="text-xs text-muted-foreground flex items-center gap-1.5">
                                            <MapPin className="size-3.5 text-primary" />
                                            <span>Destination : <strong className="text-foreground">{result.recipient_city}</strong></span>
                                        </p>
                                    </div>

                                    <div className="text-left sm:text-right space-y-1">
                                        <Badge variant="outline" className="bg-primary/10 text-primary border-primary/20 text-xs font-semibold">
                                            {result.current_status}
                                        </Badge>
                                        <p className="text-[11px] text-muted-foreground flex items-center sm:justify-end gap-1">
                                            <Clock className="size-3 text-muted-foreground" />
                                            <span>Estimé : <strong className="text-foreground">{result.eta}</strong></span>
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Interactive Timeline */}
                            <div className="space-y-3 pt-1">
                                <h5 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    Historique des jalons logistiques
                                </h5>
                                <div className="relative pl-5 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-border">
                                    {result.timeline.map((step, idx) => (
                                        <div key={idx} className="relative group">
                                            <div className={`absolute -left-5 top-1 size-4 rounded-full flex items-center justify-center transition-colors ${
                                                step.current
                                                    ? 'bg-primary ring-3 ring-primary/20 text-primary-foreground'
                                                    : step.completed
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'bg-muted border border-border text-muted-foreground'
                                            }`}>
                                                {step.completed ? (
                                                    <CheckCircle2 className="size-3" />
                                                ) : (
                                                    <span className="size-1.5 rounded-full bg-current" />
                                                )}
                                            </div>

                                            <div className="rounded-md border border-border/70 bg-card p-3 shadow-xs">
                                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                                    <h6 className="text-xs font-bold text-foreground">
                                                        {step.status}
                                                    </h6>
                                                    <span className="text-[10px] font-mono text-muted-foreground">
                                                        {step.date}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1 text-[11px] text-muted-foreground mt-1">
                                                    <MapPin className="size-3 text-muted-foreground" />
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
            </DialogContent>
        </Dialog>
    );
}
