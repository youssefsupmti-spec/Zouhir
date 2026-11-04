import { Fragment, useState, type FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import {
  AlertOctagon, CheckCircle2, ChevronDown, ChevronRight, DatabaseZap, Download, EyeOff, Gavel, Info, Loader2, RotateCcw, TriangleAlert,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ChargementTableau, useChargementPage } from '@/components/common/ChargementTableau';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ActionGroupee, BarreSelection, CaseACocher, useSelection } from '@/components/common/SelectionGroupee';

type Gravite = 'bloquant' | 'attention' | 'decision' | 'info';
type Statut = 'ouverte' | 'resolue' | 'ignoree';

interface Anomalie {
  id: number;
  domaine: string;
  code: string;
  gravite: Gravite;
  libelle: string;
  legacy_table: string | null;
  legacy_id: string | null;
  valeur_legacy: string | null;
  cible_table: string | null;
  cible_id: number | null;
  nb_elements: number | null;
  details: Record<string, unknown>;
  statut: Statut;
  decision: string | null;
  traite_par: string | null;
  traite_le: string | null;
  vue_le: string | null;
}

interface Props {
  anomalies: LaravelPaginationMeta & { data: Anomalie[] };
  domaines: Record<string, string>;
  gravites: Record<Gravite, string>;
  statuts: Record<Statut, string>;
  compteurs: Record<string, number>;
  codes: Record<string, number>;
  stats: { ouvertes: number; bloquantes: number; decisions: number; traitees: number; dernier_passage: string | null };
  filters: { domaine: string; statut: Statut; gravite: string; code: string; search: string; per_page: number };
  can: { manage: boolean };
}

const GRAVITE: Record<Gravite, { icone: typeof Info; classe: string; barre: string }> = {
  bloquant: { icone: AlertOctagon, classe: 'bg-destructive/10 text-destructive border-destructive/25', barre: 'bg-destructive' },
  decision: { icone: Gavel, classe: 'bg-[#004c68]/10 text-[#004c68] border-[#004c68]/25 dark:text-cyan-300', barre: 'bg-[#004c68] dark:bg-cyan-400' },
  attention: { icone: TriangleAlert, classe: 'bg-[#ff8806]/10 text-[#c26200] border-[#ff8806]/30 dark:text-[#ff8806]', barre: 'bg-[#ff8806]' },
  info: { icone: Info, classe: 'bg-muted text-muted-foreground border-border', barre: 'bg-muted-foreground/40' },
};

const LIBELLE_TABLE: Record<string, string> = {
  commands: 'colis', bls: 'bon', commandshistory: 'historique', trackingstates: 'statut', users: 'compte',
  villes: 'ville', zones: 'zone', businesses: 'enseigne', comptes_bancaires: 'RIB',
};

function dateCourte(d: string | null) {
  if (!d) return '—';
  const x = new Date(d.replace(' ', 'T'));
  return x.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function Details({ details }: { details: Record<string, unknown> }) {
  const entrees = Object.entries(details).filter(([k]) => !k.endsWith('_total'));
  if (entrees.length === 0) return null;
  return (
    <dl className="mt-2 grid gap-1 rounded-md border border-dashed border-border bg-muted/30 p-2.5 text-[11px]">
      {entrees.map(([k, v]) => {
        const total = details[`${k}_total`] as number | undefined;
        const texte = Array.isArray(v) ? v.join(', ') + (total ? ` … (${total} au total)` : '') : typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v);
        return (
          <div key={k} className="grid grid-cols-[130px_1fr] gap-2">
            <dt className="font-medium text-muted-foreground">{k.replace(/_/g, ' ')}</dt>
            <dd className="break-all font-mono">{texte}</dd>
          </div>
        );
      })}
    </dl>
  );
}

