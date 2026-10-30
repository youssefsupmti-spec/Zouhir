import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { StatusBadge } from '@/components/common/StatusBadge';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { LivreurFormDialog } from './LivreurFormDialog';
import { type ColumnDef } from '@tanstack/react-table';
import { Truck, CheckCircle2, Package, MapPin, Plus, MoreHorizontal, Edit, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface LivreurItem {
  id: number;
  nom_complet: string;
  email?: string;
  telephone?: string;
  ville?: string;
  status: string;
  total_colis_count?: number;
  colis_livres_count?: number;
  colis_en_cours_count?: number;
  taux_succes?: number;
}

interface LivreursIndexProps {
  livreurs: {
    data: LivreurItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  stats: {
    total: number;
    active: number;
    colis_en_cours: number;
    colis_livres_total: number;
  };
  filters: {
    search?: string;
    status?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
  };
}

export default function LivreursIndex({ livreurs, stats, filters }: LivreursIndexProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editingLivreur, setEditingLivreur] = useState<LivreurItem | null>(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingLivreur, setDeletingLivreur] = useState<LivreurItem | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const handleCreate = () => {
    setEditingLivreur(null);
    setFormOpen(true);
  };

  const handleEdit = (l: LivreurItem) => {
    setEditingLivreur(l);
    setFormOpen(true);
  };

  const handleDeleteClick = (l: LivreurItem) => {
    setDeletingLivreur(l);
    setDeleteOpen(true);
  };

  const handleConfirmDelete = () => {
    if (!deletingLivreur) return;
    setIsDeleting(true);
    router.delete(`/livreurs/${deletingLivreur.id}`, {
      onFinish: () => {
        setIsDeleting(false);
        setDeleteOpen(false);
        setDeletingLivreur(null);
      },
    });
  };

  const columns: ColumnDef<DataTableFeatures, LivreurItem>[] = [
    {
      accessorKey: 'id',
      header: 'ID',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          #{row.original.id}
        </span>
      ),
    },
    {
      accessorKey: 'nom_complet',
      header: 'Livreur',
      cell: ({ row }) => (
        <div className="flex items-center gap-2.5">
          <div className="h-8 w-8 rounded-full bg-[#ff8806]/10 text-[#c26200] dark:bg-[#ff8806]/30 dark:text-orange-300 flex items-center justify-center font-bold text-xs shrink-0">
            {row.original.nom_complet.substring(0, 2).toUpperCase()}
          </div>
          <div>
            <span className="font-semibold text-foreground block">
              {row.original.nom_complet}
            </span>
            <span className="text-xs text-muted-foreground font-mono block">
              {row.original.telephone || '-'}
            </span>
          </div>
        </div>
      ),
    },
    {
      accessorKey: 'ville',
      header: 'Secteur',
      cell: ({ row }) => (
        <div className="flex items-center gap-1.5 text-xs text-foreground font-medium">
          <MapPin className="h-3 w-3 text-muted-foreground" />
          <span>{row.original.ville || 'Toutes zones'}</span>
        </div>
      ),
    },
    {
      id: 'tournee',
      header: 'Progression Tournée',
      cell: ({ row }) => {
        const total = row.original.total_colis_count || 0;
        const livres = row.original.colis_livres_count || 0;
        const taux = row.original.taux_succes || 0;

        return (
          <div className="w-36 space-y-1">
            <div className="flex justify-between text-xs font-mono text-muted-foreground">
              <span>{livres} / {total} colis</span>
              <span className="font-semibold text-foreground">{taux}%</span>
            </div>
            <Progress value={taux} className="h-1.5 rounded-full" />
          </div>
        );
      },
    },
    {
      accessorKey: 'colis_en_cours_count',
      header: 'En cours',
      cell: ({ row }) => (
        <span className="font-mono text-xs font-medium text-amber-700 dark:text-amber-300">
          {row.original.colis_en_cours_count || 0}
        </span>
      ),
    },
    {
      accessorKey: 'status',
      header: 'Statut',
      cell: ({ row }) => (
        <StatusBadge status={row.original.status} />
      ),
    },
    {
      id: 'actions',
      header: () => <span className="sr-only">Actions</span>,
      cell: ({ row }) => (
        <div className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-7 w-7 rounded-md">
                <MoreHorizontal className="h-4 w-4 text-muted-foreground" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40 text-xs">
              <DropdownMenuItem onClick={() => handleEdit(row.original)} className="gap-2 cursor-pointer">
                <Edit className="h-3.5 w-3.5" />
                Modifier
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={() => handleDeleteClick(row.original)}
                className="gap-2 text-destructive focus:text-destructive cursor-pointer"
              >
                <Trash2 className="h-3.5 w-3.5" />
                Archiver
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    },
  ];

  return (
    <AuthenticatedLayout title="Livreurs (DLM)">
      <Head title="Livreurs & Tournées - OUAISS EXPRESS" />

      <div className="space-y-6">
        {/* Header */}
        <PageHeader
          title="Livreurs & Tournées"
          description="Pilotage de l'équipe du dernier kilomètre, affectation des zones et suivi en direct."
          icon={Truck}
          badge={`${stats.total} livreurs`}
          actions={
            <Button
              onClick={handleCreate}
              className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold cursor-pointer"
            >
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Nouveau Livreur
            </Button>
          }
        />

        {/* Cartes KPI Statistiques */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard
            title="Livreurs Totaux"
            value={stats.total}
            description="Effectif enregistré"
            icon={Truck}
          />
          <StatsCard
            title="Livreurs Opérationnels"
            value={stats.active}
            trend={{ value: `${Math.round((stats.active / (stats.total || 1)) * 100)}%`, isPositive: true, period: "actifs" }}
            icon={CheckCircle2}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
          <StatsCard
            title="Colis en Distribution"
            value={stats.colis_en_cours}
            description="Tournées en cours"
            icon={Package}
            iconColor="text-orange-500"
          />
          <StatsCard
            title="Colis Livrés Cumulés"
            value={stats.colis_livres_total}
            description="Livraisons réussies"
            icon={CheckCircle2}
            iconColor="text-blue-600 dark:text-blue-400"
          />
        </div>

        {/* Toolbar & Server Data Table */}
        <div className="space-y-3">
          <PageToolbar
            searchPlaceholder="Rechercher par nom de coursier, téléphone, ville..."
            initialSearch={filters.search}
            currentParams={filters}
          />

          <ServerDataTable
            columns={columns}
            data={livreurs.data}
            paginationMeta={livreurs}
            currentParams={filters}
            emptyMessage="Aucun coursier trouvé"
          />
        </div>
      </div>

      {/* Modals Dialog CRUD */}
      <LivreurFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        livreur={editingLivreur}
      />

      <ConfirmDeleteDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title="Archiver le livreur"
        description={`Êtes-vous sûr de vouloir archiver le coursier "${deletingLivreur?.nom_complet}" ? Ses tournées passées resteront archivées.`}
        onConfirm={handleConfirmDelete}
        isLoading={isDeleting}
      />
    </AuthenticatedLayout>
  );
}
