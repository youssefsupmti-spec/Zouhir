import React, { useState, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DataTable } from '@/components/ui/data-table';
import { columns } from '@/Pages/Dashboard/columns';

// Official shadcn/ui components
import {
  Card,
  CardHeader,
  CardFooter,
  CardTitle,
  CardDescription,
  CardContent,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import {
  Tooltip,
  TooltipTrigger,
  TooltipContent,
  TooltipProvider,
} from '@/components/ui/tooltip';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import TrackingSimulatorModal from '@/components/Landing/TrackingSimulatorModal';

import {
  Package,
  Truck,
  CheckCircle2,
  Wallet,
  Plus,
  Search,
  Sparkles,
  Clock,
  ShieldCheck,
  TrendingUp,
} from 'lucide-react';

export default function Dashboard({ stats = {}, recentShipments = [] }) {
    const { auth = {} } = usePage().props;
    const user = auth.user || {};

    const [trackingModalOpen, setTrackingModalOpen] = useState(false);
    const [selectedTrackingCode, setSelectedTrackingCode] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [newParcelModalOpen, setNewParcelModalOpen] = useState(false);

    const openTracking = (code) => {
        setSelectedTrackingCode(code);
        setTrackingModalOpen(true);
    };

    // Format data for Shadcn TanStack Data Table
    const tableData = useMemo(() => {
        const raw = recentShipments.map((colis) => ({
            id: colis.id,
            code: colis.code,
            client: colis.recipient_name,
            phone: colis.recipient_phone,
            city: colis.destination_city,
            amount: `${Number(colis.crbt_amount).toFixed(2)} MAD`,
            status: colis.status === 'livre' ? 'livre' : colis.status === 'en_cours_livraison' ? 'en_cours' : 'transit',
            statusLabel: colis.status_label || (colis.status === 'livre' ? 'Livré & Encaissé' : 'En cours de livraison'),
            date: colis.created_at,
        }));

        if (statusFilter === 'all') return raw;
        if (statusFilter === 'in_transit') return raw.filter(item => item.status === 'en_cours' || item.status === 'transit');
        if (statusFilter === 'delivered') return raw.filter(item => item.status === 'livre');
        return raw;
    }, [recentShipments, statusFilter]);

    return (
        <TooltipProvider>
            <AuthenticatedLayout title="Tableau de bord">
                <Head title="Tableau de bord - OUAISS EXPRESS" />

                {/* Hero Banner with pure shadcn Card */}
                <Card className="mb-6 relative overflow-hidden rounded-lg bg-gradient-to-r from-[#004c68] via-[#072d40] to-[#05161f] text-white shadow-lg border border-white/10 ring-0">
                    <div className="absolute top-0 right-0 w-96 h-96 bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none" />
                    
                    <CardContent className="relative z-10 p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-2">
                            <div className="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-md bg-white/10 text-xs font-semibold text-amber-300 backdrop-blur-md border border-white/10">
                                <Sparkles className="w-3.5 h-3.5 text-[#ff8806]" />
                                <span>Portail Marchand OUAISS EXPRESS 2026</span>
                            </div>
                            <h2 className="text-xl sm:text-2xl font-extrabold font-sans tracking-tight text-white">
                                Ravi de vous revoir, {user.name || 'Marchand'} !
                            </h2>
                            <p className="text-slate-200 text-xs max-w-xl leading-relaxed">
                                Suivez en direct vos expéditions nationales, vos indicateurs de performance logistique et vos remboursements CRBT.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5">
                            <Button
                                variant="secondary"
                                size="default"
                                onClick={() => openTracking('OUA-782941')}
                                className="font-bold shadow-md bg-[#ff8806] hover:bg-[#e07400] text-white rounded-md h-9 px-3.5 text-xs cursor-pointer"
                            >
                                <Search className="w-3.5 h-3.5 mr-1.5" />
                                <span>Suivre un Colis</span>
                            </Button>

                            <Dialog open={newParcelModalOpen} onOpenChange={setNewParcelModalOpen}>
                                <DialogTrigger render={
                                    <Button
                                        variant="outline"
                                        size="default"
                                        className="text-white border-white/20 hover:bg-white/10 font-bold rounded-md h-9 px-3.5 text-xs cursor-pointer"
                                    >
                                        <Plus className="w-3.5 h-3.5 mr-1.5" />
                                        <span>Nouveau Colis</span>
                                    </Button>
                                } />
                                <DialogContent className="sm:max-w-md rounded-lg border-border bg-card text-card-foreground">
                                    <DialogHeader>
                                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                                            <Package className="w-4 h-4 text-[#ff8806]" />
                                            <span>Créer un Colis Express</span>
                                        </DialogTitle>
                                        <DialogDescription className="text-xs text-muted-foreground">
                                            Enregistrez une nouvelle expédition avec ramassage et encaissement CRBT.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="space-y-3 py-2">
                                        <div>
                                            <label className="text-xs font-semibold text-foreground mb-1 block">Nom du Destinataire</label>
                                            <Input placeholder="Ex: Mohamed Alami" className="rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label className="text-xs font-semibold text-foreground mb-1 block">Téléphone Mobile (+212)</label>
                                            <Input placeholder="06XXXXXXXX" className="rounded-md text-xs" />
                                        </div>
                                        <div className="grid grid-cols-2 gap-2.5">
                                            <div>
                                                <label className="text-xs font-semibold text-foreground mb-1 block">Ville de Destination</label>
                                                <Input placeholder="Ex: Casablanca" className="rounded-md text-xs" />
                                            </div>
                                            <div>
                                                <label className="text-xs font-semibold text-foreground mb-1 block">Montant CRBT (MAD)</label>
                                                <Input type="number" placeholder="450.00" className="rounded-md text-xs" />
                                            </div>
                                        </div>
                                    </div>

                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setNewParcelModalOpen(false)} className="rounded-md text-xs">
                                            Annuler
                                        </Button>
                                        <Button
                                            onClick={() => {
                                                setNewParcelModalOpen(false);
                                                alert('Expédition pré-enregistrée sur le réseau OUAISS EXPRESS.');
                                            }}
                                            className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md text-xs font-bold"
                                        >
                                            Valider l'envoi
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardContent>
                </Card>

                {/* 4 Performance KPI Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    {/* Card 1: Total Expédiés */}
                    <Card className="rounded-lg border-border bg-card text-card-foreground shadow-xs hover:border-[#004c68]/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    Total Expédiés
                                </CardTitle>
                                <div className="w-8 h-8 rounded-md bg-[#004c68]/10 text-[#004c68] dark:text-[#ff8806] flex items-center justify-center font-bold">
                                    <Package className="w-4 h-4" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2.5">
                            <div className="text-2xl sm:text-3xl font-extrabold text-foreground font-sans">
                                {stats.total_shipments || 128}
                            </div>
                            <div className="space-y-1">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Progression mensuelle</span>
                                    <span className="font-semibold text-foreground">+18%</span>
                                </div>
                                <Progress value={78} className="h-1.5 rounded-full" />
                            </div>
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground pt-0.5">
                                <TrendingUp className="w-3.5 h-3.5 text-emerald-500" />
                                <span>Volume en forte croissance</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 2: En Distribution */}
                    <Card className="rounded-lg border-border bg-card text-card-foreground shadow-xs hover:border-amber-500/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    En Distribution
                                </CardTitle>
                                <div className="w-8 h-8 rounded-md bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
                                    <Truck className="w-4 h-4" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2.5">
                            <div className="text-2xl sm:text-3xl font-extrabold text-amber-500 font-sans">
                                {stats.in_transit || 14}
                            </div>
                            <div className="space-y-1">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Livreurs actifs</span>
                                    <span className="font-semibold text-foreground">8 tournées</span>
                                </div>
                                <Progress value={35} className="h-1.5 rounded-full" />
                            </div>
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground pt-0.5">
                                <Clock className="w-3.5 h-3.5 text-muted-foreground" />
                                <span>Livraison sous 24h garantie</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 3: Taux de Réussite */}
                    <Card className="rounded-lg border-border bg-card text-card-foreground shadow-xs hover:border-emerald-500/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    Taux de Réussite
                                </CardTitle>
                                <div className="w-8 h-8 rounded-md bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold">
                                    <CheckCircle2 className="w-4 h-4" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2.5">
                            <div className="text-2xl sm:text-3xl font-extrabold text-emerald-500 font-sans">
                                {stats.delivered_rate || stats.success_rate || 98.2}%
                            </div>
                            <div className="space-y-1">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Colis livrés & soldés</span>
                                    <span className="font-semibold text-foreground">{stats.delivered || 112} colis</span>
                                </div>
                                <Progress value={stats.success_rate || 98.2} className="h-1.5 rounded-full" />
                            </div>
                            <div className="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold pt-0.5">
                                <ShieldCheck className="w-3.5 h-3.5" />
                                <span>Performance réseau optimale</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 4: Solde CRBT Net */}
                    <Card className="rounded-lg border-border bg-card text-card-foreground shadow-xs hover:border-[#ff8806]/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    Solde CRBT Net
                                </CardTitle>
                                <div className="w-8 h-8 rounded-md bg-[#ff8806]/10 text-[#ff8806] flex items-center justify-center font-bold">
                                    <Wallet className="w-4 h-4" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2.5">
                            <div className="text-xl sm:text-2xl font-extrabold text-foreground font-sans">
                                {(stats.crbt_balance || 14850).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} <span className="text-xs font-bold text-[#ff8806]">MAD</span>
                            </div>
                            <div className="space-y-1">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Prochain versement</span>
                                    <span className="font-semibold text-foreground">Mardi</span>
                                </div>
                                <Progress value={90} className="h-1.5 rounded-full" />
                            </div>
                            <div className="text-[11px] text-muted-foreground pt-0.5">
                                Virements bancaires automatiques J+1
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Area: Official TanStack Data Table */}
                <Card className="rounded-lg border-border bg-card text-card-foreground shadow-xs overflow-hidden mb-6">
                    <CardHeader className="p-4 sm:p-5 border-b border-border bg-card">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <CardTitle className="text-base font-bold text-foreground">
                                        Expéditions Récentes
                                    </CardTitle>
                                    <Badge variant="outline" className="text-[10px] font-mono border-primary/30 text-primary rounded-md">
                                        TanStack Data Table
                                    </Badge>
                                </div>
                                <CardDescription className="text-xs text-muted-foreground mt-0.5">
                                    Tableau interactif Shadcn/UI : tri multicritère, pagination dynamique et recherche instantanée
                                </CardDescription>
                            </div>

                            {/* Status Filter Tabs using shadcn Tabs */}
                            <Tabs value={statusFilter} onValueChange={setStatusFilter}>
                                <TabsList className="rounded-md p-1 bg-muted/70 h-8">
                                    <TabsTrigger value="all" className="rounded-sm text-xs font-semibold px-2.5 cursor-pointer">
                                        Tous ({recentShipments.length || 4})
                                    </TabsTrigger>
                                    <TabsTrigger value="in_transit" className="rounded-sm text-xs font-semibold px-2.5 cursor-pointer">
                                        En cours (2)
                                    </TabsTrigger>
                                    <TabsTrigger value="delivered" className="rounded-sm text-xs font-semibold px-2.5 cursor-pointer">
                                        Livrés (2)
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>
                    </CardHeader>

                    {/* Official TanStack Table Component */}
                    <CardContent className="p-4 sm:p-5">
                        <DataTable
                            columns={columns}
                            data={tableData}
                            searchPlaceholder="Filtrer par code suivi, destinataire, ville..."
                        />
                    </CardContent>
                </Card>

                {/* Modal de Suivi en Temps Réel */}
                <TrackingSimulatorModal
                    isOpen={trackingModalOpen}
                    onClose={() => setTrackingModalOpen(false)}
                    initialTrackingCode={selectedTrackingCode}
                />
            </AuthenticatedLayout>
        </TooltipProvider>
    );
}
