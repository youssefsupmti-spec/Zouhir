import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent } from '@/components/ui/Card';
import Button from '@/components/ui/AppButton';
import Badge from '@/components/ui/Badge';
import TrackingSimulatorModal from '@/components/Landing/TrackingSimulatorModal';
import { 
    Package, 
    Truck, 
    CheckCircle2, 
    Wallet, 
    Plus, 
    Search, 
    ArrowUpRight, 
    TrendingUp, 
    Sparkles, 
    Clock,
    ShieldAlert,
    RefreshCw
} from 'lucide-react';

export default function Dashboard({ stats = {}, recentShipments = [] }) {
    const { auth = {} } = usePage().props;
    const user = auth.user || {};
    const [trackingModalOpen, setTrackingModalOpen] = useState(false);
    const [selectedTrackingCode, setSelectedTrackingCode] = useState('');
    const [searchTerm, setSearchTerm] = useState('');

    const openTracking = (code) => {
        setSelectedTrackingCode(code);
        setTrackingModalOpen(true);
    };

    const filteredShipments = recentShipments.filter(colis => 
        !searchTerm || 
        colis.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        colis.recipient_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        colis.destination_city.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <AuthenticatedLayout title="Tableau de bord">
            <Head title="Tableau de bord - OUAISS EXPRESS" />

            {/* Welcome Banner */}
            <div className="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#004c68] via-[#072d40] to-[#05161f] p-6 sm:p-8 text-white shadow-xl border border-white/10">
                <div className="absolute top-0 right-0 w-96 h-96 bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none" />
                <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="space-y-2">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-amber-300 backdrop-blur-md border border-white/10">
                            <Sparkles className="w-3.5 h-3.5 text-[#ff8806]" />
                            <span>Portail Marchand 2026</span>
                        </div>
                        <h2 className="text-2xl sm:text-3xl font-extrabold font-['Inter'] tracking-tight text-white">
                            Ravi de vous revoir, {user.name || 'Marchand'} !
                        </h2>
                        <p className="text-slate-200 text-xs sm:text-sm max-w-xl leading-relaxed">
                            Voici le récapitulatif en temps réel de vos expéditions nationales et de vos encaissements CRBT avec OUAISS EXPRESS.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="secondary"
                            size="md"
                            onClick={() => openTracking('OUA-782941')}
                            className="font-bold shadow-lg shadow-[#ff8806]/20 bg-[#ff8806] hover:bg-[#e07400] text-white"
                        >
                            <Search className="w-4 h-4 mr-2" />
                            <span>Suivre un Colis</span>
                        </Button>

                        <Button
                            variant="outline"
                            size="md"
                            onClick={() => alert('Le module de création avancée de colis sera disponible au prochain lot.')}
                            className="text-white border-white/20 hover:bg-white/10 font-bold"
                        >
                            <Plus className="w-4 h-4 mr-1.5" />
                            <span>Nouveau Colis</span>
                        </Button>
                    </div>
                </div>
            </div>

            {/* Metrics Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                {/* Total Expéditions */}
                <Card className="bg-card text-card-foreground border-border hover:border-sidebar-primary/40 hover:shadow-lg transition-all rounded-2xl">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Total Expédiés</span>
                            <div className="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                                <Package className="w-5 h-5 text-[#004c68] dark:text-[#ff8806]" />
                            </div>
                        </div>
                        <div className="text-3xl font-extrabold text-foreground font-['Inter']">
                            {stats.total_shipments || 128}
                        </div>
                        <div className="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 mt-2 font-semibold">
                            <TrendingUp className="w-3.5 h-3.5" />
                            <span>+12.5% vs mois dernier</span>
                        </div>
                    </CardContent>
                </Card>

                {/* En Transit / En cours */}
                <Card className="bg-card text-card-foreground border-border hover:border-amber-500/40 hover:shadow-lg transition-all rounded-2xl">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-muted-foreground uppercase tracking-wider">En Distribution</span>
                            <div className="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
                                <Truck className="w-5 h-5 text-amber-500" />
                            </div>
                        </div>
                        <div className="text-3xl font-extrabold text-amber-500 font-['Inter']">
                            {stats.in_transit || 14}
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-2 font-medium flex items-center gap-1.5">
                            <Clock className="w-3.5 h-3.5 text-muted-foreground" />
                            <span>Livraison prévue sous 24h</span>
                        </p>
                    </CardContent>
                </Card>

                {/* Livrés avec Succès */}
                <Card className="bg-card text-card-foreground border-border hover:border-emerald-500/40 hover:shadow-lg transition-all rounded-2xl">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Taux de Réussite</span>
                            <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold">
                                <CheckCircle2 className="w-5 h-5 text-emerald-500" />
                            </div>
                        </div>
                        <div className="text-3xl font-extrabold text-emerald-500 font-['Inter']">
                            {stats.delivered_rate || 98.2}%
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-2 font-medium">
                            {stats.delivered_count || 112} colis livrés & encaissés
                        </p>
                    </CardContent>
                </Card>

                {/* Solde CRBT */}
                <Card className="bg-card text-card-foreground border-border hover:border-[#ff8806]/40 hover:shadow-lg transition-all rounded-2xl">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Solde CRBT Net</span>
                            <div className="w-10 h-10 rounded-xl bg-[#ff8806]/10 text-[#ff8806] flex items-center justify-center font-bold">
                                <Wallet className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="text-2xl sm:text-3xl font-extrabold text-foreground font-['Inter']">
                            {(stats.crbt_balance || 14850).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} <span className="text-sm font-bold text-[#ff8806]">MAD</span>
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-2 font-medium">
                            Prochain virement bancaire : <span className="text-foreground font-semibold">Mardi</span>
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Recent Shipments Table Card */}
            <Card className="bg-card text-card-foreground border-border shadow-md rounded-2xl overflow-hidden mb-8">
                <div className="p-5 sm:p-6 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 className="text-lg font-bold text-foreground font-['Inter']">
                            Expéditions Récentes
                        </h3>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Derniers colis enregistrés sur le réseau OUAISS EXPRESS
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Filtrer par code, ville..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 pr-3 py-1.5 rounded-xl border border-border bg-background text-foreground text-xs placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-[#ff8806]/40 w-44 sm:w-56"
                            />
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => openTracking('')}
                            className="text-xs font-bold border-border text-foreground hover:bg-muted"
                        >
                            <Search className="w-3.5 h-3.5 mr-1 text-[#ff8806]" />
                            <span>Vérifier un code</span>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-muted/50 text-muted-foreground font-semibold border-b border-border">
                            <tr>
                                <th className="px-6 py-3.5">Code Suivi</th>
                                <th className="px-6 py-3.5">Destinataire</th>
                                <th className="px-6 py-3.5">Ville</th>
                                <th className="px-6 py-3.5">Montant CRBT</th>
                                <th className="px-6 py-3.5">Statut</th>
                                <th className="px-6 py-3.5">Date</th>
                                <th className="px-6 py-3.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border font-medium">
                            {filteredShipments.map((colis) => (
                                <tr key={colis.id} className="hover:bg-muted/40 transition-colors">
                                    <td className="px-6 py-4 font-bold text-[#004c68] dark:text-[#ff8806]">
                                        <button 
                                            onClick={() => openTracking(colis.code)}
                                            className="hover:underline flex items-center gap-1.5 font-mono"
                                        >
                                            <span>{colis.code}</span>
                                            <ArrowUpRight className="w-3 h-3 opacity-60" />
                                        </button>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="font-semibold text-foreground">{colis.recipient_name}</div>
                                        <div className="text-[11px] text-muted-foreground font-mono">{colis.recipient_phone}</div>
                                    </td>
                                    <td className="px-6 py-4 text-foreground/80">{colis.destination_city}</td>
                                    <td className="px-6 py-4 font-bold text-foreground">
                                        {colis.crbt_amount.toFixed(2)} <span className="text-[10px] text-muted-foreground font-normal">MAD</span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <Badge 
                                            variant={
                                                colis.status === 'livre' ? 'success' :
                                                colis.status === 'en_cours_livraison' ? 'secondary' : 'primary'
                                            }
                                            size="sm"
                                            dot={true}
                                        >
                                            {colis.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-6 py-4 text-muted-foreground text-[11px]">
                                        {colis.created_at}
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => openTracking(colis.code)}
                                            className="text-xs text-[#004c68] dark:text-[#ff8806] font-bold hover:bg-muted"
                                        >
                                            Suivi
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {/* Tracking Modal */}
            <TrackingSimulatorModal
                isOpen={trackingModalOpen}
                onClose={() => setTrackingModalOpen(false)}
                initialCode={selectedTrackingCode}
            />
        </AuthenticatedLayout>
    );
}