export default function RepriseIndex({ anomalies, domaines, gravites, statuts, compteurs, codes, stats, filters, can }: Props) {
  const chargement = useChargementPage();
  const [ouvertes, setOuvertes] = useState<Record<number, boolean>>({});
  const [cible, setCible] = useState<Anomalie | { ids: number[]; libelle: string; statut: Statut } | null>(null);
  const selection = useSelection(anomalies.data.map((a) => a.id));
  const params = { ...filters };
  const naviguer = (changes: Record<string, unknown>) =>
    router.get('/reprise', { ...params, ...changes, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });

  const form = useForm<{ statut: Statut; decision: string }>({ statut: 'resolue', decision: '' });
  const ouvrirSelection = () => {
    form.setData({ statut: filters.statut === 'ouverte' ? 'resolue' : 'ouverte', decision: '' });
    form.clearErrors();
    setCible({ ids: selection.ids, libelle: `${selection.taille} anomalie(s) sélectionnée(s)`, statut: filters.statut });
  };
  const ouvrir = (a: Anomalie) => {
    form.setData({ statut: a.statut === 'ouverte' ? 'resolue' : 'ouverte', decision: a.decision ?? '' });
    form.clearErrors();
    setCible(a);
  };
  const enregistrer = (e: FormEvent) => {
    e.preventDefault();
    if (!cible) return;
    if ('ids' in cible) {
      form.transform((d) => ({ ...d, ids: cible.ids }));
      form.post('/reprise/groupe', { preserveScroll: true, onSuccess: () => { setCible(null); selection.vider(); } });
    } else {
      form.transform((d) => d);
      form.put(`/reprise/${cible.id}`, { preserveScroll: true, onSuccess: () => setCible(null) });
    }
  };

  return (
    <AuthenticatedLayout title="Reprise des données">
      <Head title="Reprise des données - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Reprise des données"
          description="Tout ce que la reprise de l’ancienne base n’a pas pu rattacher exactement. Rien n’a été deviné : chaque écart attend une décision."
          icon={DatabaseZap}
          actions={(
            <Button render={<a href={`/reprise/export?domaine=${filters.domaine}`} />} variant="outline" size="sm" className="h-8 text-xs">
              <Download className="size-3.5" /> Exporter (CSV)
            </Button>
          )}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Anomalies ouvertes" value={stats.ouvertes} description={stats.dernier_passage ? `Dernier passage : ${dateCourte(stats.dernier_passage)}` : 'Aucune reprise exécutée'} icon={DatabaseZap} />
          <StatsCard title="Bloquantes" value={stats.bloquantes} description="Donnée absente ou introuvable" icon={AlertOctagon} iconColor="text-destructive" />
          <StatsCard title="Décisions à prendre" value={stats.decisions} description="Règle appliquée à confirmer" icon={Gavel} />
          <StatsCard title="Traitées" value={stats.traitees} description="Résolues ou ignorées, décision gardée" icon={CheckCircle2} iconColor="text-emerald-600 dark:text-emerald-400" />
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {(Object.keys(statuts) as Statut[]).map((s) => (
            <button
              key={s}
              type="button"
              onClick={() => naviguer({ statut: s })}
              className={`rounded-full border px-3 py-1 text-xs font-medium transition ${filters.statut === s ? 'border-[#004c68] bg-[#004c68] text-white dark:border-[#ff8806] dark:bg-[#ff8806] dark:text-[#05161f]' : 'border-border text-muted-foreground hover:text-foreground'}`}
            >
              {statuts[s]}
            </button>
          ))}
        </div>

        <TarifsTabs
          label="Domaines"
          value={filters.domaine}
          onValueChange={(v) => naviguer({ domaine: v, code: undefined })}
          onglets={[
            { value: 'tous', label: 'Tout', count: compteurs.tous ?? 0 },
            ...Object.entries(domaines).map(([value, label]) => ({ value, label, count: compteurs[value] ?? 0, tone: (compteurs[value] ?? 0) === 0 ? ('muted' as const) : ('default' as const) })),
          ]}
        />

        <PageToolbar searchPlaceholder="Libellé, valeur d’origine ou id legacy…" initialSearch={filters.search} currentParams={params}>
          <FilterDropdown
            label="Gravité"
            value={filters.gravite || 'all'}
            options={[{ value: 'all', label: 'Toutes' }, ...Object.entries(gravites).map(([value, label]) => ({ value, label }))]}
            onChange={(v) => naviguer({ gravite: v === 'all' ? undefined : v })}
          />
          <FilterDropdown
            label="Type"
            value={filters.code || 'all'}
            options={[{ value: 'all', label: 'Tous les types' }, ...Object.entries(codes).map(([value, n]) => ({ value, label: `${value.replace(/_/g, ' ')} (${n})` }))]}
            onChange={(v) => naviguer({ code: v === 'all' ? undefined : v })}
          />
        </PageToolbar>

        {can.manage && (
          <BarreSelection taille={selection.taille} onVider={selection.vider}>
            <ActionGroupee icone={<Gavel className="size-3.5" />} onClick={ouvrirSelection}>{filters.statut === 'ouverte' ? 'Décider pour la sélection' : 'Rouvrir / modifier la décision'}</ActionGroupee>
          </BarreSelection>
        )}

        <div className="relative overflow-x-auto rounded-md border border-border bg-card">
          <ChargementTableau actif={chargement} />
          <Table className="min-w-[1100px] text-xs">
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                {can.manage && <TableHead className="w-10 pl-4"><CaseACocher label="Tout sélectionner" coche={selection.tous} partiel={selection.partiel} onChange={selection.basculerTous} /></TableHead>}
                <TableHead className={`w-32 ${can.manage ? '' : 'pl-4'}`}>Gravité</TableHead>
                <TableHead className="w-36">Domaine / type</TableHead>
                <TableHead>Anomalie</TableHead>
                <TableHead className="w-48">Ancienne base</TableHead>
                <TableHead className="w-20 text-right">Éléments</TableHead>
                <TableHead className="w-56">Décision</TableHead>
                <TableHead className="w-24 pr-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {anomalies.data.map((a) => {
                const g = GRAVITE[a.gravite] ?? GRAVITE.info;
                const Icone = g.icone;
                const deplie = ouvertes[a.id] === true;
                const aDetails = Object.keys(a.details).length > 0;
                return (
                  <Fragment key={a.id}>
                    <TableRow data-state={selection.estChoisi(a.id) ? 'selected' : undefined} className="align-top">
                      {can.manage && <TableCell className="pl-4 pt-3"><CaseACocher label={`Sélectionner l’anomalie ${a.id}`} coche={selection.estChoisi(a.id)} onChange={() => selection.basculer(a.id)} /></TableCell>}
                      <TableCell className={can.manage ? '' : 'pl-4'}>
                        <span className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold ${g.classe}`}><Icone className="size-3" /> {gravites[a.gravite] ?? a.gravite}</span>
                      </TableCell>
                      <TableCell>
                        <div className="font-medium">{domaines[a.domaine] ?? a.domaine}</div>
                        <code className="text-[10px] text-muted-foreground">{a.code}</code>
                      </TableCell>
                      <TableCell className="max-w-[420px] whitespace-normal">
                        <p className="leading-snug">{a.libelle}</p>
                        {a.valeur_legacy !== null && a.valeur_legacy !== '' && <p className="mt-0.5 truncate text-[10px] text-muted-foreground" title={a.valeur_legacy}>Valeur : « {a.valeur_legacy} »</p>}
                      </TableCell>
                      <TableCell className="text-[11px] text-muted-foreground">
                        {a.legacy_table ? `${LIBELLE_TABLE[a.legacy_table] ?? a.legacy_table}${a.legacy_id ? ` #${a.legacy_id}` : ''}` : '—'}
                        {a.cible_table && a.cible_id && <div className="text-[10px]">→ {LIBELLE_TABLE[a.cible_table] ?? a.cible_table} #{a.cible_id}</div>}
                      </TableCell>
                      <TableCell className="text-right tabular-nums">{a.nb_elements ?? '—'}</TableCell>
                      <TableCell className="whitespace-normal text-[11px]">
                        {a.decision
                          ? <><p className="text-emerald-800 dark:text-emerald-300">{a.decision}</p>{a.traite_par && <p className="text-[10px] text-muted-foreground">{a.traite_par} · {dateCourte(a.traite_le)}</p>}</>
                          : <span className="text-muted-foreground">—</span>}
                      </TableCell>
                      <TableCell className="pr-4 text-right">
                        <span className="inline-flex gap-0.5">
                          {aDetails && (
                            <Button size="icon" variant="ghost" className="size-7" title="Détails" aria-label="Détails" onClick={() => setOuvertes((o) => ({ ...o, [a.id]: !deplie }))}>
                              {deplie ? <ChevronDown className="size-3.5" /> : <ChevronRight className="size-3.5" />}
                            </Button>
                          )}
                          {can.manage && (
                            <Button size="icon" variant="ghost" className="size-7" title={a.statut === 'ouverte' ? 'Décider' : 'Rouvrir'} aria-label="Décider" onClick={() => ouvrir(a)}>
                              {a.statut === 'ouverte' ? <Gavel className="size-3.5 text-[#004c68] dark:text-cyan-300" /> : <RotateCcw className="size-3.5" />}
                            </Button>
                          )}
                        </span>
                      </TableCell>
                    </TableRow>
                    {deplie && (
                      <TableRow className="hover:bg-transparent">
                        <TableCell colSpan={can.manage ? 8 : 7} className="bg-muted/20 px-6 pb-3 pt-0"><Details details={a.details} /></TableCell>
                      </TableRow>
                    )}
                  </Fragment>
                );
              })}
              {anomalies.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={can.manage ? 8 : 7} className="py-14 text-center text-muted-foreground">
                    <CheckCircle2 className="mx-auto mb-2 size-6 text-emerald-600 opacity-70" />
                    {stats.dernier_passage ? 'Aucune anomalie pour ces filtres.' : 'La reprise n’a pas encore été exécutée (php artisan reprise:legacy).'}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        {anomalies.total > 0 && <DataTablePagination meta={anomalies} currentParams={params} pageSizeOptions={[25, 50, 100]} />}
      </div>

      <Dialog open={cible !== null} onOpenChange={(o) => !o && !form.processing && setCible(null)}>
        <DialogContent className="max-w-lg">
          <form onSubmit={enregistrer} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Décision sur l’anomalie</DialogTitle>
              <DialogDescription className="text-xs">{cible?.libelle}</DialogDescription>
            </DialogHeader>
            <div className="grid grid-cols-3 gap-2">
              {([['resolue', 'Résolue', CheckCircle2], ['ignoree', 'Ignorée', EyeOff], ['ouverte', 'Ouverte', RotateCcw]] as const).map(([v, l, I]) => (
                <button
                  key={v}
                  type="button"
                  onClick={() => form.setData('statut', v)}
                  className={`flex items-center justify-center gap-1.5 rounded-md border px-2 py-2 text-xs font-medium ${form.data.statut === v ? 'border-[#004c68] bg-[#004c68]/10 text-[#004c68] dark:border-[#ff8806] dark:text-[#ff8806]' : 'border-border text-muted-foreground'}`}
                >
                  <I className="size-3.5" /> {l}
                </button>
              ))}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="decision">Décision prise {form.data.statut !== 'ouverte' && '*'}</Label>
              <Textarea id="decision" rows={4} value={form.data.decision} onChange={(e) => form.setData('decision', e.target.value)}
                placeholder="Ex. : ville corrigée en « Casablanca » sur les 12 colis ; compte confirmé comme partenaire…" />
              {form.errors.decision && <p className="text-[11px] font-medium text-destructive">{form.errors.decision}</p>}
              <p className="text-[11px] text-muted-foreground">La décision reste attachée à l’anomalie, même si la reprise est rejouée.</p>
            </div>
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
