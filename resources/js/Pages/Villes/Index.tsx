import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { VilleFormDialog } from './VilleFormDialog';
import { type ColumnDef } from '@tanstack/react-table';
import { MapPin, Network, Navigation, CheckCircle2, Plus, MoreHorizontal, Edit, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface ZoneItem {
  id: number;
  name: string;
  code: string;
}

interface VilleItem {
  id: number;
  name_fr: string;
  name_ar?: string;
  zone_id?: number;
  code_zone?: string;
  zone?: ZoneItem;
}

interface VillesIndexProps {
  villes: {
    data: VilleItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  zones: ZoneItem[];
  stats: {
    total_villes: number;
    total_zones: number;
  };
  filters: {
    search?: string;
    zone_id?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
  };
}

export default function VillesIndex({ villes, zones, stats, filters }: VillesIndexProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editingVille, setEditingVille] = useState<VilleItem | null>(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingVille, setDeletingVille] = useState<VilleItem | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const handleCreate = () => {
    setEditingVille(null);
    setFormOpen(true);
  };

  const handleEdit = (v: VilleItem) => {
    setEditingVille(v);
    setFormOpen(true);
  };

  const handleDeleteClick = (v: VilleItem) => {
    setDeletingVille(v);
    setDeleteOpen(true);
  };

  const handleConfirmDelete = () => {
    if (!deletingVille) return;
    setIsDeleting(true);
    router.delete(`/villes/${deletingVille.id}`, {
      onFinish: () => {
        setIsDeleting(false);
        setDeleteOpen(false);
        setDeletingVille(null);
      },
    });
  };

  const handleZoneFilter = (zoneId?: string) => {
    router.get(
      '/villes',
      {
        ...filters,
        zone_id: zoneId ?? 'all',
        page: 1,
      },
      { preserveState: true }
    );
  };

  const currentZone = zones.find((z) => z.id.toString() === filters.zone_id);

  const columns: ColumnDef<DataTableFeatures, VilleItem>[] = [
    {
      accessorKey: 'id',
      header: 'ID',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          #VIL-{row.original.id}
        </span>
      ),
    },
    {
      accessorKey: 'name_fr',
      header: 'Ville (Français)',
      cell: ({ row }) => (
        <span className="font-semibold text-foreground">
          {row.original.name_fr}
        </span>
      ),
    },
    {
      accessorKey: 'name_ar',
      header: 'Ville (Arabe)',
      cell: ({ row }) => (
        <span className="text-xs text-muted-foreground font-medium" dir="rtl">
          {row.original.name_ar || '-'}
        </span>
      ),
    },
    {
      id: 'zone',
      header: 'Zone Logistique',
      cell: ({ row }) => (
        <Badge className="bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300 border-none rounded-md text-xs font-medium">
          {row.original.zone?.name || 'Rabat-Témara'}
        </Badge>
      ),
    },
    {
      accessorKey: 'code_zone',
      header: 'Code Zone',
      cell: ({ row }) => (
        <span className="font-mono text-xs font-bold text-muted-foreground">
          {row.original.code_zone || row.original.zone?.code || 'RBT'}
        </span>
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
                Supprimer
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    },
  ];

  return (
    <AuthenticatedLayout title="Villes & Zones">
      <Head title="Référentiel des Villes - OUAISS EXPRESS" />

      <div className="space-y-6">
        {/* Header */}
        <PageHeader
          title="Référentiel des Villes & Zones"
          description="Découpage territorial du Maroc en 6 zones logistiques et gestion de la desserte des villes."
          icon={MapPin}
          badge={`${stats.total_villes} villes`}
          actions={
            <Button
              onClick={handleCreate}
              className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold cursor-pointer"
            >
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Ajouter une Ville
            </Button>
          }
        />

        {/* Cartes KPI Statistiques */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard
            title="Villes Référencées"
            value={stats.total_villes}
            description="Catalogue national"
            icon={MapPin}
          />
          <StatsCard
            title="Hubs & Zones"
            value={`${stats.total_zones} Hubs`}
            description="RBT, CAS, NORD, SUD..."
            icon={Network}
            iconColor="text-orange-500"
          />
          <StatsCard
            title="Bassin Historique"
            value="Témara (46%)"
            description="Centre de tri principal"
            icon={Navigation}
          />
          <StatsCard
            title="Intégrité Référentiel"
            value="100 %"
            description="Toutes rattachées à une zone"
            icon={CheckCircle2}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
        </div>

        {/* Toolbar & Server Data Table */}
        <div className="space-y-3">
          <PageToolbar
            searchPlaceholder="Rechercher une ville par nom FR, nom arabe ou zone..."
            initialSearch={filters.search}
            currentParams={filters}
          >
            {/* Filtre par Zone */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="h-8 text-xs rounded-md">
                  {currentZone ? `Zone: ${currentZone.name}` : 'Toutes les zones'}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-52">
                <DropdownMenuItem onClick={() => handleZoneFilter(undefined)} className="text-xs cursor-pointer">
                  Toutes les zones
                </DropdownMenuItem>
                {zones.map((z) => (
                  <DropdownMenuItem key={z.id} onClick={() => handleZoneFilter(z.id.toString())} className="text-xs cursor-pointer">
                    {z.name} ({z.code})
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          </PageToolbar>

          <ServerDataTable
            columns={columns}
            data={villes.data}
            paginationMeta={villes}
            currentParams={filters}
            emptyMessage="Aucune ville trouvée dans ce périmètre"
          />
        </div>
      </div>

      {/* Modals Dialog CRUD */}
      <VilleFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        ville={editingVille}
        zones={zones}
      />

      <ConfirmDeleteDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title="Retirer la ville du référentiel"
        description={`Êtes-vous sûr de vouloir supprimer la ville "${deletingVille?.name_fr}" du catalogue ?`}
        onConfirm={handleConfirmDelete}
        isLoading={isDeleting}
      />
    </AuthenticatedLayout>
  );
}
