import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { StatusBadge } from '@/components/common/StatusBadge';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { type ColumnDef } from '@tanstack/react-table';
import { Truck, CheckCircle2, Package, MapPin, Plus, MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';

interface LivreurItem {
  id: number;
  nom_complet: string;
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
  const columns: ColumnDef<DataTableFeatures, LivreurItem>[] = [
    {
      accessorKey: 'id',
      header: 'ID',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          #LIV-{row.original.id}
        </span>
      ),
    },
    {
      accessorKey: 'nom_complet',
      header: 'Livreur (DLM)',
      cell: ({ row }) => (
        <div>
          <span className="font-semibold text-foreground block">
            {row.original.nom_complet}
          </span>
          <span className="text-xs text-muted-foreground font-mono block">
            {row.original.telephone || '-'}
          </span>
        </div>
      ),
    },
    {
      accessorKey: 'ville',
      header: 'Zone / Ville',
      cell: ({ row }) => (
        <Badge className="bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300 border-none rounded-md text-xs">
          <MapPin className="mr-1 h-3 w-3" />
          {row.original.ville || 'Témara'}
        </Badge>
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
      cell: () => (
        <div className="flex justify-end">
          <Button variant="ghost" size="icon" className="h-7 w-7 rounded-md">
            <MoreHorizontal className="h-4 w-4 text-muted-foreground" />
          </Button>
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
            <Button className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold">
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
            searchPlaceholder="Rechercher un livreur par nom, GSM, ville..."
            initialSearch={filters.search}
            currentParams={filters}
          />

          <ServerDataTable
            columns={columns}
            data={livreurs.data}
            paginationMeta={livreurs}
            currentParams={filters}
            emptyMessage="Aucun livreur trouvé pour cette recherche"
          />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
