import { useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, CreditCard, Landmark, Loader2, PauseCircle, Pencil, Plus, Trash2, Undo2, XCircle } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';

type Statut = 'active' | 'inactive' | 'suspendue';
type Onglet = 'toutes' | Statut | 'corbeille';

interface BankLigne {
  id: number;
  name: string;
  code_etablissement: string | null;
  swift_code: string | null;
  status: Statut;
  comptes_count: number;
  deleted_at: string | null;
}

interface Props {
  banks: LaravelPaginationMeta & { data: BankLigne[] };
  stats: { toutes: number; active: number; inactive: number; suspendue: number; corbeille: number; comptes: number; banques_utilisees: number };
  filters: { onglet: Onglet; search: string; per_page: number };
  can: { manage: boolean };
}

const STATUTS: Record<Statut, { label: string; classe: string }> = {
  active: { label: 'Active', classe: 'border-emerald-600/25 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400' },
  inactive: { label: 'Inactive', classe: 'border-border bg-muted text-muted-foreground' },
  suspendue: { label: 'Suspendue', classe: 'border-destructive/25 bg-destructive/10 text-destructive' },
};

/** Initiales de la banque en pastille (aucun logo stocké pour l'instant). */
function Monogramme({ nom }: { nom: string }) {
  const initiales = nom.replace(/\(.*?\)/g, '').split(/\s+/).filter((m) => m.length > 2 || /^[A-Z]{2,}$/.test(m)).slice(0, 2).map((m) => m[0]).join('').toUpperCase() || nom[0];
  return (
    <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-[#004c68]/10 text-[11px] font-bold text-[#004c68] dark:bg-[#004c68]/30 dark:text-cyan-300">
      {initiales}
    </span>
  );
}

export default function BanksIndex({ banks, stats, filters, can }: Props) {
  const [edition, setEdition] = useState<BankLigne | 'nouvelle' | null>(null);
  const [suppression, setSuppression] = useState<BankLigne | null>(null);
  const [busy, setBusy] = useState(false);
  const corbeille = filters.onglet === 'corbeille';
  const params = { ...filters };

  const onglet = (v: string) =>
    router.get('/banks', { ...params, onglet: v === 'toutes' ? undefined : v, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
  const options = (apres?: () => void) => ({
    preserveScroll: true,
    onStart: () => setBusy(true),
    onFinish: () => setBusy(false),
    onSuccess: () => apres?.(),
  });
  const changerStatut = (b: BankLigne, status: Statut) => router.patch(`/banks/${b.id}/statut`, { status }, options());

  return (
    <AuthenticatedLayout title="Banques">
      <Head title="Banques - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Banques"
          description="Référentiel des banques marocaines proposées pour les RIB de reversement CRBT."
          icon={Landmark}
          actions={can.manage ? (
            <Button size="sm" className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]" onClick={() => setEdition('nouvelle')}>
              <Plus className="size-3.5" /> Nouvelle banque
            </Button>
          ) : undefined}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Banques" value={stats.toutes} description={`${stats.active} active(s)`} icon={Landmark} />
          <StatsCard title="Proposées aux RIB" value={stats.active} description="Statut actif" icon={CheckCircle2} iconColor="text-emerald-600 dark:text-emerald-400" />
          <StatsCard title="Désactivées" value={stats.inactive + stats.suspendue} description={`${stats.inactive} inactive(s) · ${stats.suspendue} suspendue(s)`} icon={PauseCircle} />
          <StatsCard title="Comptes bancaires" value={stats.comptes} description={`répartis sur ${stats.banques_utilisees} banque(s)`} icon={CreditCard} iconColor="text-[#ff8806]" />
        </div>

        <TarifsTabs
          label="Filtrer les banques"
          value={filters.onglet}
          onValueChange={onglet}
          onglets={[
            { value: 'toutes', label: 'Toutes', count: stats.toutes, icon: <Landmark className="size-3.5" /> },
            { value: 'active', label: 'Actives', count: stats.active, icon: <CheckCircle2 className="size-3.5" /> },
            { value: 'inactive', label: 'Inactives', count: stats.inactive, icon: <PauseCircle className="size-3.5" />, tone: 'muted' },
            { value: 'suspendue', label: 'Suspendues', count: stats.suspendue, icon: <XCircle className="size-3.5" />, tone: 'muted' },
            { value: 'corbeille', label: 'Corbeille', count: stats.corbeille, icon: <Trash2 className="size-3.5" />, tone: 'danger' },
          ]}
        />

        <PageToolbar searchPlaceholder="Nom, code banque ou SWIFT…" initialSearch={filters.search} currentParams={params} />

        <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
          <Table className="min-w-[820px] text-xs">
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="pl-4">Banque</TableHead>
                <TableHead className="w-28 text-center">Code RIB</TableHead>
                <TableHead className="w-32">SWIFT</TableHead>
                <TableHead className="w-36">Statut</TableHead>
                <TableHead className="w-28 text-center">Comptes</TableHead>
                <TableHead className="w-28 pr-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {banks.data.map((b) => (
                <TableRow key={b.id}>
                  <TableCell className="pl-4">
                    <div className="flex items-center gap-2.5">
                      <Monogramme nom={b.name} />
                      <span className="font-semibold">{b.name}</span>
                    </div>
                  </TableCell>
                  <TableCell className="text-center">
                    {b.code_etablissement
                      ? <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-[11px] font-bold tracking-wider">{b.code_etablissement}</span>
                      : <span className="text-muted-foreground">—</span>}
                  </TableCell>
                  <TableCell className="font-mono text-[11px]">{b.swift_code || <span className="text-muted-foreground">—</span>}</TableCell>
                  <TableCell>
                    {can.manage && !corbeille ? (
                      <select
                        aria-label={`Statut de ${b.name}`}
                        value={b.status}
                        disabled={busy}
                        onChange={(e) => changerStatut(b, e.target.value as Statut)}
                        className={`h-6 cursor-pointer rounded-full border px-2 text-[10px] font-semibold outline-none ${STATUTS[b.status]?.classe ?? ''}`}
                      >
                        {(Object.keys(STATUTS) as Statut[]).map((s) => <option key={s} value={s} className="text-foreground dark:bg-slate-900">{STATUTS[s].label}</option>)}
                      </select>
                    ) : (
                      <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold ${STATUTS[b.status]?.classe ?? ''}`}>{STATUTS[b.status]?.label ?? b.status}</span>
                    )}
                  </TableCell>
                  <TableCell className="text-center">
                    {b.comptes_count > 0 ? (
                      <Link href={`/comptes-bancaires?bank_id=${b.id}`} className="inline-flex min-w-8 justify-center rounded-full bg-emerald-600/10 px-2 py-0.5 font-mono text-[11px] font-semibold text-emerald-700 hover:underline dark:text-emerald-400">
                        {b.comptes_count}
                      </Link>
                    ) : <span className="inline-flex min-w-8 justify-center rounded-full bg-muted px-2 py-0.5 font-mono text-[11px] text-muted-foreground">0</span>}
                  </TableCell>
                  <TableCell className="pr-4 text-right">
                    {can.manage && (corbeille ? (
                      <Button size="sm" variant="ghost" className="h-7 rounded-md px-2 text-[11px]" disabled={busy} onClick={() => router.post(`/banks/${b.id}/restaurer`, {}, options())}>
                        <Undo2 className="size-3.5" /> Restaurer
                      </Button>
                    ) : (
                      <span className="inline-flex gap-0.5">
                        <Button size="icon" variant="ghost" className="size-7" aria-label={`Modifier ${b.name}`} title="Modifier" onClick={() => setEdition(b)}>
                          <Pencil className="size-3.5 text-[#ff8806]" />
                        </Button>
                        <Button size="icon" variant="ghost" className="size-7" aria-label={`Mettre ${b.name} à la corbeille`} title={b.comptes_count > 0 ? 'Utilisée par des comptes : désactivez-la plutôt' : 'Mettre à la corbeille'}
                          disabled={b.comptes_count > 0} onClick={() => setSuppression(b)}>
                          <Trash2 className="size-3.5 text-destructive" />
                        </Button>
                      </span>
                    ))}
                  </TableCell>
                </TableRow>
              ))}
              {banks.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="py-12 text-center text-muted-foreground">
                    {filters.search ? 'Aucune banque ne correspond à la recherche.' : corbeille ? 'La corbeille est vide.' : 'Aucune banque dans cet onglet.'}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        {banks.total > 0 && <DataTablePagination meta={banks} currentParams={params} pageSizeOptions={[10, 25, 50, 100]} />}
      </div>

      <BanqueDialog bank={edition} onClose={() => setEdition(null)} />
      <ConfirmDeleteDialog
        open={suppression !== null}
        onOpenChange={(o) => !o && !busy && setSuppression(null)}
        title={`Mettre ${suppression?.name ?? ''} à la corbeille`}
        description="La banque ne sera plus proposée pour les nouveaux RIB. Vous pourrez la restaurer depuis la corbeille."
        isLoading={busy}
        onConfirm={() => suppression && router.delete(`/banks/${suppression.id}`, options(() => setSuppression(null)))}
      />
    </AuthenticatedLayout>
  );
}

function BanqueDialog({ bank, onClose }: { bank: BankLigne | 'nouvelle' | null; onClose: () => void }) {
  const existante = bank !== null && bank !== 'nouvelle' ? bank : null;
  const form = useForm({ name: '', code_etablissement: '', swift_code: '', status: 'active' as Statut });
  const [chargee, setChargee] = useState<BankLigne | 'nouvelle' | null>(null);

  if (bank !== chargee) {
    setChargee(bank);
    form.clearErrors();
    form.setData({
      name: existante?.name ?? '',
      code_etablissement: existante?.code_etablissement ?? '',
      swift_code: existante?.swift_code ?? '',
      status: existante?.status ?? 'active',
    });
  }

  const envoyer = (e: FormEvent) => {
    e.preventDefault();
    const fin = { preserveScroll: true, onSuccess: () => onClose() };
    if (existante) form.put(`/banks/${existante.id}`, fin);
    else form.post('/banks', fin);
  };
  const erreur = (m?: string) => m && <p className="text-[11px] font-medium text-destructive">{m}</p>;

  return (
    <Dialog open={bank !== null} onOpenChange={(o) => !o && !form.processing && onClose()}>
      <DialogContent className="sm:max-w-lg">
        <form onSubmit={envoyer} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{existante ? `Modifier ${existante.name}` : 'Nouvelle banque'}</DialogTitle>
            <DialogDescription>
              {existante && existante.comptes_count > 0
                ? `Utilisée par ${existante.comptes_count} compte(s) : un changement de nom leur est reporté.`
                : 'Le nom et le code banque doivent être uniques.'}
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3.5 sm:grid-cols-2">
            <div className="space-y-1.5 sm:col-span-2">
              <Label htmlFor="bank-name">Nom de la banque *</Label>
              <Input id="bank-name" value={form.data.name} maxLength={255} placeholder="Ex. Attijariwafa Bank" required
                onChange={(e) => form.setData('name', e.target.value)} aria-invalid={Boolean(form.errors.name)} />
              {erreur(form.errors.name)}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="bank-code">Code banque (RIB)</Label>
              <Input id="bank-code" value={form.data.code_etablissement} maxLength={3} inputMode="numeric" placeholder="Ex. 007"
                className="font-mono tracking-[0.2em]" onChange={(e) => form.setData('code_etablissement', e.target.value.replace(/\D/g, ''))} />
              <p className="text-[10px] text-muted-foreground">Les 3 premiers chiffres du RIB.</p>
              {erreur(form.errors.code_etablissement)}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="bank-swift">Code SWIFT</Label>
              <Input id="bank-swift" value={form.data.swift_code} maxLength={11} placeholder="Ex. BCMAMAMC"
                className="font-mono uppercase" onChange={(e) => form.setData('swift_code', e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))} />
              {erreur(form.errors.swift_code)}
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Statut *</Label>
              <div role="radiogroup" aria-label="Statut" className="grid grid-cols-3 gap-2">
                {(Object.keys(STATUTS) as Statut[]).map((s) => (
                  <button key={s} type="button" role="radio" aria-checked={form.data.status === s} onClick={() => form.setData('status', s)}
                    className={`rounded-md border px-3 py-1.5 text-xs font-semibold transition-colors ${form.data.status === s
                      ? 'border-[#004c68] bg-[#004c68]/5 ring-1 ring-[#004c68] dark:border-[#ff8806] dark:ring-[#ff8806]' : 'border-border hover:bg-muted/50'}`}>
                    {STATUTS[s].label}
                  </button>
                ))}
              </div>
              <p className="text-[10px] text-muted-foreground">Seules les banques actives sont proposées pour un nouveau RIB.</p>
              {erreur(form.errors.status)}
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={form.processing}>Annuler</Button>
            <Button type="submit" size="sm" className="bg-[#004c68] text-white hover:bg-[#00384d]" disabled={form.processing}>
              {form.processing && <Loader2 className="size-3.5 animate-spin" />} {existante ? 'Enregistrer' : 'Ajouter la banque'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
