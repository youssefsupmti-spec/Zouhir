import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Boxes, CheckCircle2, CircleSlash, KeyRound, LockKeyhole, RefreshCw, RotateCcw, Search } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { PermissionGroupCard, PermissionStatusBadge, type PermissionGroup, type PermissionRow } from './PermissionGroupCard';

interface Filters {
  search: string;
  key: string;
  module: string;
  guard: string;
  status: string;
}

interface PermissionsIndexProps {
  groups: PermissionGroup[];
  stats: { total: number; modules: number; assigned: number; unassigned: number };
  modules: { key: string; label: string }[];
  guards: string[];
  missingFromDatabase: string[];
  canSynchronize: boolean;
  filters: Filters;
}

export default function PermissionsIndex({ groups, stats, modules, guards, missingFromDatabase, canSynchronize, filters }: PermissionsIndexProps) {
  const [selected, setSelected] = useState<PermissionRow | null>(null);
  const [deleting, setDeleting] = useState<PermissionRow | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [keyTerm, setKeyTerm] = useState(filters.key ?? '');

  const applyFilter = (name: keyof Filters, value: string) =>
    router.get('/permissions', { ...filters, [name]: value }, { preserveState: true, preserveScroll: true, replace: true });

  // Recherche par clé technique (debounce, comme la recherche principale).
  useEffect(() => {
    if (keyTerm === (filters.key ?? '')) return;
    const t = setTimeout(() => applyFilter('key', keyTerm), 350);
    return () => clearTimeout(t);
  }, [keyTerm]);

  const isFiltered = Boolean(filters.search || filters.key) || ['module', 'guard', 'status'].some((k) => filters[k as keyof Filters] !== 'all');
  const onError = (errors: Record<string, string>) => setError(Object.values(errors)[0] ?? 'Action refusée.');

  const synchronize = () =>
    router.post('/permissions/sync', {}, { preserveScroll: true, onError, onStart: () => setSyncing(true), onFinish: () => setSyncing(false) });

  const confirmDelete = () => {
    if (!deleting) return;
    setIsDeleting(true);
    router.delete(`/permissions/${deleting.id}`, {
      preserveScroll: true,
      onError,
      onFinish: () => {
        setIsDeleting(false);
        setDeleting(null);
      },
    });
  };

  return (
    <AuthenticatedLayout title="Gestion des permissions">
      <Head title="Gestion des permissions - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Gestion des permissions"
          description="Catalogue central des autorisations de la plateforme."
          icon={LockKeyhole}
          actions={
            canSynchronize && missingFromDatabase.length > 0 ? (
              <Button size="sm" onClick={synchronize} disabled={syncing} className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]">
                <RefreshCw className={`size-3.5 ${syncing ? 'animate-spin' : ''}`} />
                Synchroniser le catalogue ({missingFromDatabase.length})
              </Button>
            ) : undefined
          }
        />

        <div className="flex items-start gap-2.5 rounded-md border border-[#004c68]/20 bg-[#004c68]/5 px-4 py-3 text-xs text-foreground">
          <AlertTriangle className="mt-0.5 size-4 shrink-0 text-[#ff8806]" />
          <p>
            Une permission n'a d'effet que si elle est <strong>déclarée dans le code</strong> et <strong>reliée à une règle</strong>{' '}
            (Policy, Gate, contrôleur). Le catalogue ne permet donc pas de créer des permissions arbitraires : la synchronisation
            enregistre seulement les permissions déclarées manquantes. Une permission non attribuée à un rôle n'est pas forcément inutilisée.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Total des permissions" value={stats.total} description="Enregistrées en base" icon={KeyRound} />
          <StatsCard title="Modules" value={stats.modules} description="Groupes du catalogue" icon={Boxes} />
          <StatsCard
            title="Attribuées à des rôles"
            value={stats.assigned}
            description="Au moins un rôle"
            icon={CheckCircle2}
            iconColor="text-emerald-600 dark:text-emerald-400"
          />
          <StatsCard
            title="Non attribuées"
            value={stats.unassigned}
            description="Aucun rôle (peuvent être directes)"
            icon={CircleSlash}
            iconColor="text-[#ff8806]"
          />
        </div>

        {error && (
          <div role="alert" className="flex items-center justify-between gap-3 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-xs font-medium text-destructive">
            {error}
            <button type="button" className="underline" onClick={() => setError(null)}>Fermer</button>
          </div>
        )}

        <PageToolbar
          searchPlaceholder="Rechercher par nom ou description…"
          initialSearch={filters.search}
          currentParams={filters}
          rightActions={
            isFiltered && (
              <Button variant="ghost" size="sm" className="h-8 rounded-md text-xs"
                onClick={() => { setKeyTerm(''); router.get('/permissions', {}, { replace: true }); }}>
                <RotateCcw className="size-3.5" />
                Réinitialiser
              </Button>
            )
          }
        >
          <div className="relative w-full sm:w-52">
            <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={keyTerm}
              onChange={(e) => setKeyTerm(e.target.value)}
              placeholder="Clé technique (ex. ActAs)"
              className="h-8 rounded-md bg-background pl-8 font-mono text-xs"
              aria-label="Rechercher par clé technique"
            />
          </div>
          <FilterDropdown
            label="Module"
            value={filters.module}
            onChange={(v) => applyFilter('module', v)}
            options={[{ value: 'all', label: 'Tous' }, ...modules.map((m) => ({ value: m.key, label: m.label }))]}
          />
          <FilterDropdown
            label="Guard"
            value={filters.guard}
            onChange={(v) => applyFilter('guard', v)}
            options={[{ value: 'all', label: 'Tous' }, ...guards.map((g) => ({ value: g, label: g }))]}
          />
          <FilterDropdown
            label="Attribution"
            value={filters.status}
            onChange={(v) => applyFilter('status', v)}
            options={[
              { value: 'all', label: 'Toutes' },
              { value: 'assigned', label: 'Attribuées' },
              { value: 'unassigned', label: 'Non attribuées' },
            ]}
          />
        </PageToolbar>

        {groups.length === 0 ? (
          <div className="rounded-md border border-dashed border-border bg-card p-10 text-center text-xs text-muted-foreground">
            Aucune permission ne correspond à ces filtres.
          </div>
        ) : (
          <div className="space-y-3">
            {groups.map((group) => (
              <PermissionGroupCard key={group.key} group={group} onSelect={setSelected} onDelete={setDeleting} />
            ))}
          </div>
        )}
      </div>

      <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
        <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
          {selected && (
            <>
              <SheetHeader className="border-b border-border px-5 py-4">
                <SheetTitle className="text-base font-bold">{selected.label}</SheetTitle>
                <SheetDescription className="font-mono text-[11px]">{selected.name}</SheetDescription>
              </SheetHeader>
              <div className="space-y-4 overflow-y-auto px-5 py-4 text-xs">
                <div className="flex flex-wrap gap-1.5">
                  <PermissionStatusBadge permission={selected} />
                  {selected.critical && <Badge variant="destructive" className="rounded-md text-[10px]">Critique</Badge>}
                  <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">guard : {selected.guard_name}</code>
                </div>
                {selected.description && <p className="text-muted-foreground">{selected.description}</p>}
                <section>
                  <h3 className="mb-1.5 font-semibold">Règles qui l'appliquent</h3>
                  {selected.enforced_by.length === 0 ? (
                    <p className="text-muted-foreground">Aucune règle d'autorisation ne vérifie cette permission à ce jour.</p>
                  ) : (
                    <ul className="flex flex-wrap gap-1.5">
                      {selected.enforced_by.map((rule) => (
                        <li key={rule}><code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">{rule}</code></li>
                      ))}
                    </ul>
                  )}
                </section>
                <section>
                  <h3 className="mb-1.5 font-semibold">Rôles ({selected.roles_count})</h3>
                  {selected.roles.length === 0 ? (
                    <p className="text-muted-foreground">Attribuée à aucun rôle.</p>
                  ) : (
                    <ul className="flex flex-wrap gap-1.5">
                      {selected.roles.map((r) => <li key={r}><Badge variant="secondary" className="rounded-md text-[10px]">{r}</Badge></li>)}
                    </ul>
                  )}
                </section>
                <p className="text-muted-foreground">
                  Attribuée directement à {selected.direct_users_count} utilisateur(s) (hors rôles).
                </p>
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>

      <ConfirmDeleteDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title="Supprimer une permission hors catalogue"
        description={`La permission « ${deleting?.name ?? ''} » n'est déclarée nulle part dans le code et n'est attribuée à aucun rôle ni utilisateur.`}
        onConfirm={confirmDelete}
        isLoading={isDeleting}
      />
    </AuthenticatedLayout>
  );
}
