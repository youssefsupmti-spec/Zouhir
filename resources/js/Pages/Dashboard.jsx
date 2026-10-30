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
    Clock, 
    Sparkles, 
    FileText 
} from 'lucide-react';

export default function Dashboard({ stats = {}, recentShipments = [] }) {
    const { auth = {} } = usePage().props;
    const user = auth.user || {};
    const [trackingModalOpen, setTrackingModalOpen] = useState(false);
    const [selectedTrackingCode, setSelectedTrackingCode] = useState('');

    const openTracking = (code) => {
        setSelectedTrackingCode(code);
        setTrackingModalOpen(true);
    };

    return (
        <AuthenticatedLayout title="Tableau de bord">
            <Head title="Tableau de bord - OUAISS EXPRESS" />

            {/* Welcome Banner */}
            <div className="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#004c68] via-[#072432] to-[#05161f] p-8 text-white shadow-xl">
                <div className="absolute top-0 right-0 w-96 h-96 bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none" />
                <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="space-y-2">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-amber-300 backdrop-blur-md">
                            <Sparkles className="w-3.5 h-3.5" />
                            <span>Portail Marchand 2026</span>
                        </div>
                        <h2 className="text-2xl md:text-3xl font-extrabold font-['Inter'] tracking-tight">
                            Ravi de vous revoir, {user.name} !
                        </h2>
                        <p className="text-slate-300 text-xs md:text-sm max-w-xl">
                            Voici le récapitulatif en direct de vos expéditions nationales et de vos encaissements CRBT avec OUAISS EXPRESS.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="secondary"
                            size="md"
                            onClick={() => openTracking('OUA-782941')}
                            className="font-bold shadow-lg shadow-[#ff8806]/20"
                        >
                            <Search className="w-4 h-4 mr-2" />
                            <span>Suivre un Colis</span>
                        </Button>

                        <Button
                            variant="outline"
                            size="md"
                            onClick={() => alert('Le module de création avancée de colis sera déployé lors du prochain sprint.')}
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
                <Card className="hover:shadow-md transition-shadow border-slate-200">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Expédiés</span>
                            <div className="w-10 h-10 rounded-xl bg-[#004c68]/10 text-[#004c68] flex items-center justify-center">
                                <Package className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="text-3xl font-extrabold text-slate-900 font-['Inter']">
                            {stats.total_shipments || 0}
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">Colis enregistrés ce mois</p>
                    </CardContent>
                </Card>

                {/* En Transit / En cours */}
                <Card className="hover:shadow-md transition-shadow border-slate-200">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">En Distribution</span>
                            <div className="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                                <Truck className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="text-3xl font-extrabold text-amber-600 font-['Inter']">
                            {stats.in_transit || 0}
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">Livraison prévue sous 24h</p>
                    </CardContent>
                </Card>

                {/* Livrés avec Succès */}
                <Card className="hover:shadow-md transition-shadow border-slate-200">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Taux de Réussite</span>
                            <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                                <CheckCircle2 className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="text-3xl font-extrabold text-emerald-600 font-['Inter']">
                            {stats.success_rate || 98.2}%
                        </div>
                        <p className="text-[11px] text-emerald-700 font-semibold mt-1">
                            {stats.delivered || 0} colis livrés & encaissés
                        </p>
                    </CardContent>
                </Card>

                {/* Solde CRBT Disponible */}
                <Card className="hover:shadow-md transition-shadow border-slate-200 bg-gradient-to-br from-white to-orange-50/30">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Solde CRBT Net</span>
                            <div className="w-10 h-10 rounded-xl bg-[#ff8806]/15 text-[#ff8806] flex items-center justify-center">
                                <Wallet className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="text-3xl font-extrabold text-slate-900 font-['Inter']">
                            {Number(stats.crbt_balance || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} <span className="text-sm font-bold text-[#ff8806]">MAD</span>
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">
                            Prochain virement bancaire : <span className="font-semibold text-slate-700">Mardi</span>
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Recent Shipments Table */}
            <Card className="border-slate-200 shadow-sm">
                <div className="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 className="text-base font-bold text-slate-900">Expéditions Récentes</h3>
                        <p className="text-xs text-slate-500">Derniers colis enregistrés sur le réseau Ouais Express</p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => openTracking('OUA-782941')}
                            className="text-xs font-semibold"
                        >
                            <Search className="w-3.5 h-3.5 mr-1.5 text-[#004c68]" />
                            <span>Vérifier un code</span>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50/80 text-slate-500 font-semibold border-b border-slate-100">
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
                        <tbody className="divide-y divide-slate-100 font-medium">
                            {recentShipments.map((colis) => (
                                <tr key={colis.id} className="hover:bg-slate-50/60 transition-colors">
                                    <td className="px-6 py-4 font-bold text-[#004c68]">
                                        <button 
                                            onClick={() => openTracking(colis.code)}
                                            className="hover:underline flex items-center gap-1.5"
                                        >
                                            <span>{colis.code}</span>
                                            <ArrowUpRight className="w-3 h-3 text-slate-400" />
                                        </button>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="font-semibold text-slate-900">{colis.recipient_name}</div>
                                        <div className="text-[11px] text-slate-400">{colis.recipient_phone}</div>
                                    </td>
                                    <td className="px-6 py-4 text-slate-700">{colis.destination_city}</td>
                                    <td className="px-6 py-4 font-bold text-slate-900">
                                        {colis.crbt_amount.toFixed(2)} MAD
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
                                    <td className="px-6 py-4 text-slate-500 text-[11px]">
                                        {colis.created_at}
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => openTracking(colis.code)}
                                            className="text-xs text-[#004c68] font-bold"
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
