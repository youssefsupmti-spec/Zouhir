import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { StatusBadge } from '@/components/common/StatusBadge';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { ColisFormDialog } from './ColisFormDialog';
import { ChangeStatutDialog } from './ChangeStatutDialog';
import { type ColumnDef } from '@tanstack/react-table';
import { 
  Package, 
  Truck, 
  CheckCircle2, 
  Wallet, 
  Plus, 
  MoreHorizontal, 
  Edit, 
  Trash2, 
  MapPin, 
  RefreshCw,
  Phone,
  Store
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';


// Provenance du frais client figé (commands.type_tarif_client) — libellés seulement, aucun calcul.
const TYPE_TARIF_LABEL: Record<string, string> = {
  client: 'négocié',
  standard: 'standard',
  manual: 'manuel',
  promo: 'promo',
};

interface ClientOption {
  id: number;
  nom_complet: string;
  telephone?: string;
  businesses?: Array<{ id: number; name: string; user_id: number }>;
}

interface VilleOption {
  id: number;
  name_fr: string;
  code_zone?: string;
}

interface LivreurOption {
  id: number;
  nom_complet: string;
  telephone?: string;
}

interface ColisItem {
  /** Capacités calculées côté serveur par CommandPolicy. */
  can?: { update: boolean; updateState: boolean; delete: boolean };
  id: number;
  code: string;
  produit?: string;
  quantite: number;
  destinataire_nom: string;
  destinataire_telephone: string;
  destinataire_adresse?: string;
  destinataire_ville: string;
  destinataire_ville_id?: number;
  destinataire_zone_code?: string;
  prix_brut: number;
  frais_livraison_client: number;
  type_tarif_client?: string | null;
  state: string;
  state_group: string;
  client_id: number;
  business_id?: number;
  business_name?: string;
  nom_cliente?: string;
  livreur_id?: number;
  livreur_nom?: string;
  livreur_telephone?: string;
  ouverture_autorisee: boolean;
  echange: boolean;
  note?: string;
  created_at: string;
  livre_at?: string;
  client?: {
    id: number;
    nom_complet: string;
    email: string;
    telephone?: string;
  };
  business?: {
    id: number;
    name: string;
  };
  livreur?: {
    id: number;
    nom_complet: string;
    telephone?: string;
  };
}

interface ColisIndexProps {
  canCreate?: boolean;
  canSetManualFee?: boolean;
  colis: {
    data: ColisItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  clients: ClientOption[];
  villes: VilleOption[];
  livreurs: LivreurOption[];
  stats: {
    total: number;
    livres: number;
    en_cours: number;
    retours: number;
    taux_livraison: number;
    total_crbt: number;
  };
  filters: {
    search?: string;
    state_group?: string;
    ville_id?: string;
    client_id?: string;
    livreur_id?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
  };
}

export default function ColisIndex({
  colis,
  clients = [],
  villes = [],
  livreurs = [],
  stats,
  filters,
  canCreate = false,
  canSetManualFee = false,
}: ColisIndexProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editingColis, setEditingColis] = useState<ColisItem | null>(null);

  const [statutOpen, setStatutOpen] = useState(false);
  const [statutColis, setStatutColis] = useState<ColisItem | null>(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingColis, setDeletingColis] = useState<ColisItem | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const handleCreate = () => {
    setEditingColis(null);
    setFormOpen(true);
  };

  const handleEdit = (c: ColisItem) => {
    setEditingColis(c);
    setFormOpen(true);
  };

  const handleStatusClick = (c: ColisItem) => {
    setStatutColis(c);
    setStatutOpen(true);
  };

  const handleDeleteClick = (c: ColisItem) => {
    setDeletingColis(c);
    setDeleteOpen(true);
  };

  const handleConfirmDelete = () => {
    if (!deletingColis) return;
    setIsDeleting(true);
    router.delete(`/colis/${deletingColis.id}`, {
      onFinish: () => {
        setIsDeleting(false);
        setDeleteOpen(false);
        setDeletingColis(null);
      },
    });
  };

  const handleFilterChange = (key: string, value?: string) => {
    router.get(
      '/colis',
      {
        ...filters,
        [key]: value ?? 'all',
        page: 1,
      },
      { preserveState: true }
    );
  };

  const columns: ColumnDef<DataTableFeatures, ColisItem>[] = [
    {
      accessorKey: 'code',
      header: 'Code AWB',
      cell: ({ row }) => (
        <div>
          <span className="font-mono font-bold text-xs text-primary block">
            {row.original.code}
          </span>
          <span className="text-[11px] text-muted-foreground font-mono">
            {row.original.created_at ? new Date(row.original.created_at).toLocaleDateString('fr-FR') : '-'}
          </span>
        </div>
      ),
    },
    {
      id: 'expediteur',
      header: 'Expéditeur',
      cell: ({ row }) => (
        <div>
          <span className="font-semibold text-xs text-foreground block">
            {row.original.nom_cliente || row.original.client?.nom_complet || 'Marchand OUAISS'}
          </span>
          {row.original.business_name && (
            <span className="text-[11px] text-muted-foreground flex items-center gap-1">
              <Store className="h-3 w-3" />
              {row.original.business_name}
            </span>
          )}
        </div>
      ),
    },
    {
      id: 'destinataire',
      header: 'Destinataire',
      cell: ({ row }) => (
        <div>
          <span className="font-medium text-xs text-foreground block">
            {row.original.destinataire_nom}
          </span>
          <a
            href={`tel:${row.original.destinataire_telephone}`}
            className="text-[11px] text-muted-foreground font-mono flex items-center gap-1 hover:text-primary"
          >
            <Phone className="h-2.5 w-2.5" />
            {row.original.destinataire_telephone}
          </a>
        </div>
      ),
    },
    {
      id: 'destination',
      header: 'Destination',
      cell: ({ row }) => (
        <div>
          <div className="flex items-center gap-1 text-xs font-semibold text-foreground">
            <MapPin className="h-3 w-3 text-muted-foreground" />
            <span>{row.original.destinataire_ville}</span>
          </div>
          {row.original.destinataire_zone_code && (
            <span className="text-[10px] font-mono text-muted-foreground bg-muted px-1.5 py-0.2 rounded-xs">
              Zone: {row.original.destinataire_zone_code}
            </span>
          )}
        </div>
      ),
    },
    {
      accessorKey: 'prix_brut',
      header: 'CRBT (MAD)',
      cell: ({ row }) => (
        <div>
          <span className="font-mono font-bold text-xs text-foreground block">
            {Number(row.original.prix_brut).toFixed(2)} MAD
          </span>
          <span className="text-[10px] text-muted-foreground font-mono">
            Frais: {row.original.type_tarif_client === 'missing'
              ? <span className="font-sans font-semibold text-[#b45f00]">tarif manquant</span>
              : Number(row.original.frais_livraison_client).toFixed(2)}
            {TYPE_TARIF_LABEL[row.original.type_tarif_client ?? ''] && row.original.type_tarif_client !== 'missing' && (
              <span className="ml-1 font-sans">· {TYPE_TARIF_LABEL[row.original.type_tarif_client ?? '']}</span>
            )}
          </span>
        </div>
      ),
    },
    {
      id: 'livreur',
      header: 'Livreur',
      cell: ({ row }) => (
        <span className="text-xs text-muted-foreground font-medium">
          {row.original.livreur_nom || row.original.livreur?.nom_complet || 'Non affecté'}
        </span>
      ),
    },
    {
      accessorKey: 'state_group',
      header: 'Statut',
      cell: ({ row }) => (
        <StatusBadge status={row.original.state_group || 'nouveau'} />
      ),
    },
    {
      id: 'actions',
      header: () => <span className="sr-only">Actions</span>,
      cell: ({ row }) => {
        const can = row.original.can;
        if (!can || (!can.update && !can.updateState && !can.delete)) return null;
        return (
        <div className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-7 w-7 rounded-md">
                <MoreHorizontal className="h-4 w-4 text-muted-foreground" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44 text-xs">
              {can.updateState && (
              <DropdownMenuItem onClick={() => handleStatusClick(row.original)} className="gap-2 cursor-pointer font-medium text-primary">
                <RefreshCw className="h-3.5 w-3.5" />
                Changer de statut
              </DropdownMenuItem>
              )}
              {can.update && (
              <DropdownMenuItem onClick={() => handleEdit(row.original)} className="gap-2 cursor-pointer">
                <Edit className="h-3.5 w-3.5" />
                Modifier le colis
              </DropdownMenuItem>
              )}
              {can.delete && (
              <DropdownMenuItem
                onClick={() => handleDeleteClick(row.original)}
                className="gap-2 text-destructive focus:text-destructive cursor-pointer"
              >
                <Trash2 className="h-3.5 w-3.5" />
                Archiver
              </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        );
      },
    },
  ];

  return (
    <AuthenticatedLayout title="Expéditions & Colis">
      <Head title="Expéditions & Colis - OUAISS EXPRESS" />

      <div className="space-y-6">
        {/* Header */}
        <PageHeader
          title="Expéditions & Colis Express"
          description="Gestion du flux logistique national, expéditions marchands et encaissement des CRBT."
          icon={Package}
          badge={`${stats.total} colis`}
          actions={
            canCreate && <Button
              onClick={handleCreate}
              className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold cursor-pointer"
            >
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Nouveau Colis Express
            </Button>
          }
        />

        {/* Cartes KPI Statistiques */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard
            title="Total Expéditions"
            value={stats.total}
            description="Volume total traité"
            icon={Package}
          />
          <StatsCard
            title="Taux de Livraison"
            value={`${stats.taux_livraison} %`}
            trend={{ value: `${stats.livres} colis livrés`, isPositive: true, period: "succès" }}
            icon={CheckCircle2}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
          <StatsCard
            title="En Distribution"
            value={stats.en_cours}
            description="Tournées coursiers"
            icon={Truck}
            iconColor="text-orange-500"
          />
          <StatsCard
            title="CRBT Encaissé"
            value={`${Number(stats.total_crbt).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} MAD`}
            description="Fonds à reverser"
            icon={Wallet}
            iconColor="text-blue-600 dark:text-blue-400"
          />
        </div>

        {/* Toolbar & Server Data Table */}
        <div className="space-y-3">
          <PageToolbar
            searchPlaceholder="Rechercher par code AWB, destinataire, téléphone, produit..."
            initialSearch={filters.search}
            currentParams={filters}
          >
            {/* Filtre par État */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="h-8 text-xs rounded-md">
                  {filters.state_group && filters.state_group !== 'all' ? `Statut: ${filters.state_group}` : 'Tous les statuts'}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-44">
                <DropdownMenuItem onClick={() => handleFilterChange('state_group', undefined)} className="text-xs cursor-pointer">
                  Tous les statuts
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFilterChange('state_group', 'livre')} className="text-xs cursor-pointer">
                  Livré avec succès
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFilterChange('state_group', 'en_cours')} className="text-xs cursor-pointer">
                  En cours de livraison
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFilterChange('state_group', 'retour')} className="text-xs cursor-pointer">
                  Retour expéditeur
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFilterChange('state_group', 'annule')} className="text-xs cursor-pointer">
                  Annulé
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFilterChange('state_group', 'nouveau')} className="text-xs cursor-pointer">
                  Nouveau colis
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            {/* Filtre par Ville */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="h-8 text-xs rounded-md">
                  {filters.ville_id && filters.ville_id !== 'all'
                    ? `Ville: ${villes.find((v) => v.id.toString() === filters.ville_id)?.name_fr || filters.ville_id}`
                    : 'Toutes les villes'}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-48 max-h-60 overflow-y-auto">
                <DropdownMenuItem onClick={() => handleFilterChange('ville_id', undefined)} className="text-xs cursor-pointer">
                  Toutes les villes
                </DropdownMenuItem>
                {villes.map((v) => (
                  <DropdownMenuItem key={v.id} onClick={() => handleFilterChange('ville_id', v.id.toString())} className="text-xs cursor-pointer">
                    {v.name_fr}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          </PageToolbar>

          <ServerDataTable
            columns={columns}
            data={colis.data}
            paginationMeta={colis}
            currentParams={filters}
            emptyMessage="Aucun colis ne correspond à ces critères"
          />
        </div>
      </div>

      {/* Modals Dialog CRUD */}
      <ColisFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        colis={editingColis}
        clients={clients}
        villes={villes}
        livreurs={livreurs}
        canSetManualFee={canSetManualFee}
      />

      <ChangeStatutDialog
        open={statutOpen}
        onOpenChange={setStatutOpen}
        colis={statutColis}
        livreurs={livreurs}
      />

      <ConfirmDeleteDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title="Archiver l'expédition"
        description={`Êtes-vous sûr de vouloir archiver le colis #${deletingColis?.code} ?`}
        onConfirm={handleConfirmDelete}
        isLoading={isDeleting}
      />
    </AuthenticatedLayout>
  );
}

