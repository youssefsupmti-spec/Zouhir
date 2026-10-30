import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { StatusBadge } from '@/components/common/StatusBadge';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { type ColumnDef } from '@tanstack/react-table';
import { Store, Building2, CheckCircle2, Package, Plus, MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface BusinessItem {
  id: number;
  name: string;
  phone?: string;
  status: string;
  created_at: string;
  commands_count?: number;
  user?: {
    id: number;
    nom_complet: string;
    email: string;
    telephone?: string;
  };
}

interface BusinessesIndexProps {
  businesses: {
    data: BusinessItem[];
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
    clients_with_business: number;
    total_clients: number;
    coverage_rate: number;
  };
  filters: {
    search?: string;
    status?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
  };
}

export default function BusinessesIndex({ businesses, stats, filters }: BusinessesIndexProps) {
  const columns: ColumnDef<DataTableFeatures, BusinessItem>[] = [
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
      accessorKey: 'name',
      header: 'Enseigne',
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <div className="h-7 w-7 rounded-md bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300 flex items-center justify-center font-bold text-xs shrink-0">
            {row.original.name.substring(0, 2).toUpperCase()}
          </div>
          <span className="font-semibold text-foreground">
            {row.original.name}
          </span>
        </div>
      ),
    },
    {
      id: 'marchand',
      header: 'Client Marchand',
      cell: ({ row }) => (
        <div>
          <span className="font-medium text-foreground block">
            {row.original.user?.nom_complet || 'Marchand OUAISS'}
          </span>
          <span className="text-xs text-muted-foreground block font-mono">
            {row.original.user?.email || '-'}
          </span>
        </div>
      ),
    },
    {
      accessorKey: 'phone',
      header: 'Téléphone',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          {row.original.phone || row.original.user?.telephone || '-'}
        </span>
      ),
    },
    {
      accessorKey: 'commands_count',
      header: 'Colis',
      cell: ({ row }) => (
        <span className="font-mono text-xs font-semibold text-foreground">
          {row.original.commands_count ?? 0}
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
    <AuthenticatedLayout title="Enseignes Marchandes">
      <Head title="Enseignes & Boutiques - OUAISS EXPRESS" />

      <div className="space-y-6">
        {/* Header */}
        <PageHeader
          title="Enseignes & Boutiques"
          description="Référentiel des marques et boutiques marchandes sous contrat de livraison."
          icon={Store}
          badge={`${stats.total} enseignes`}
          actions={
            <Button className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold">
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Nouvelle Enseigne
            </Button>
          }
        />

        {/* Cartes KPI Statistiques */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard
            title="Total Enseignes"
            value={stats.total}
            description="Boutiques répertoriées"
            icon={Store}
          />
          <StatsCard
            title="Enseignes Actives"
            value={stats.active}
            trend={{ value: `${Math.round((stats.active / (stats.total || 1)) * 100)}%`, isPositive: true, period: "du catalogue" }}
            icon={CheckCircle2}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
          <StatsCard
            title="Marchands Couverts"
            value={`${stats.clients_with_business} / ${stats.total_clients}`}
            description="Clients avec boutique"
            icon={Building2}
          />
          <StatsCard
            title="Taux de Couverture"
            value={`${stats.coverage_rate} %`}
            description="Marchands rattachés"
            icon={Package}
            iconColor="text-blue-600 dark:text-blue-400"
          />
        </div>

        {/* Toolbar & Server Data Table */}
        <div className="space-y-3">
          <PageToolbar
            searchPlaceholder="Rechercher une enseigne, un marchand, un téléphone..."
            initialSearch={filters.search}
            currentParams={filters}
          />

          <ServerDataTable
            columns={columns}
            data={businesses.data}
            paginationMeta={businesses}
            currentParams={filters}
            emptyMessage="Aucune enseigne trouvée pour ces critères de recherche"
          />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
