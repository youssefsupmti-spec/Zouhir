import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Bike, Building2, CreditCard, Landmark, Pencil, Plus, Star, Store, Trash2, Users } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { CompteBancaireFormDialog } from './CompteBancaireFormDialog';

type Onglet = 'tous' | 'client' | 'livreur' | 'a_verifier';

interface CompteLigne {
  id: number;
  owner_id: number;
  owner_type: string | null;
  titulaire_nom: string;
  bank_id: number | null;
  bank_name: string | null;
  bank_code: string | null;
  bank_active: boolean;
  masked_rib: string;
  rib_conforme: boolean;
  code_coherent: boolean;
  is_default: boolean;
  owner: { id: number; nom_complet: string; email: string | null; telephone: string | null } | null;
  business: string[];
}

interface Props {
  comptes: LaravelPaginationMeta & { data: CompteLigne[] };
  banks: { id: number; name: string; code_etablissement: string | null; status: string }[];
  owners: { id: number; nom_complet: string; role: string | null; business: string | null }[];
  stats: { total_comptes: number; client: number; livreur: number; a_verifier: number; titulaires: number; business_total: number; business_avec_rib: number };
  filters: { onglet: Onglet; search: string; bank_id: number | null; per_page: number };
  can: { manage: boolean };
}

