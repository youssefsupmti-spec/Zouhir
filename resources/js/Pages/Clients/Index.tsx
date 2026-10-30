import React from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { StatusBadge } from '@/components/common/StatusBadge';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { type ColumnDef } from '@tanstack/react-table';
import { Users, UserCheck, Store, Package, Plus, MoreHorizontal, Landmark, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface ClientItem {
  id: number;
  nom_complet: string;
  email: string;
  telephone?: string;
  ville?: string;
  status: string;
  commands_count?: number;
  businesses?: Array<{ id: number; name: string }>;
  comptes_bancaires?: Array<{
    id: number;
    masked_rib: string;
    bank?: { name: string; code_etablissement?: string };
    bank_name?: string;
    is_default: boolean;
  }>;
}

interface ClientsIndexProps {
  clients: {
    data: ClientItem[];
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
    businesses_count: number;
    commands_count: number;
  };
  villesList: string[];
  filters: {
    search?: string;
    status?: string;
    ville?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
  };
}

export default function ClientsIndex({ clients, stats, villesList, filters }: ClientsIndexProps) {
  const handleVilleFilter = (selectedVille?: string) => {
    const queryParams = { ...filters, ville: selectedVille || undefined, page: 1 };
    router.get(window.location.pathname, queryParams, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const columns: ColumnDef<DataTableFeatures, ClientItem>[] = [
    {
      accessorKey: 'id',
      header: 'ID',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          #CL-{row.original.id}
        </span>
      ),
    },
    {
      accessorKey: 'nom_complet',
      header: 'Marchand',
      cell: ({ row }) => (
        <div>
          <span className="font-semibold text-foreground block">
            {row.original.nom_complet}
          </span>
          <span className="text-xs text-muted-foreground font-mono block">
            {row.original.email}
          </span>
        </div>
      ),
    },
    {
      accessorKey: 'telephone',
      header: 'Téléphone',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          {row.original.telephone || '-'}
        </span>
      ),
    },
    {
      accessorKey: 'ville',
      header: 'Ville',
      cell: ({ row }) => (
        row.original.ville ? (
          <Badge variant="outline" className="rounded-md text-xs font-normal">
            {row.original.ville}
          </Badge>
        ) : (
          <span className="text-muted-foreground text-xs">-</span>
        )
      ),
    },
    {
      id: 'businesses',
      header: 'Boutique(s)',
      cell: ({ row }) => {
        const list = row.original.businesses || [];
        if (list.length === 0) return <span className="text-muted-foreground text-xs">-</span>;
        return (
          <div className="flex flex-wrap gap-1">
            {list.map((b) => (
              <Badge key={b.id} className="bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300 border-none rounded-md text-xs">
                {b.name}
              </Badge>
            ))}
          </div>
        );
      },
    },
    {
      id: 'banque',
      header: 'Banque & RIB',
      cell: ({ row }) => {
        const compte = row.original.comptes_bancaires?.[0];
        if (!compte) {
          return (
            <span className="text-xs text-amber-600 dark:text-amber-400 font-medium">
              À renseigner
            </span>
          );
        }
        return (
          <div className="space-y-0.5">
            <div className="flex items-center gap-1 text-xs font-medium text-foreground">
              <Landmark className="h-3 w-3 text-muted-foreground" />
              <span>{compte.bank?.name || compte.bank_name || 'Banque BAM'}</span>
            </div>
            <div className="text-xs text-muted-foreground font-mono">
              {compte.masked_rib}
            </div>
          </div>
        );
      },
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
    <AuthenticatedLayout title="Clients Marchands">
      <Head title="Clients Marchands - OUAISS EXPRESS" />

      <div className="space-y-6">
        {/* Header */}
        <PageHeader
          title="Clients Marchands"
          description="Gestion des expéditeurs, suivi des boutiques et contrôle des coordonnées de reversement."
          icon={Users}
          badge={`${stats.total} marchands`}
          actions={
            <Button className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold">
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Nouveau Marchand
            </Button>
          }
        />

        {/* Cartes KPI Statistiques */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard
            title="Total Marchands"
            value={stats.total}
            description="Comptes expéditeurs"
            icon={Users}
          />
          <StatsCard
            title="Marchands Actifs"
            value={stats.active}
            trend={{ value: `${Math.round((stats.active / (stats.total || 1)) * 100)}%`, isPositive: true, period: "opérationnels" }}
            icon={UserCheck}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
          <StatsCard
            title="Boutiques Enregistrées"
            value={stats.businesses_count}
            description="Enseignes commerciales"
            icon={Store}
          />
          <StatsCard
            title="Expéditions Totales"
            value={stats.commands_count}
            description="Colis pris en charge"
            icon={Package}
            iconColor="text-blue-600 dark:text-blue-400"
          />
        </div>

        {/* Toolbar & Server Data Table */}
        <div className="space-y-3">
          <PageToolbar
            searchPlaceholder="Rechercher par nom, email, téléphone, boutique..."
            initialSearch={filters.search}
            currentParams={filters}
          >
            {/* Filtre par Ville */}
            <DropdownMenu>
              <DropdownMenuTrigger render={
                <Button variant="outline" size="sm" className="h-9 text-xs rounded-md">
                  {filters.ville ? `Ville: ${filters.ville}` : 'Toutes les villes'}
                </Button>
              } />
              <DropdownMenuContent align="start" className="w-48 max-h-60 overflow-y-auto">
                <DropdownMenuItem onClick={() => handleVilleFilter(undefined)} className="text-xs cursor-pointer">
                  Toutes les villes
                </DropdownMenuItem>
                {villesList.map((v) => (
                  <DropdownMenuItem key={v} onClick={() => handleVilleFilter(v)} className="text-xs cursor-pointer">
                    {v}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          </PageToolbar>

          <ServerDataTable
            columns={columns}
            data={clients.data}
            paginationMeta={clients}
            currentParams={filters}
            emptyMessage="Aucun client marchand trouvé"
          />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
