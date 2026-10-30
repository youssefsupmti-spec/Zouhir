import React from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { type ColumnDef } from '@tanstack/react-table';
import { MapPin, Network, Layers, Clock, Plus, MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface VilleItem {
  id: number;
  name_fr: string;
  name_ar?: string;
  code_zone?: string;
  zone?: {
    id: number;
    name: string;
    code: string;
  };
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
  zones: Array<{ id: number; name: string; code: string }>;
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
  const handleZoneFilter = (selectedZoneId?: string | number) => {
    const queryParams = { ...filters, zone_id: selectedZoneId || undefined, page: 1 };
    router.get(window.location.pathname, queryParams, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

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
        <span className="font-arabic text-sm text-muted-foreground" dir="rtl">
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
            <Button className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold">
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
            description="42 569 colis urbains"
            icon={Layers}
            iconColor="text-blue-600 dark:text-blue-400"
          />
          <StatsCard
            title="Délai Moyen"
            value="24h / 48h"
            description="J+1 sur axe principal"
            icon={Clock}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
        </div>

        {/* Toolbar & Server Data Table */}
        <div className="space-y-3">
          <PageToolbar
            searchPlaceholder="Rechercher une ville (français, arabe, code)..."
            initialSearch={filters.search}
            currentParams={filters}
          >
            {/* Filtre par Zone */}
            <DropdownMenu>
              <DropdownMenuTrigger render={
                <Button variant="outline" size="sm" className="h-9 text-xs rounded-md">
                  {filters.zone_id && filters.zone_id !== 'all'
                    ? `Zone: ${zones.find((z) => String(z.id) === String(filters.zone_id))?.code || filters.zone_id}`
                    : 'Toutes les zones'}
                </Button>
              } />
              <DropdownMenuContent align="start" className="w-52">
                <DropdownMenuItem onClick={() => handleZoneFilter('all')} className="text-xs cursor-pointer">
                  Toutes les zones
                </DropdownMenuItem>
                {zones.map((z) => (
                  <DropdownMenuItem key={z.id} onClick={() => handleZoneFilter(z.id)} className="text-xs cursor-pointer">
                    <span className="font-mono font-bold mr-2 text-[#004c68]">{z.code}</span>
                    <span>{z.name}</span>
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
            emptyMessage="Aucune ville ne correspond aux critères sélectionnés"
          />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
