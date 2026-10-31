import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
  Copy,
  Eye,
  KeyRound,
  Loader2,
  MoreHorizontal,
  Pencil,
  Plus,
  RefreshCw,
  RotateCcw,
  ShieldCheck,
  Trash2,
  UserCog,
  Users,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { ServerDataTable, type DataTableFeatures } from '@/components/common/ServerDataTable';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PermissionCatalogData } from '@/components/admin/types';
import { RoleFormDialog, type EditableRole } from './RoleFormDialog';
import { RoleDetailsSheet } from './RoleDetailsSheet';

interface RoleRow {
  id: number;
  name: string;
  guard_name: string;
  is_system: boolean;
  permissions_count: number;
  users_count: number;
  created_at: string | null;
  can: { update: boolean; delete: boolean; duplicate: boolean };
}

interface Filters {
  search: string;
  type: string;
  permissions: string;
  users: string;
  guard: string;
  sort: string;
  direction: string;
  per_page: number;
}

interface RolesIndexProps {
  roles: {
    data: RoleRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  stats: { total: number; system: number; custom: number; users_with_roles: number };
  catalog: PermissionCatalogData;
  guards: string[];
  canCreate: boolean;
  filters: Filters;
}

const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' });

export default function RolesIndex({ roles, stats, catalog, guards, canCreate, filters }: RolesIndexProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<EditableRole | null>(null);
  const [locked, setLocked] = useState<string[]>([]);
  const [loadingEditId, setLoadingEditId] = useState<number | null>(null);
  const [detailsId, setDetailsId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<RoleRow | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const applyFilter = (key: keyof Filters, value: string) =>
    router.get('/roles', { ...filters, [key]: value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });

  const isFiltered =
    Boolean(filters.search) || ['type', 'permissions', 'users', 'guard'].some((k) => filters[k as keyof Filters] !== 'all');

  const onError = (errors: Record<string, string>) => setActionError(Object.values(errors)[0] ?? 'Action refusée.');

  const openCreate = () => {
    setEditing(null);
    setLocked([]);
    setFormOpen(true);
  };

  const openEdit = async (row: RoleRow) => {
    setLoadingEditId(row.id);
    setActionError(null);
    try {
      const res = await fetch(`/roles/${row.id}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(String(res.status));
      const role = await res.json();
      setEditing({ id: role.id, name: role.name, guard_name: role.guard_name, is_system: role.is_system, permissions: role.permissions });
      setLocked(role.locked_permissions ?? []);
      setFormOpen(true);
    } catch {
      setActionError('Impossible de charger le rôle pour modification.');
    } finally {
      setLoadingEditId(null);
    }
  };

  const duplicate = (row: RoleRow) => {
    setActionError(null);
    router.post(`/roles/${row.id}/duplicate`, {}, { preserveScroll: true, onError });
  };

  const confirmDelete = () => {
    if (!deleting) return;
    setIsDeleting(true);
    router.delete(`/roles/${deleting.id}`, {
      preserveScroll: true,
      onError,
      onFinish: () => {
        setIsDeleting(false);
        setDeleting(null);
      },
    });
  };

  const refresh = () =>
    router.reload({ only: ['roles', 'stats', 'catalog'], onStart: () => setRefreshing(true), onFinish: () => setRefreshing(false) });

  const columns: ColumnDef<DataTableFeatures, RoleRow>[] = [
    {
      id: 'select',
      header: ({ table }) => (
        <Checkbox
          checked={table.getIsAllPageRowsSelected()}
          indeterminate={table.getIsSomePageRowsSelected() && !table.getIsAllPageRowsSelected()}
          onCheckedChange={(v) => table.toggleAllPageRowsSelected(Boolean(v))}
          aria-label="Sélectionner tous les rôles de la page"
          className="rounded-[3px]"
        />
      ),
      cell: ({ row }) => (
        <Checkbox
          checked={row.getIsSelected()}
          onCheckedChange={(v) => row.toggleSelected(Boolean(v))}
          aria-label={`Sélectionner ${row.original.name}`}
          className="rounded-[3px]"
        />
      ),
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'name',
      header: 'Nom du rôle',
      cell: ({ row }) => (
        <button
          type="button"
          onClick={() => setDetailsId(row.original.id)}
          className="max-w-56 truncate text-left font-semibold text-foreground hover:text-[#004c68] hover:underline dark:hover:text-[#ff8806]"
        >
          {row.original.name}
        </button>
      ),
    },
    {
      id: 'type',
      header: 'Type',
      enableSorting: false,
      cell: ({ row }) =>
        row.original.is_system ? (
          <Badge variant="secondary" className="rounded-md text-[10px]">Système</Badge>
        ) : (
          <Badge variant="outline" className="rounded-md border-[#ff8806]/40 text-[10px] text-[#c96400] dark:text-[#ff8806]">Personnalisé</Badge>
        ),
    },
    {
      accessorKey: 'permissions_count',
      header: 'Permissions',
      cell: ({ row }) => (
        <span className="inline-flex items-center gap-1.5 font-mono text-xs">
          <KeyRound className="size-3.5 text-muted-foreground" />
          {row.original.permissions_count}
        </span>
      ),
    },
    {
      accessorKey: 'users_count',
      header: 'Utilisateurs',
      cell: ({ row }) => (
        <span className="inline-flex items-center gap-1.5 font-mono text-xs">
          <Users className="size-3.5 text-muted-foreground" />
          {row.original.users_count}
        </span>
      ),
    },
    {
      accessorKey: 'guard_name',
      header: 'Guard',
      enableSorting: false,
      cell: ({ row }) => <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">{row.original.guard_name}</code>,
    },
    {
      accessorKey: 'created_at',
      header: 'Créé le',
      cell: ({ row }) => (
        <span className="text-xs text-muted-foreground">
          {row.original.created_at ? dateFormat.format(new Date(row.original.created_at)) : '—'}
        </span>
      ),
    },
    {
      id: 'actions',
      header: () => <span className="sr-only">Actions</span>,
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const r = row.original;
        return (
          <div className="flex justify-end">
            <DropdownMenu>
              <DropdownMenuTrigger
                render={<Button variant="ghost" size="icon-sm" className="rounded-md" aria-label={`Actions pour ${r.name}`} />}
              >
                {loadingEditId === r.id ? <Loader2 className="size-4 animate-spin" /> : <MoreHorizontal className="size-4 text-muted-foreground" />}
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuItem onClick={() => setDetailsId(r.id)} className="cursor-pointer gap-2 text-xs">
                  <Eye className="size-3.5" /> Voir les détails
                </DropdownMenuItem>
                {r.can.update && (
                  <DropdownMenuItem onClick={() => openEdit(r)} className="cursor-pointer gap-2 text-xs">
                    <Pencil className="size-3.5" /> Modifier
                  </DropdownMenuItem>
                )}
                {r.can.duplicate && (
                  <DropdownMenuItem onClick={() => duplicate(r)} className="cursor-pointer gap-2 text-xs">
                    <Copy className="size-3.5" /> Dupliquer
                  </DropdownMenuItem>
                )}
                {r.can.delete && (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      onClick={() => setDeleting(r)}
                      className="cursor-pointer gap-2 text-xs text-destructive focus:text-destructive"
                    >
                      <Trash2 className="size-3.5" /> Supprimer
                    </DropdownMenuItem>
                  </>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        );
      },
    },
  ];

  return (
    <AuthenticatedLayout title="Gestion des rôles">
      <Head title="Gestion des rôles - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Gestion des rôles"
          description="Créez, organisez et gérez les rôles et leurs autorisations."
          icon={ShieldCheck}
          actions={
            <>
              <Button variant="outline" size="sm" onClick={refresh} disabled={refreshing} className="h-8 rounded-md text-xs">
                <RefreshCw className={`size-3.5 ${refreshing ? 'animate-spin' : ''}`} />
                Actualiser
              </Button>
              {canCreate && (
                <Button size="sm" onClick={openCreate} className="h-8 rounded-md bg-[#004c68] text-xs font-semibold text-white hover:bg-[#00384d]">
                  <Plus className="size-3.5" />
                  Nouveau rôle
                </Button>
              )}
            </>
          }
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Total des rôles" value={stats.total} description="Tous guards confondus" icon={ShieldCheck} />
          <StatsCard title="Rôles système" value={stats.system} description="Rôles de base de la plateforme" icon={UserCog} />
          <StatsCard
            title="Rôles personnalisés"
            value={stats.custom}
            description="Créés par l'administration"
            icon={KeyRound}
            iconColor="text-[#ff8806]"
          />
          <StatsCard title="Utilisateurs avec rôle" value={stats.users_with_roles} description="Comptes ayant au moins un rôle" icon={Users} />
        </div>

        {actionError && (
          <div role="alert" className="flex items-center justify-between gap-3 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-xs font-medium text-destructive">
            {actionError}
            <button type="button" className="underline" onClick={() => setActionError(null)}>Fermer</button>
          </div>
        )}

        <div className="space-y-3">
          <PageToolbar
            searchPlaceholder="Rechercher un rôle par nom…"
            initialSearch={filters.search}
            currentParams={filters}
            rightActions={
              isFiltered && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-8 rounded-md text-xs"
                  onClick={() => router.get('/roles', {}, { preserveState: false, replace: true })}
                >
                  <RotateCcw className="size-3.5" />
                  Réinitialiser
                </Button>
              )
            }
          >
            <FilterDropdown
              label="Type"
              value={filters.type}
              onChange={(v) => applyFilter('type', v)}
              options={[
                { value: 'all', label: 'Tous' },
                { value: 'system', label: 'Système' },
                { value: 'custom', label: 'Personnalisés' },
              ]}
            />
            <FilterDropdown
              label="Permissions"
              value={filters.permissions}
              onChange={(v) => applyFilter('permissions', v)}
              options={[
                { value: 'all', label: 'Tous' },
                { value: 'some', label: 'Avec permissions' },
                { value: 'none', label: 'Sans permission' },
              ]}
            />
            <FilterDropdown
              label="Utilisateurs"
              value={filters.users}
              onChange={(v) => applyFilter('users', v)}
              options={[
                { value: 'all', label: 'Tous' },
                { value: 'some', label: 'Avec utilisateurs' },
                { value: 'none', label: 'Sans utilisateur' },
              ]}
            />
            {guards.length > 0 && (
              <FilterDropdown
                label="Guard"
                value={filters.guard}
                onChange={(v) => applyFilter('guard', v)}
                options={[{ value: 'all', label: 'Tous' }, ...guards.map((g) => ({ value: g, label: g }))]}
              />
            )}
          </PageToolbar>

          <ServerDataTable
            columns={columns}
            data={roles.data}
            paginationMeta={roles}
            currentParams={filters}
            emptyMessage={isFiltered ? 'Aucun rôle ne correspond à ces filtres' : 'Aucun rôle enregistré'}
          />
        </div>
      </div>

      <RoleFormDialog open={formOpen} onOpenChange={setFormOpen} role={editing} catalog={catalog} locked={locked} />
      <RoleDetailsSheet roleId={detailsId} onOpenChange={(open) => !open && setDetailsId(null)} catalog={catalog} />
      <ConfirmDeleteDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title="Supprimer le rôle"
        description={`Le rôle « ${deleting?.name ?? ''} » sera supprimé définitivement. Aucun utilisateur ne le possède ; ses permissions restent disponibles dans le catalogue.`}
        onConfirm={confirmDelete}
        isLoading={isDeleting}
      />
    </AuthenticatedLayout>
  );
}
