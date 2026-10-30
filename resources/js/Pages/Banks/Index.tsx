import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { ServerDataTable, DataTableFeatures } from '@/components/common/ServerDataTable';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { CompteBancaireFormDialog } from './CompteBancaireFormDialog';
import { type ColumnDef } from '@tanstack/react-table';
import { Landmark, CreditCard, CheckCircle2, ShieldCheck, Plus, MoreHorizontal, Edit, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent } from '@/components/ui/card';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface BankItem {
  id: number;
  name: string;
  code_etablissement?: string;
  swift_code?: string;
  comptes_count?: number;
}

interface OwnerItem {
  id: number;
  nom_complet: string;
  role?: string;
}

interface CompteBancaireItem {
  id: number;
  owner_id: number;
  owner_type?: string;
  titulaire_nom: string;
  bank_id?: number;
  bank_name?: string;
  masked_rib: string;
  is_default: boolean;
  bank?: BankItem;
  owner?: {
    id: number;
    nom_complet: string;
    email: string;
    telephone?: string;
    role?: string;
  };
}

interface BanksIndexProps {
  comptes: {
    data: CompteBancaireItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  banks: BankItem[];
  owners?: OwnerItem[];
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

export default function BanksIndex({ comptes, banks, owners = [], stats, filters }: BanksIndexProps) {
  const [activeTab, setActiveTab] = useState(filters.tab || 'comptes');

  const [formOpen, setFormOpen] = useState(false);
  const [editingCompte, setEditingCompte] = useState<CompteBancaireItem | null>(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingCompte, setDeletingCompte] = useState<CompteBancaireItem | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const handleCreate = () => {
    setEditingCompte(null);
    setFormOpen(true);
  };

  const handleEdit = (c: CompteBancaireItem) => {
    setEditingCompte(c);
    setFormOpen(true);
  };

  const handleDeleteClick = (c: CompteBancaireItem) => {
    setDeletingCompte(c);
    setDeleteOpen(true);
  };

  const handleConfirmDelete = () => {
    if (!deletingCompte) return;
    setIsDeleting(true);
    router.delete(`/banks/comptes/${deletingCompte.id}`, {
      onFinish: () => {
        setIsDeleting(false);
        setDeleteOpen(false);
        setDeletingCompte(null);
      },
    });
  };

  const handleBankFilter = (bankId?: string) => {
    router.get(
      '/banks',
      {
        ...filters,
        bank_id: bankId ?? 'all',
        page: 1,
      },
      { preserveState: true }
    );
  };

  const currentBank = banks.find((b) => b.id.toString() === filters.bank_id);

  const columns: ColumnDef<DataTableFeatures, CompteBancaireItem>[] = [
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
      accessorKey: 'titulaire_nom',
      header: 'Titulaire du Compte',
      cell: ({ row }) => (
        <div>
          <span className="font-semibold text-foreground block">
            {row.original.titulaire_nom}
          </span>
          <span className="text-xs text-muted-foreground block font-mono">
            {row.original.owner?.nom_complet || 'Client OUAISS'} ({row.original.owner_type || 'client'})
          </span>
        </div>
      ),
    },
    {
      id: 'banque',
      header: 'Établissement BAM',
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <div className="h-7 w-7 rounded-md bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300 flex items-center justify-center font-bold text-xs shrink-0">
            <Landmark className="h-3.5 w-3.5" />
          </div>
          <div>
            <span className="font-medium text-foreground block">
              {row.original.bank?.name || row.original.bank_name || 'Banque BAM'}
            </span>
            <span className="text-xs text-muted-foreground block font-mono">
              Code: {row.original.bank?.code_etablissement || 'BAM'}
            </span>
          </div>
        </div>
      ),
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
            <Button
              onClick={handleCreate}
              className="bg-[#004c68] hover:bg-[#00384d] text-white rounded-md shadow-2xs text-xs font-semibold cursor-pointer"
            >
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
              Catalogue Officiel BAM ({stats.total_banks})
            </TabsTrigger>
          </TabsList>

          <TabsContent value="comptes" className="space-y-3">
            <PageToolbar
              searchPlaceholder="Rechercher par titulaire, banque, marchand..."
              initialSearch={filters.search}
              currentParams={filters}
            >
              {/* Filtre Banque */}
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm" className="h-8 text-xs rounded-md">
                    {currentBank ? `Banque: ${currentBank.name}` : 'Toutes les banques'}
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-56 max-h-60 overflow-y-auto">
                  <DropdownMenuItem onClick={() => handleBankFilter(undefined)} className="text-xs cursor-pointer">
                    Toutes les banques
                  </DropdownMenuItem>
                  {banks.map((b) => (
                    <DropdownMenuItem key={b.id} onClick={() => handleBankFilter(b.id.toString())} className="text-xs cursor-pointer">
                      {b.name} (Code {b.code_etablissement})
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
              emptyMessage="Aucun compte bancaire enregistré"
            />
          </TabsContent>

          <TabsContent value="catalogue">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {banks.map((b) => (
                <Card key={b.id} className="p-4 rounded-lg bg-card border-border shadow-xs">
                  <CardContent className="p-0 space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-sm text-foreground">{b.name}</span>
                      <Badge className="bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300 border-none font-mono text-xs">
                        Code {b.code_etablissement}
                      </Badge>
                    </div>
                    <div className="text-xs text-muted-foreground flex items-center justify-between">
                      <span>Code SWIFT : <span className="font-mono">{b.swift_code || '-'}</span></span>
                      <span>Comptes liés : <strong>{b.comptes_count ?? 0}</strong></span>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </TabsContent>
        </Tabs>
      </div>

      {/* Modals Dialog CRUD */}
      <CompteBancaireFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        compte={editingCompte}
        banks={banks}
        owners={owners}
      />

      <ConfirmDeleteDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title="Supprimer les coordonnées bancaires"
        description={`Êtes-vous sûr de vouloir supprimer le compte bancaire de "${deletingCompte?.titulaire_nom}" ?`}
        onConfirm={handleConfirmDelete}
        isLoading={isDeleting}
      />
    </AuthenticatedLayout>
  );
}
