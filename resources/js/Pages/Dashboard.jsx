import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

// Official shadcn/ui components
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  CardFooter,
} from '@/components/ui/card';
import {
  Table,
  TableHeader,
  TableBody,
  TableHead,
  TableRow,
  TableCell,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Tabs,
  TabsList,
  TabsTrigger,
  TabsContent,
} from '@/components/ui/tabs';
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
} from '@/components/ui/dropdown-menu';
import {
  Tooltip,
  TooltipTrigger,
  TooltipContent,
  TooltipProvider,
} from '@/components/ui/tooltip';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
  MoreHorizontal,
  FileText,
  Copy,
  ExternalLink,
  ShieldCheck,
  Phone,
  Filter
} from 'lucide-react';

export default function Dashboard({ stats = {}, recentShipments = [] }) {
    const { auth = {} } = usePage().props;
    const user = auth.user || {};

    const [trackingModalOpen, setTrackingModalOpen] = useState(false);
    const [selectedTrackingCode, setSelectedTrackingCode] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [newParcelModalOpen, setNewParcelModalOpen] = useState(false);
    const [copiedCode, setCopiedCode] = useState('');

    const openTracking = (code) => {
        setSelectedTrackingCode(code);
        setTrackingModalOpen(true);
    };

    const copyToClipboard = (code) => {
        navigator.clipboard.writeText(code);
        setCopiedCode(code);
        setTimeout(() => setCopiedCode(''), 2000);
    };

    const filteredShipments = recentShipments.filter(colis => {
        const matchesSearch = !searchTerm || 
            colis.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
            colis.recipient_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            colis.destination_city.toLowerCase().includes(searchTerm.toLowerCase());

        if (!matchesSearch) return false;

        if (statusFilter === 'all') return true;
        if (statusFilter === 'in_transit') return colis.status === 'en_cours_livraison';
        if (statusFilter === 'delivered') return colis.status === 'livre';
        return true;
    });

    return (
        <TooltipProvider>
            <AuthenticatedLayout title="Tableau de bord">
                <Head title="Tableau de bord - OUAISS EXPRESS" />

                {/* Hero Banner with pure shadcn Card */}
                <Card className="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#004c68] via-[#072d40] to-[#05161f] text-white shadow-xl border border-white/10 ring-0">
                    <div className="absolute top-0 right-0 w-96 h-96 bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none" />
                    
                    <CardContent className="relative z-10 p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-2.5">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-amber-300 backdrop-blur-md border border-white/10">
                                <Sparkles className="w-3.5 h-3.5 text-[#ff8806]" />
                                <span>Portail Marchand OUAISS EXPRESS 2026</span>
                            </div>
                            <h2 className="text-2xl sm:text-3xl font-extrabold font-['Inter'] tracking-tight text-white">
                                Ravi de vous revoir, {user.name || 'Marchand'} !
                            </h2>
                            <p className="text-slate-200 text-xs sm:text-sm max-w-xl leading-relaxed">
                                Suivez en temps réel l'ensemble de vos expéditions nationales, vos taux de livraison et vos remboursements CRBT.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                variant="secondary"
                                size="default"
                                onClick={() => openTracking('OUA-782941')}
                                className="font-bold shadow-lg shadow-[#ff8806]/25 bg-[#ff8806] hover:bg-[#e07400] text-white rounded-xl h-10 px-4"
                            >
                                <Search className="w-4 h-4 mr-2" />
                                <span>Suivre un Colis</span>
                            </Button>

                            <Dialog open={newParcelModalOpen} onOpenChange={setNewParcelModalOpen}>
                                <DialogTrigger render={
                                    <Button
                                        variant="outline"
                                        size="default"
                                        className="text-white border-white/20 hover:bg-white/10 font-bold rounded-xl h-10 px-4"
                                    >
                                        <Plus className="w-4 h-4 mr-1.5" />
                                        <span>Nouveau Colis</span>
                                    </Button>
                                } />
                                <DialogContent className="sm:max-w-md rounded-2xl border-border bg-card text-card-foreground">
                                    <DialogHeader>
                                        <DialogTitle className="text-lg font-bold flex items-center gap-2">
                                            <Package className="w-5 h-5 text-[#ff8806]" />
                                            <span>Créer un Colis Express</span>
                                        </DialogTitle>
                                        <DialogDescription className="text-xs text-muted-foreground">
                                            Enregistrez une nouvelle expédition avec ramassage et encaissement CRBT.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="space-y-3.5 py-3">
                                        <div>
                                            <label className="text-xs font-semibold text-foreground mb-1 block">Nom du Destinataire</label>
                                            <Input placeholder="Ex: Mohamed Alami" className="rounded-xl text-xs" />
                                        </div>
                                        <div>
                                            <label className="text-xs font-semibold text-foreground mb-1 block">Téléphone Mobile (+212)</label>
                                            <Input placeholder="06XXXXXXXX" className="rounded-xl text-xs" />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                <label className="text-xs font-semibold text-foreground mb-1 block">Ville de Destination</label>
                                                <Input placeholder="Casablanca, Rabat..." className="rounded-xl text-xs" />
                                            </div>
                                            <div>
                                                <label className="text-xs font-semibold text-foreground mb-1 block">Montant CRBT (MAD)</label>
                                                <Input type="number" placeholder="450.00" className="rounded-xl text-xs font-mono" />
                                            </div>
                                        </div>
                                    </div>

                                    <DialogFooter className="gap-2 sm:gap-0">
                                        <DialogClose render={
                                            <Button variant="ghost" size="sm" className="rounded-xl text-xs">
                                                Annuler
                                            </Button>
                                        } />
                                        <Button 
                                            variant="secondary" 
                                            size="sm"
                                            onClick={() => {
                                                setNewParcelModalOpen(false);
                                                alert('Expédition pré-enregistrée avec succès sur le Hub de ramassage.');
                                            }}
                                            className="bg-[#ff8806] hover:bg-[#e07400] text-white font-bold rounded-xl text-xs"
                                        >
                                            Confirmer l'Expédition
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardContent>
                </Card>

                {/* 4 KPI Metrics with shadcn Cards + Progress */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    {/* Card 1: Total Expédiés */}
                    <Card className="rounded-2xl border-border bg-card text-card-foreground shadow-sm hover:shadow-md hover:border-primary/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    Total Expédiés
                                </CardTitle>
                                <div className="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                                    <Package className="w-4 h-4 text-[#004c68] dark:text-[#ff8806]" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="text-3xl font-extrabold text-foreground font-['Inter']">
                                {stats.total_shipments || 128}
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Objectif mensuel</span>
                                    <span className="font-semibold text-foreground">128 / 160</span>
                                </div>
                                <Progress value={80} className="h-1.5 rounded-full" />
                            </div>
                            <div className="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold pt-1">
                                <TrendingUp className="w-3.5 h-3.5" />
                                <span>+12.5% vs mois dernier</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 2: En Distribution */}
                    <Card className="rounded-2xl border-border bg-card text-card-foreground shadow-sm hover:shadow-md hover:border-amber-500/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    En Distribution
                                </CardTitle>
                                <div className="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
                                    <Truck className="w-4 h-4" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="text-3xl font-extrabold text-amber-500 font-['Inter']">
                                {stats.in_transit || 14}
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Livreurs actifs</span>
                                    <span className="font-semibold text-foreground">8 tournées</span>
                                </div>
                                <Progress value={35} className="h-1.5 rounded-full" />
                            </div>
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground pt-1">
                                <Clock className="w-3.5 h-3.5 text-muted-foreground" />
                                <span>Livraison sous 24h garantie</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 3: Taux de Réussite */}
                    <Card className="rounded-2xl border-border bg-card text-card-foreground shadow-sm hover:shadow-md hover:border-emerald-500/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    Taux de Réussite
                                </CardTitle>
                                <div className="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold">
                                    <CheckCircle2 className="w-4 h-4" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="text-3xl font-extrabold text-emerald-500 font-['Inter']">
                                {stats.delivered_rate || 98.2}%
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Colis livrés & soldés</span>
                                    <span className="font-semibold text-foreground">112 colis</span>
                                </div>
                                <Progress value={98.2} className="h-1.5 rounded-full" />
                            </div>
                            <div className="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold pt-1">
                                <ShieldCheck className="w-3.5 h-3.5" />
                                <span>Performance réseau optimale</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 4: Solde CRBT Net */}
                    <Card className="rounded-2xl border-border bg-card text-card-foreground shadow-sm hover:shadow-md hover:border-[#ff8806]/40 transition-all">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                    Solde CRBT Net
                                </CardTitle>
                                <div className="w-9 h-9 rounded-xl bg-[#ff8806]/10 text-[#ff8806] flex items-center justify-center font-bold">
                                    <Wallet className="w-4 h-4" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="text-2xl sm:text-3xl font-extrabold text-foreground font-['Inter']">
                                {(stats.crbt_balance || 14850).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} <span className="text-xs sm:text-sm font-bold text-[#ff8806]">MAD</span>
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Prochain versement</span>
                                    <span className="font-semibold text-foreground">Mardi</span>
                                </div>
                                <Progress value={90} className="h-1.5 rounded-full" />
                            </div>
                            <div className="text-[11px] text-muted-foreground pt-1">
                                Virements bancaires automatiques J+1
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Area: shadcn Tabs & Table */}
                <Card className="rounded-2xl border-border bg-card text-card-foreground shadow-sm overflow-hidden mb-8">
                    <CardHeader className="p-5 sm:p-6 border-b border-border">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <CardTitle className="text-lg font-bold text-foreground">
                                    Expéditions Récentes
                                </CardTitle>
                                <CardDescription className="text-xs text-muted-foreground mt-0.5">
                                    Gestion et suivi des expéditions enregistrées sur le réseau OUAISS EXPRESS
                                </CardDescription>
                            </div>

                            {/* Search and Quick Tracking */}
                            <div className="flex items-center gap-2.5">
                                <div className="relative">
                                    <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="text"
                                        placeholder="Rechercher code, ville, client..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="pl-9 pr-3 py-1.5 h-9 rounded-xl text-xs w-48 sm:w-64"
                                    />
                                </div>

                                <Tooltip>
                                    <TooltipTrigger render={
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => openTracking('')}
                                            className="h-9 rounded-xl text-xs font-semibold gap-1.5"
                                        >
                                            <Search className="w-3.5 h-3.5 text-[#ff8806]" />
                                            <span className="hidden sm:inline">Vérifier code</span>
                                        </Button>
                                    } />
                                    <TooltipContent>Ouvrir le simulateur de suivi</TooltipContent>
                                </Tooltip>
                            </div>
                        </div>

                        {/* Status Filter Tabs using shadcn Tabs */}
                        <div className="pt-4">
                            <Tabs value={statusFilter} onValueChange={setStatusFilter}>
                                <TabsList className="rounded-xl p-1 bg-muted/60 h-9">
                                    <TabsTrigger value="all" className="rounded-lg text-xs font-semibold px-3">
                                        Tous les colis ({recentShipments.length || 4})
                                    </TabsTrigger>
                                    <TabsTrigger value="in_transit" className="rounded-lg text-xs font-semibold px-3">
                                        En cours (1)
                                    </TabsTrigger>
                                    <TabsTrigger value="delivered" className="rounded-lg text-xs font-semibold px-3">
                                        Livrés & Encaissés (2)
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>
                    </CardHeader>

                    {/* Table constructed entirely from official shadcn Table */}
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-b border-border bg-muted/40 hover:bg-muted/40">
                                    <TableHead className="px-5 py-3 text-xs font-bold text-muted-foreground">Code Suivi</TableHead>
                                    <TableHead className="px-5 py-3 text-xs font-bold text-muted-foreground">Destinataire</TableHead>
                                    <TableHead className="px-5 py-3 text-xs font-bold text-muted-foreground">Ville</TableHead>
                                    <TableHead className="px-5 py-3 text-xs font-bold text-muted-foreground">Montant CRBT</TableHead>
                                    <TableHead className="px-5 py-3 text-xs font-bold text-muted-foreground">Statut</TableHead>
                                    <TableHead className="px-5 py-3 text-xs font-bold text-muted-foreground">Date</TableHead>
                                    <TableHead className="px-5 py-3 text-xs font-bold text-muted-foreground text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredShipments.map((colis) => {
                                    const isDelivered = colis.status === 'livre';
                                    const isInTransit = colis.status === 'en_cours_livraison';

                                    return (
                                        <TableRow key={colis.id} className="border-b border-border/60 hover:bg-muted/30 transition-colors">
                                            {/* Code */}
                                            <TableCell className="px-5 py-3.5 font-bold">
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openTracking(colis.code)}
                                                        className="font-mono text-xs text-[#004c68] dark:text-[#ff8806] hover:underline font-bold flex items-center gap-1"
                                                    >
                                                        <span>{colis.code}</span>
                                                        <ArrowUpRight className="w-3 h-3 opacity-60" />
                                                    </button>
                                                    <Tooltip>
                                                        <TooltipTrigger render={
                                                            <button
                                                                type="button"
                                                                onClick={() => copyToClipboard(colis.code)}
                                                                className="text-muted-foreground hover:text-foreground transition-colors p-1"
                                                            >
                                                                <Copy className="w-3 h-3" />
                                                            </button>
                                                        } />
                                                        <TooltipContent>
                                                            {copiedCode === colis.code ? 'Copié !' : 'Copier le code'}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            </TableCell>

                                            {/* Destinataire */}
                                            <TableCell className="px-5 py-3.5">
                                                <div className="flex items-center gap-2.5">
                                                    <Avatar className="h-7 w-7 rounded-lg text-[10px] font-bold bg-muted flex items-center justify-center">
                                                        <AvatarFallback>{colis.recipient_name.substring(0, 2).toUpperCase()}</AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="font-semibold text-xs text-foreground">{colis.recipient_name}</div>
                                                        <div className="text-[11px] text-muted-foreground font-mono">{colis.recipient_phone}</div>
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Ville */}
                                            <TableCell className="px-5 py-3.5 text-xs text-foreground/90 font-medium">
                                                {colis.destination_city}
                                            </TableCell>

                                            {/* Montant CRBT */}
                                            <TableCell className="px-5 py-3.5 font-bold font-mono text-xs text-foreground">
                                                {colis.crbt_amount.toFixed(2)} <span className="text-[10px] text-muted-foreground font-normal">MAD</span>
                                            </TableCell>

                                            {/* Statut avec Badge shadcn */}
                                            <TableCell className="px-5 py-3.5">
                                                {isDelivered ? (
                                                    <Badge variant="outline" className="rounded-full bg-emerald-500/15 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px] gap-1 px-2.5 py-0.5">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block" />
                                                        <span>{colis.status_label}</span>
                                                    </Badge>
                                                ) : isInTransit ? (
                                                    <Badge variant="secondary" className="rounded-full bg-[#ff8806]/15 border-[#ff8806]/30 text-[#ff8806] font-semibold text-[11px] gap-1 px-2.5 py-0.5">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-[#ff8806] inline-block animate-pulse" />
                                                        <span>{colis.status_label}</span>
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="rounded-full text-muted-foreground font-semibold text-[11px] gap-1 px-2.5 py-0.5">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-slate-400 inline-block" />
                                                        <span>{colis.status_label}</span>
                                                    </Badge>
                                                )}
                                            </TableCell>

                                            {/* Date */}
                                            <TableCell className="px-5 py-3.5 text-[11px] text-muted-foreground">
                                                {colis.created_at}
                                            </TableCell>

                                            {/* Dropdown Menu actions shadcn */}
                                            <TableCell className="px-5 py-3.5 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger render={
                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-lg">
                                                            <MoreHorizontal className="w-4 h-4 text-muted-foreground" />
                                                        </Button>
                                                    } />
                                                    <DropdownMenuContent align="end" className="w-48 rounded-xl border-border bg-card text-card-foreground shadow-lg">
                                                        <DropdownMenuLabel className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                                            Actions Colis
                                                        </DropdownMenuLabel>
                                                        <DropdownMenuItem 
                                                            onClick={() => openTracking(colis.code)}
                                                            className="gap-2 cursor-pointer text-xs font-semibold"
                                                        >
                                                            <Search className="w-3.5 h-3.5 text-[#ff8806]" />
                                                            <span>Suivre en direct</span>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem 
                                                            onClick={() => copyToClipboard(colis.code)}
                                                            className="gap-2 cursor-pointer text-xs"
                                                        >
                                                            <Copy className="w-3.5 h-3.5" />
                                                            <span>Copier le numéro</span>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem 
                                                            onClick={() => alert(`Téléchargement du bordereau pour ${colis.code}`)}
                                                            className="gap-2 cursor-pointer text-xs"
                                                        >
                                                            <FileText className="w-3.5 h-3.5" />
                                                            <span>Bordereau PDF</span>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>

                    <CardFooter className="p-4 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                        <div>
                            Affichage de <span className="font-semibold text-foreground">{filteredShipments.length}</span> sur <span className="font-semibold text-foreground">{recentShipments.length}</span> expéditions
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" className="rounded-lg text-xs h-8" disabled>
                                Précédent
                            </Button>
                            <Button variant="outline" size="sm" className="rounded-lg text-xs h-8" disabled>
                                Suivant
                            </Button>
                        </div>
                    </CardFooter>
                </Card>

                {/* Tracking Simulator Modal */}
                <TrackingSimulatorModal
                    isOpen={trackingModalOpen}
                    onClose={() => setTrackingModalOpen(false)}
                    initialCode={selectedTrackingCode}
                />
            </AuthenticatedLayout>
        </TooltipProvider>
    );
}
