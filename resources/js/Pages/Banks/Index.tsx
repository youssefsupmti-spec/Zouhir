import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { type ColumnDef } from '@tanstack/react-table';
import { Landmark, CreditCard, CheckCircle2, ShieldCheck, Plus, MoreHorizontal, Copy, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface CompteItem {
  id: number;
  titulaire_nom: string;
  masked_rib: string;
  is_default: boolean;
  bank?: {
    id: number;
    name: string;
    code_etablissement?: string;
    swift_code?: string;
  };
  bank_name?: string;
  owner?: {
    id: number;
    nom_complet: string;
    email: string;
    role: string;
  };
}

interface BankItem {
  id: number;
  name: string;
  code_etablissement?: string;
  swift_code?: string;
  status: string;
  comptes_count?: number;
}

interface BanksIndexProps {
  comptes: {
    data: CompteItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  banks: BankItem[];
  stats: {
    total_banks: number;
    total_comptes: number;
    linked_comptes: number;
    link_rate: number;
  };
  filters: {
    search?: string;
    bank_id?: string;
    tab?: string;
    per_page?: number;
  };
}

export default function BanksIndex({ comptes, banks, stats, filters }: BanksIndexProps) {
  const [activeTab, setActiveTab] = useState(filters.tab || 'comptes');

  const handleBankFilter = (selectedBankId?: string | number) => {
    const queryParams = { ...filters, bank_id: selectedBankId || undefined, page: 1 };
    router.get(window.location.pathname, queryParams, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const columns: ColumnDef<DataTableFeatures, CompteItem>[] = [
    {
      accessorKey: 'id',
      header: 'ID',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-muted-foreground">
          #CB-{row.original.id}
        </span>
      ),
    },
    {
      accessorKey: 'titulaire_nom',
      header: 'Titulaire du Compte',
      cell: ({ row }) => (
        <span className="font-semibold text-foreground">
          {row.original.titulaire_nom}
        </span>
      ),
    },
    {
      id: 'owner',
      header: 'Propriétaire',
      cell: ({ row }) => (
        <div>
          <span className="font-medium text-foreground block">
            {row.original.owner?.nom_complet || 'Marchand OUAISS'}
          </span>
          <span className="text-xs text-muted-foreground capitalize">
            {row.original.owner?.role || 'Client'}
          </span>
        </div>
      ),
    },
    {
      id: 'bank',
      header: 'Banque BAM',
      cell: ({ row }) => {
        const bankName = row.original.bank?.name || row.original.bank_name || 'Banque BAM';
        const code = row.original.bank?.code_etablissement;
        return (
          <div className="flex items-center gap-1.5">
            <Badge className="bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300 border-none rounded-md text-xs font-medium">
              <Landmark className="mr-1 h-3 w-3" />
              {bankName}
            </Badge>
            {code && (
              <span className="font-mono text-xs text-muted-foreground">
                ({code})
              </span>
            )}
          </div>
        );
      },
    },
    {
      accessorKey: 'masked_rib',
      header: 'Coordonnées RIB',
      cell: ({ row }) => (
        <span className="font-mono text-xs text-foreground bg-muted/60 px-2 py-0.5 rounded-sm">
          {row.original.masked_rib}
        </span>
      ),
    },
    {
      accessorKey: 'is_default',
      header: 'Rôle',
      cell: ({ row }) => (
        row.original.is_default ? (
          <Badge className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-none rounded-md text-xs">
            Principal
          </Badge>
        ) : (
          <span className="text-xs text-muted-foreground">Secondaire</span>
        )
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
    <AuthenticatedLayout title="Banques & RIBs">
      <Head title="Référentiel Bancaire - OUAISS EXPRESS" />

      <div className="space-y-6">
        {/* Header */}
        <PageHeader
          title="Référentiel Bancaire & RIBs"
          description="Gestion des 12 établissements de crédit Bank Al-Maghrib et des comptes de reversement CRBT."
          icon={Landmark}
          badge={`${stats.total_banks} banques BAM`}
          actions={
            <Button className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold">
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Nouveau Compte
            </Button>
          }
        />

        {/* Cartes KPI Statistiques */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard
            title="Catalogue Banques BAM"
            value={stats.total_banks}
            description="Établissements agréés"
            icon={Landmark}
          />
          <StatsCard
            title="Comptes Rattachés"
            value={`${stats.linked_comptes} / ${stats.total_comptes}`}
            trend={{ value: `${stats.link_rate}%`, isPositive: true, period: "liés par FK" }}
            icon={CheckCircle2}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
          <StatsCard
            title="Banque Leader Marchands"
            value="CIH Bank (50%)"
            description="Établissement n°1"
            icon={CreditCard}
            iconColor="text-orange-500"
          />
          <StatsCard
            title="Norme de Sécurité"
            value="Modulo 97"
            description="Algorithme BAM"
            icon={ShieldCheck}
            iconColor="text-blue-600 dark:text-blue-400"
          />
        </div>

        {/* Onglets : Comptes Tiers vs Catalogue Banques */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
          <TabsList className="rounded-md">
            <TabsTrigger value="comptes" className="rounded-md text-xs">
              Comptes Bancaires Tiers ({stats.total_comptes})
            </TabsTrigger>
            <TabsTrigger value="catalogue" className="rounded-md text-xs">
              Catalogue BAM ({stats.total_banks})
            </TabsTrigger>
          </TabsList>

          <TabsContent value="comptes" className="space-y-3">
            <PageToolbar
              searchPlaceholder="Rechercher par titulaire, banque, marchand..."
              initialSearch={filters.search}
              currentParams={filters}
            >
              {/* Filtre par Banque */}
              <DropdownMenu>
                <DropdownMenuTrigger render={
                  <Button variant="outline" size="sm" className="h-9 text-xs rounded-md">
                    {filters.bank_id && filters.bank_id !== 'all'
                      ? `Banque: ${banks.find((b) => String(b.id) === String(filters.bank_id))?.name || filters.bank_id}`
                      : 'Toutes les banques'}
                  </Button>
                } />
                <DropdownMenuContent align="start" className="w-56 max-h-60 overflow-y-auto">
                  <DropdownMenuItem onClick={() => handleBankFilter('all')} className="text-xs cursor-pointer">
                    Toutes les banques
                  </DropdownMenuItem>
                  {banks.map((b) => (
                    <DropdownMenuItem key={b.id} onClick={() => handleBankFilter(b.id)} className="text-xs cursor-pointer">
                      <span className="font-mono text-muted-foreground mr-1.5">[{b.code_etablissement}]</span>
                      <span>{b.name}</span>
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            </PageToolbar>

            <ServerDataTable
              columns={columns}
              data={comptes.data}
              paginationMeta={comptes}
              currentParams={filters}
              emptyMessage="Aucun compte bancaire trouvé"
            />
          </TabsContent>

          <TabsContent value="catalogue">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {banks.map((bank) => (
                <div key={bank.id} className="p-4 rounded-md border border-border bg-card shadow-2xs space-y-2">
                  <div className="flex items-center justify-between">
                    <Badge variant="outline" className="font-mono text-xs">
                      Code BAM: {bank.code_etablissement || 'N/A'}
                    </Badge>
                    <Badge className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-none text-xs">
                      {bank.status}
                    </Badge>
                  </div>
                  <h3 className="font-semibold text-foreground text-sm flex items-center gap-2">
                    <Landmark className="h-4 w-4 text-[#004c68] dark:text-[#ff8806]" />
                    {bank.name}
                  </h3>
                  <div className="text-xs text-muted-foreground flex justify-between pt-1 border-t border-border/60">
                    <span>SWIFT: {bank.swift_code || '-'}</span>
                    <span className="font-medium text-foreground">{bank.comptes_count || 0} comptes reliés</span>
                  </div>
                </div>
              ))}
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
