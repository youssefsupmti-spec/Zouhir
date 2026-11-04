import { useState, type FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { CircleDot, History, Layers, Loader2, Pencil, Tags, TriangleAlert } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface StatutLigne {
  id: number;
  libelle: string;
  couleur: string | null;
  groupe: string;
  origine: string;
  actif: boolean;
  kpi_legacy: string | null;
  acteurs_legacy: string | null;
  colis: number;
  historique: number;
}

interface Props {
  statuts: StatutLigne[];
  groupes: Record<string, string>;
  origines: Record<string, string>;
  stats: { total: number; actifs: number; hors_referentiel: number; colis_sans_statut: number; par_groupe: Record<string, number> };
  filters: { origine: string; groupe: string; search: string };
  can: { manage: boolean };
}

const TON_GROUPE: Record<string, string> = {
  nouveau: 'bg-muted text-muted-foreground',
  en_cours: 'bg-sky-600/10 text-sky-700 dark:text-sky-300',
  livre: 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
  retour: 'bg-[#ff8806]/10 text-[#c26200] dark:text-[#ff8806]',
  annule: 'bg-destructive/10 text-destructive',
};

const BARRE: Record<string, string> = { nouveau: 'bg-muted-foreground/40', en_cours: 'bg-sky-600', livre: 'bg-emerald-600', retour: 'bg-[#ff8806]', annule: 'bg-destructive' };

/** Les couleurs legacy sont parfois des mots (« Gris foncé ») : pastille seulement si c'est une couleur CSS. */
const estCouleur = (c: string | null) => !!c && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(c);

export default function StatutsIndex({ statuts, groupes, origines, stats, filters, can }: Props) {
  const [cible, setCible] = useState<StatutLigne | null>(null);
  const form = useForm({ groupe: 'en_cours', couleur: '', actif: true });
  const params = { ...filters };
  const naviguer = (changes: Record<string, unknown>) => router.get('/statuts', { ...params, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
  const totalColis = Object.values(stats.par_groupe).reduce((a, b) => a + Number(b), 0);

  const ouvrir = (s: StatutLigne) => {
    form.setData({ groupe: s.groupe, couleur: s.couleur ?? '', actif: s.actif });
    form.clearErrors();
    setCible(s);
  };
  const enregistrer = (e: FormEvent) => {
    e.preventDefault();
    if (cible) form.put(`/statuts/${cible.id}`, { preserveScroll: true, onSuccess: () => setCible(null) });
  };

  return (
    <AuthenticatedLayout title="Statuts colis">
      <Head title="Statuts colis - OUAISS EXPRESS" />
      <div className="space-y-6">
        <PageHeader title="Statuts colis" description="Référentiel des statuts et leur groupe (livré, retour, en cours…). Les statuts de l’ancien site gardent leur numéro." icon={Tags} />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Statuts" value={stats.total} description={`${stats.actifs} actifs`} icon={Tags} />
          <StatsCard title="Hors référentiel" value={stats.hors_referentiel} description="Libellés saisis à la main dans l’ancien site" icon={TriangleAlert} iconColor="text-[#ff8806]" />
          <StatsCard title="Colis classés" value={totalColis.toLocaleString('fr-FR')} description={`${stats.colis_sans_statut} sans statut rattaché`} icon={Layers} />
          <StatsCard title="Livrés" value={(stats.par_groupe.livre ?? 0).toLocaleString('fr-FR')} description={totalColis ? `${Math.round(((stats.par_groupe.livre ?? 0) / totalColis) * 1000) / 10} % des colis` : '—'} icon={CircleDot} iconColor="text-emerald-600" />
        </div>

        {totalColis > 0 && (
          <div className="overflow-hidden rounded-lg border border-border bg-card p-4">
            <p className="mb-2 text-xs font-semibold">Répartition des colis par groupe</p>
            <div className="flex h-3 overflow-hidden rounded-full bg-muted">
              {Object.entries(groupes).map(([g]) => {
                const n = Number(stats.par_groupe[g] ?? 0);
                return n > 0 ? <span key={g} title={`${groupes[g]} : ${n}`} className={BARRE[g]} style={{ width: `${(n / totalColis) * 100}%` }} /> : null;
              })}
            </div>
            <div className="mt-2 flex flex-wrap gap-3 text-[11px] text-muted-foreground">
              {Object.entries(groupes).map(([g, l]) => <span key={g}><b className="text-foreground">{Number(stats.par_groupe[g] ?? 0).toLocaleString('fr-FR')}</b> {l.toLowerCase()}</span>)}
            </div>
          </div>
        )}

        <PageToolbar searchPlaceholder="Libellé du statut…" initialSearch={filters.search} currentParams={params}>
          <FilterDropdown label="Groupe" value={filters.groupe || 'all'} options={[{ value: 'all', label: 'Tous' }, ...Object.entries(groupes).map(([value, label]) => ({ value, label }))]} onChange={(v) => naviguer({ groupe: v === 'all' ? undefined : v })} />
          <FilterDropdown label="Origine" value={filters.origine || 'all'} options={[{ value: 'all', label: 'Toutes' }, ...Object.entries(origines).map(([value, label]) => ({ value, label }))]} onChange={(v) => naviguer({ origine: v === 'all' ? undefined : v })} />
        </PageToolbar>

        <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
          <Table className="min-w-[820px] text-xs">
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="w-14 pl-4">N°</TableHead>
                <TableHead>Statut</TableHead>
                <TableHead className="w-32">Groupe</TableHead>
                <TableHead>Origine</TableHead>
                <TableHead className="w-24 text-right">Colis</TableHead>
                <TableHead className="w-28 text-right">Historique</TableHead>
                <TableHead className="w-16 pr-4 text-right" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {statuts.map((s) => (
                <TableRow key={s.id} className={s.actif ? undefined : 'opacity-70'}>
                  <TableCell className="pl-4 tabular-nums text-muted-foreground">{s.id}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <span className="size-2.5 shrink-0 rounded-full border border-black/10" style={estCouleur(s.couleur) ? { background: s.couleur! } : undefined} />
                      <span className="font-semibold">{s.libelle}</span>
                      {!s.actif && <span className="rounded bg-muted px-1.5 py-px text-[10px] text-muted-foreground">inactif</span>}
                    </div>
                    {(s.kpi_legacy || s.acteurs_legacy) && <div className="mt-0.5 pl-4.5 text-[10px] text-muted-foreground">{[s.kpi_legacy, s.acteurs_legacy].filter(Boolean).join(' · ')}</div>}
                  </TableCell>
                  <TableCell><span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${TON_GROUPE[s.groupe] ?? ''}`}>{groupes[s.groupe] ?? s.groupe}</span></TableCell>
                  <TableCell className="text-muted-foreground">{origines[s.origine] ?? s.origine}</TableCell>
                  <TableCell className="text-right tabular-nums">{s.colis.toLocaleString('fr-FR')}</TableCell>
                  <TableCell className="text-right tabular-nums"><span className="inline-flex items-center gap-1"><History className="size-3 opacity-50" />{s.historique.toLocaleString('fr-FR')}</span></TableCell>
                  <TableCell className="pr-4 text-right">
                    {can.manage && (
                      <Button size="icon" variant="ghost" className="size-7" aria-label={`Modifier ${s.libelle}`} onClick={() => ouvrir(s)}>
                        <Pencil className="size-3.5 text-[#ff8806]" />
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
              {statuts.length === 0 && <TableRow><TableCell colSpan={7} className="py-12 text-center text-muted-foreground">Aucun statut (lancer la reprise des statuts).</TableCell></TableRow>}
            </TableBody>
          </Table>
        </div>
      </div>

      <Dialog open={cible !== null} onOpenChange={(o) => !o && !form.processing && setCible(null)}>
        <DialogContent className="max-w-md">
          <form onSubmit={enregistrer} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Statut « {cible?.libelle} »</DialogTitle>
              <DialogDescription className="text-xs">Changer le groupe reclasse aussi les {cible?.colis.toLocaleString('fr-FR')} colis qui portent ce statut.</DialogDescription>
            </DialogHeader>
            <div className="space-y-1.5">
              <Label>Groupe</Label>
              <div className="grid grid-cols-3 gap-2">
                {Object.entries(groupes).map(([g, l]) => (
                  <button key={g} type="button" onClick={() => form.setData('groupe', g)}
                    className={`rounded-md border px-2 py-1.5 text-xs font-medium ${form.data.groupe === g ? 'border-[#004c68] bg-[#004c68]/10 text-[#004c68] dark:border-[#ff8806] dark:text-[#ff8806]' : 'border-border text-muted-foreground'}`}>
                    {l}
                  </button>
                ))}
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="couleur">Couleur</Label>
              <Input id="couleur" value={form.data.couleur} onChange={(e) => form.setData('couleur', e.target.value)} placeholder="#008000" />
            </div>
            <label className="flex items-center gap-2 text-xs">
              <input type="checkbox" checked={form.data.actif} onChange={(e) => form.setData('actif', e.target.checked)} className="size-4" /> Statut actif (proposé lors d’un changement d’état)
            </label>
            <DialogFooter>
              <Button type="button" variant="outline" size="sm" onClick={() => setCible(null)}>Annuler</Button>
              <Button type="submit" size="sm" disabled={form.processing} className="bg-[#004c68] text-white hover:bg-[#00384d]">
                {form.processing && <Loader2 className="size-3.5 animate-spin" />} Enregistrer
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