export default function ComptesBancairesIndex({ comptes, banks, owners, stats, filters, can }: Props) {
  const [formOpen, setFormOpen] = useState(false);
  const [edition, setEdition] = useState<CompteLigne | null>(null);
  const [suppression, setSuppression] = useState<CompteLigne | null>(null);
  const [busy, setBusy] = useState(false);
  const params = { ...filters, bank_id: filters.bank_id ?? undefined };

  const naviguer = (changes: Record<string, unknown>) =>
    router.get('/comptes-bancaires', { ...params, ...changes, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
  const ouvrir = (c: CompteLigne | null) => { setEdition(c); setFormOpen(true); };

  return (
    <AuthenticatedLayout title="Comptes bancaires">
      <Head title="Comptes bancaires - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Comptes bancaires"
          description={can.manage
            ? 'RIB de reversement CRBT de tous les business et livreurs. Les RIB sont masqués en liste.'
            : 'Vos RIB de reversement CRBT. Les RIB sont masqués en liste.'}
          icon={CreditCard}
          actions={(
            <Button size="sm" className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]" onClick={() => ouvrir(null)}>
              <Plus className="size-3.5" /> Nouveau compte
            </Button>
          )}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Comptes bancaires" value={stats.total_comptes} description={`${stats.titulaires} titulaire(s)`} icon={CreditCard} />
          <StatsCard title="Business avec RIB" value={`${stats.business_avec_rib} / ${stats.business_total}`}
            description={`${Math.max(stats.business_total - stats.business_avec_rib, 0)} business sans RIB`} icon={Building2} iconColor="text-emerald-600 dark:text-emerald-400" />
          <StatsCard title="Comptes livreurs" value={stats.livreur} description="Reversements des commissions" icon={Bike} />
          <StatsCard title="RIB à vérifier" value={stats.a_verifier} description="Pas 24 chiffres" icon={AlertTriangle} iconColor="text-[#ff8806]" />
        </div>

        <TarifsTabs
          label="Filtrer les comptes"
          value={filters.onglet}
          onValueChange={(v) => naviguer({ onglet: v === 'tous' ? undefined : v })}
          onglets={[
            { value: 'tous', label: 'Tous', count: stats.total_comptes, icon: <CreditCard className="size-3.5" /> },
            { value: 'client', label: 'Business', count: stats.client, icon: <Store className="size-3.5" /> },
            { value: 'livreur', label: 'Livreurs', count: stats.livreur, icon: <Bike className="size-3.5" /> },
            { value: 'a_verifier', label: 'RIB à vérifier', count: stats.a_verifier, icon: <AlertTriangle className="size-3.5" />, tone: 'danger' },
          ]}
        />

        <PageToolbar searchPlaceholder="Business, titulaire, e-mail ou banque…" initialSearch={filters.search} currentParams={params}>
          <FilterDropdown
            label="Banque"
            value={filters.bank_id ? String(filters.bank_id) : 'all'}
            options={[{ value: 'all', label: 'Toutes' }, ...banks.map((b) => ({ value: String(b.id), label: b.name }))]}
            onChange={(v) => naviguer({ bank_id: v === 'all' ? undefined : v })}
          />
        </PageToolbar>

        <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
          <Table className="min-w-[960px] text-xs">
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="pl-4">Business / titulaire</TableHead>
                <TableHead>Intitulé du compte</TableHead>
                <TableHead>Banque</TableHead>
                <TableHead className="w-56">RIB</TableHead>
                <TableHead className="w-28">Rôle</TableHead>
                <TableHead className="w-24 pr-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {comptes.data.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="pl-4">
                    <div className="flex items-center gap-2">
                      <span className={`inline-flex size-7 shrink-0 items-center justify-center rounded-md ${c.owner_type === 'livreur' ? 'bg-sky-500/10 text-sky-600' : 'bg-[#ff8806]/10 text-[#ff8806]'}`}>
                        {c.owner_type === 'livreur' ? <Bike className="size-3.5" /> : <Store className="size-3.5" />}
                      </span>
                      <div className="min-w-0">
                        <div className="truncate font-semibold">{c.business.length > 0 ? c.business.join(', ') : (c.owner?.nom_complet ?? '—')}</div>
                        <div className="truncate text-[11px] text-muted-foreground">
                          {c.business.length > 0 && c.owner ? `${c.owner.nom_complet} · ` : ''}{c.owner?.email ?? ''}
                        </div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="font-medium">{c.titulaire_nom}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Landmark className="size-3.5 shrink-0 text-[#004c68] dark:text-cyan-300" />
                      <div>
                        <div className="font-medium">{c.bank_name ?? '—'}</div>
                        <div className="text-[10px] text-muted-foreground">
                          {c.bank_code ? `Code ${c.bank_code}` : 'Sans code'}{c.bank_active ? '' : ' · banque désactivée'}
                        </div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="space-y-1">
                      <span className="rounded-sm bg-muted/60 px-2 py-0.5 font-mono text-[11px]">{c.masked_rib}</span>
                      {(!c.rib_conforme || !c.code_coherent) && (
                        <div className="flex items-center gap-1 text-[10px] font-medium text-[#ff8806]">
                          <AlertTriangle className="size-3" />
                          {!c.rib_conforme ? 'RIB incomplet (pas 24 chiffres)' : 'Code banque différent de la banque choisie'}
                        </div>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    {c.is_default
                      ? <span className="inline-flex items-center gap-1 rounded-full bg-emerald-600/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400"><Star className="size-3" /> Principal</span>
                      : <span className="text-[11px] text-muted-foreground">Secondaire</span>}
                  </TableCell>
                  <TableCell className="pr-4 text-right">
                    <span className="inline-flex gap-0.5">
                      <Button size="icon" variant="ghost" className="size-7" aria-label={`Modifier le compte de ${c.titulaire_nom}`} title="Modifier" onClick={() => ouvrir(c)}>
                        <Pencil className="size-3.5 text-[#ff8806]" />
                      </Button>
                      <Button size="icon" variant="ghost" className="size-7" aria-label={`Supprimer le compte de ${c.titulaire_nom}`} title="Supprimer" onClick={() => setSuppression(c)}>
                        <Trash2 className="size-3.5 text-destructive" />
                      </Button>
                    </span>
                  </TableCell>
                </TableRow>
              ))}
              {comptes.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="py-12 text-center text-muted-foreground">
                    <Users className="mx-auto mb-2 size-5 opacity-50" />
                    {filters.search || filters.bank_id ? 'Aucun compte ne correspond aux filtres.' : 'Aucun compte bancaire enregistré.'}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        {comptes.total > 0 && <DataTablePagination meta={comptes} currentParams={params} pageSizeOptions={[10, 25, 50, 100]} />}
      </div>

      <CompteBancaireFormDialog open={formOpen} onOpenChange={setFormOpen} compte={edition} banks={banks} owners={owners} />
      <ConfirmDeleteDialog
        open={suppression !== null}
        onOpenChange={(o) => !o && !busy && setSuppression(null)}
        title="Supprimer les coordonnées bancaires"
        description={`Supprimer le compte « ${suppression?.titulaire_nom ?? ''} » (${suppression?.bank_name ?? ''}) ?`}
        isLoading={busy}
        onConfirm={() => suppression && router.delete(`/comptes-bancaires/${suppression.id}`, {
          preserveScroll: true,
          onStart: () => setBusy(true),
          onFinish: () => { setBusy(false); setSuppression(null); },
        })}
      />
    </AuthenticatedLayout>
  );
}
