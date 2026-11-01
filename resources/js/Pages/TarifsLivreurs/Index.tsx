import { Fragment, useEffect, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Bike, CheckCircle2, ChevronRightIcon, Coins, Loader2, MapPin, MapPinned, Plus, RotateCcw, Save, Trash2, Users, Wallet } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { StatsCard } from '@/components/common/StatsCard';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { CorbeilleTarifs, type LigneCorbeille } from '@/components/tarifs/CorbeilleTarifs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { erreurMontant, jsonFetch, normaliserMontant } from '@/Pages/TarifsClients/types';
import { AjouterTarifLivreurDialog } from './AjouterTarifLivreurDialog';

/*
 * Écran « Tarifs livreurs » — structure reprise de WinningExpress (« Commissions internes ») :
 * cartes de compteurs servant d'onglets, puis Livreur → Zone → Ville. Aucune commission standard :
 * la commission est propre à un livreur et une ville ; sinon elle est « manquante ».
 */

/** Deux états, comme le legacy : active (trash = 1) ou corbeille (trash = 0). */
type Statut = 'actives' | 'corbeille';

interface LivreurResume {
  id: number;
  nom: string;
  telephone: string | null;
  email: string | null;
  archive: boolean;
  nb_villes: number;
  nb_zones: number;
  min: string | null;
  max: string | null;
}

interface Commission {
  id: number;
  ville_id: number;
  ville_nom: string;
  zone_nom: string;
  livraison: string;
  refus: string;
  zero: boolean;
}

interface ZoneGrille {
  zone_id: number | null;
  zone_nom: string;
  zone_code: string | null;
  lignes: Commission[];
}

interface Capacites {
  create: boolean;
  update: boolean;
  delete: boolean;
}

interface Props {
  stats: { commissions: number; livreurs: number; villes: number; moyenne: string | null; zero: number; corbeille: number };
  zones: { id: number; name: string; code: string | null }[];
  livreurs: (LaravelPaginationMeta & { data: LivreurResume[] }) | null;
  corbeille: LigneCorbeille[] | null;
  filters: { statut: Statut; search: string; zone_id: string; per_page: number };
  can: Capacites;
}

function BadgeCommission({ c }: { c: Commission }) {
  return c.zero
    ? <Badge variant="outline" className="rounded-md border-sky-500/30 text-[10px] text-sky-700 dark:text-sky-300">Commission à zéro</Badge>
    : <Badge variant="outline" className="rounded-md border-emerald-500/30 text-[10px] text-emerald-700 dark:text-emerald-400">Active</Badge>;
}

/** Zone → Ville d'UN livreur : édition inline, sélection, application en lot, mise à la corbeille. */
function GrilleLivreur({ livreur, zones, can, onChange }: { livreur: LivreurResume; zones: ZoneGrille[]; can: Capacites; onChange: () => void }) {
  const toutes = useMemo(() => zones.flatMap((z) => z.lignes), [zones]);
  const [brouillons, setBrouillons] = useState<Record<number, { livraison: string; refus: string }>>({});
  const [selection, setSelection] = useState<Set<number>>(new Set());
  const [lot, setLot] = useState({ livraison: '', refus: '0' });
  const [suppression, setSuppression] = useState(false);
  const [busy, setBusy] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  useEffect(() => {
    setBrouillons({});
    setSelection(new Set());
  }, [zones]);

  const valeur = (c: Commission, k: 'livraison' | 'refus') => brouillons[c.id]?.[k] ?? c[k];
  const modifiee = (c: Commission) => brouillons[c.id] !== undefined
    && (normaliserMontant(valeur(c, 'livraison')) !== c.livraison || normaliserMontant(valeur(c, 'refus')) !== c.refus);
  const modifiees = toutes.filter(modifiee);
  const invalides = modifiees.filter((c) => erreurMontant(valeur(c, 'livraison')) || erreurMontant(valeur(c, 'refus'))).length;
  const selectionnees = toutes.filter((c) => selection.has(c.id));

  const options = (apres?: () => void) => ({
    preserveScroll: true,
    preserveState: true,
    onStart: () => setBusy(true),
    onFinish: () => setBusy(false),
    onError: (e: Record<string, string>) => setErreur(Object.values(e)[0] ?? 'Enregistrement refusé.'),
    onSuccess: () => { setErreur(null); apres?.(); onChange(); },
  });
  const envoyer = (lignes: { id: number; livraison: string; refus: string }[], apres?: () => void) =>
    router.put('/tarifs-livreurs', { livreur_id: livreur.id, lignes }, options(apres));
  const basculer = (ids: number[], coche: boolean) => setSelection((prev) => {
    const n = new Set(prev);
    ids.forEach((id) => (coche ? n.add(id) : n.delete(id)));
    return n;
  });

  return (
    <div>
      {erreur && <div role="alert" className="border-b border-destructive/30 bg-destructive/5 px-4 py-2 text-xs text-destructive">{erreur}</div>}

      {(can.update || can.delete) && toutes.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/30 px-4 py-2 text-[11px]">
          <span className="text-muted-foreground">{selectionnees.length > 0 ? `${selectionnees.length} commission(s) sélectionnée(s)` : 'Cochez des villes pour agir en lot'}</span>
          {can.update && selectionnees.length > 0 && (
            <>
              <Input aria-label="Commission livraison du lot" inputMode="decimal" placeholder="Livré" value={lot.livraison}
                onChange={(e) => setLot((l) => ({ ...l, livraison: e.target.value }))} className="h-7 w-20 font-mono text-xs" />
              <Input aria-label="Commission refus du lot" inputMode="decimal" placeholder="Refusé" value={lot.refus}
                onChange={(e) => setLot((l) => ({ ...l, refus: e.target.value }))} className="h-7 w-20 font-mono text-xs" />
              <Button size="sm" variant="outline" className="h-7 rounded-md text-[11px]" disabled={busy || Boolean(erreurMontant(lot.livraison) || erreurMontant(lot.refus))}
                onClick={() => envoyer(selectionnees.map((c) => ({ id: c.id, livraison: normaliserMontant(lot.livraison), refus: normaliserMontant(lot.refus) })), () => setLot({ livraison: '', refus: '0' }))}>
                Appliquer à la sélection
              </Button>
            </>
          )}
          {can.delete && (
            <Button size="sm" variant="outline" className="ml-auto h-7 rounded-md text-[11px] text-destructive hover:text-destructive" disabled={selectionnees.length === 0} onClick={() => setSuppression(true)}>
              <Trash2 className="size-3.5" /> Mettre à la corbeille
            </Button>
          )}
        </div>
      )}

      {can.update && modifiees.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 border-b border-[#ff8806]/30 bg-[#ff8806]/10 px-4 py-2 text-[11px]">
          <span className="font-semibold text-[#b45f00] dark:text-[#ffb366]">{modifiees.length} commission(s) modifiée(s)</span>
          {invalides > 0 && <span className="text-destructive">· {invalides} invalide(s)</span>}
          <div className="ml-auto flex gap-1.5">
            <Button size="sm" variant="ghost" className="h-7 rounded-md text-[11px]" disabled={busy} onClick={() => setBrouillons({})}><RotateCcw className="size-3.5" /> Annuler</Button>
            <Button size="sm" className="h-7 rounded-md bg-[#004c68] text-[11px] text-white hover:bg-[#00384d]" disabled={busy || invalides > 0}
              onClick={() => envoyer(modifiees.map((c) => ({ id: c.id, livraison: normaliserMontant(valeur(c, 'livraison')), refus: normaliserMontant(valeur(c, 'refus')) })))}>
              {busy ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />} Enregistrer ({modifiees.length})
            </Button>
          </div>
        </div>
      )}

      <div className="overflow-x-auto">
        <Table className="min-w-[640px] table-fixed text-xs">
          <colgroup>
            <col className="w-10" />
            <col />
            <col className="w-36" />
            <col className="w-32" />
            <col className="w-36" />
          </colgroup>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="pl-4"><span className="sr-only">Sélection</span></TableHead>
              <TableHead>Ville</TableHead>
              <TableHead className="text-right">Frais livré (MAD)</TableHead>
              <TableHead className="text-right">Frais refusé (MAD)</TableHead>
              <TableHead className="pr-4">Statut</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {zones.map((z) => {
              const ids = z.lignes.map((l) => l.id);
              const cochees = ids.filter((id) => selection.has(id)).length;
              return (
                <Fragment key={z.zone_id ?? 'sans'}>
                  <TableRow className="bg-muted/40 hover:bg-muted/40">
                    <TableCell className="pl-4">
                      <Checkbox aria-label={`Sélectionner toute la zone ${z.zone_nom} (${livreur.nom})`} checked={cochees === ids.length && ids.length > 0}
                        indeterminate={cochees > 0 && cochees < ids.length} onCheckedChange={(c) => basculer(ids, Boolean(c))} />
                    </TableCell>
                    <TableCell colSpan={4}>
                      <span className="flex items-center gap-2 font-semibold">
                        <MapPinned className="size-3.5 text-[#004c68] dark:text-[#ff8806]" /> {z.zone_nom}
                        {z.zone_code && <code className="rounded bg-muted px-1 font-mono text-[10px]">{z.zone_code}</code>}
                        <span className="font-normal text-muted-foreground">· {z.lignes.length} ville(s)</span>
                      </span>
                    </TableCell>
                  </TableRow>
                  {z.lignes.map((c) => (
                    <TableRow key={c.id} data-state={selection.has(c.id) || modifiee(c) ? 'selected' : undefined}>
                      <TableCell className="pl-4">
                        <Checkbox aria-label={`Sélectionner ${c.ville_nom} (${livreur.nom})`} checked={selection.has(c.id)} onCheckedChange={(v) => basculer([c.id], Boolean(v))} />
                      </TableCell>
                      <TableCell className="truncate font-medium" title={c.ville_nom}>{c.ville_nom}</TableCell>
                      {(['livraison', 'refus'] as const).map((k) => (
                        <TableCell key={k} className="text-right">
                          {can.update ? (
                            <div>
                              <Input inputMode="decimal" aria-label={`Commission ${k} ${c.ville_nom} (${livreur.nom})`} value={valeur(c, k)}
                                aria-invalid={Boolean(erreurMontant(valeur(c, k)))}
                                onChange={(e) => setBrouillons((b) => ({ ...b, [c.id]: { livraison: valeur(c, 'livraison'), refus: valeur(c, 'refus'), [k]: e.target.value } }))}
                                className={`h-7 rounded-md text-right font-mono text-xs ${k === 'livraison' ? 'font-semibold text-[#004c68] dark:text-[#ff8806]' : ''}`} />
                              {erreurMontant(valeur(c, k)) && <p className="mt-0.5 text-[10px] text-destructive">{erreurMontant(valeur(c, k))}</p>}
                            </div>
                          ) : <span className="font-mono">{c[k]} DH</span>}
                        </TableCell>
                      ))}
                      <TableCell className="pr-4">{modifiee(c)
                        ? <Badge variant="outline" className="rounded-md border-[#ff8806]/40 text-[10px] text-[#b45f00] dark:text-[#ffb366]">Modifiée</Badge>
                        : <BadgeCommission c={c} />}</TableCell>
                    </TableRow>
                  ))}
                </Fragment>
              );
            })}
            {zones.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-6 text-center text-muted-foreground">
                  Aucune commission : les colis de ce livreur auront une commission « manquante » tant qu'aucune ville ne lui est affectée.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <ConfirmDeleteDialog
        open={suppression}
        onOpenChange={(o) => !o && !busy && setSuppression(false)}
        title={`Mettre ${selectionnees.length} commission(s) à la corbeille`}
        description={`${livreur.nom} : ${selectionnees.map((c) => c.ville_nom).slice(0, 8).join(', ')}${selectionnees.length > 8 ? '…' : ''}. Les prochains colis de ces villes n'auront plus de commission pour ce livreur. Les colis existants ne sont pas modifiés ; restauration possible depuis la corbeille.`}
        isLoading={busy}
        onConfirm={() => router.delete('/tarifs-livreurs', { data: { livreur_id: livreur.id, ids: selectionnees.map((c) => c.id) }, ...options(() => setSuppression(false)) })}
      />
    </div>
  );
}

/** Carte d'un livreur (accordéon) : la grille n'est chargée qu'à l'ouverture. */
function CarteLivreur({ livreur, zoneId, can, onAjouter }: { livreur: LivreurResume; zoneId: string; can: Capacites; onAjouter: (l: LivreurResume) => void }) {
  const [open, setOpen] = useState(false);
  const [zones, setZones] = useState<ZoneGrille[] | null>(null);
  const [version, setVersion] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    let annule = false;
    setLoading(true);
    setError(null);
    jsonFetch<{ zones: ZoneGrille[] }>(`/tarifs-livreurs/${livreur.id}/grille${zoneId !== 'all' ? `?zone_id=${zoneId}` : ''}`)
      .then((d) => { if (!annule) setZones(d.zones); })
      .catch((e: Error) => { if (!annule) setError(e.message); })
      .finally(() => { if (!annule) setLoading(false); });
    return () => { annule = true; };
  }, [open, version, zoneId, livreur.id]);

  const fourchette = livreur.min === null ? null : livreur.min === livreur.max ? `${livreur.min} DH` : `${livreur.min} – ${livreur.max} DH`;

  return (
    <Collapsible open={open} onOpenChange={setOpen} className="group/livreur rounded-md border border-border bg-card shadow-2xs">
      <div className="flex flex-wrap items-center gap-3 px-4 py-3">
        <CollapsibleTrigger className="flex min-w-0 flex-1 basis-56 cursor-pointer items-center gap-3 text-left">
          <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground transition-transform group-data-open/livreur:rotate-90" />
          <span className="min-w-0">
            <span className="block truncate text-sm font-semibold text-foreground">{livreur.nom}</span>
            <span className="block truncate text-[11px] text-muted-foreground">{livreur.telephone ?? livreur.email ?? 'Livreur'}</span>
          </span>
        </CollapsibleTrigger>
        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
          <Badge variant="secondary" className="rounded-md text-[10px]">{livreur.nb_villes} ville(s)</Badge>
          {livreur.nb_zones > 0 && <Badge variant="outline" className="hidden rounded-md text-[10px] sm:inline-flex">{livreur.nb_zones} zone(s)</Badge>}
          {fourchette && <Badge variant="outline" className="rounded-md font-mono text-[10px]">{fourchette}</Badge>}
          {livreur.nb_villes === 0 && <Badge variant="outline" className="rounded-md border-[#ff8806]/40 text-[10px] text-[#b45f00] dark:text-[#ffb366]">Sans commission</Badge>}
          {can.create && !livreur.archive && (
            <Button variant="outline" size="sm" className="h-7 rounded-md text-[11px]" onClick={() => onAjouter(livreur)}>
              <Plus className="size-3.5" /> Ajouter des villes
            </Button>
          )}
        </div>
      </div>
      <CollapsibleContent>
        <div className="border-t border-border">
          {loading && zones === null && <p className="flex items-center gap-2 px-4 py-6 text-xs text-muted-foreground"><Loader2 className="size-4 animate-spin" /> Chargement…</p>}
          {error && <p role="alert" className="px-4 py-3 text-xs font-medium text-destructive">{error}</p>}
          {zones !== null && <GrilleLivreur livreur={livreur} zones={zones} can={can} onChange={() => setVersion((v) => v + 1)} />}
        </div>
      </CollapsibleContent>
    </Collapsible>
  );
}

export default function TarifsLivreursIndex({ stats, zones, livreurs, corbeille, filters, can }: Props) {
  const [dialog, setDialog] = useState<{ open: boolean; livreurId: number | null }>({ open: false, livreurId: null });
  const params = { ...filters };
  const naviguer = (changes: Partial<Record<keyof Props['filters'], string | undefined>>) =>
    router.get('/tarifs-livreurs', { ...params, ...changes, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
  const onglet = (s: Statut) => naviguer({ statut: s === 'actives' ? undefined : s });
  const filtre = Boolean(filters.search) || filters.zone_id !== 'all';

  return (
    <AuthenticatedLayout title="Tarifs livreurs">
      <Head title="Tarifs livreurs - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Tarifs livreurs"
          description="Gérez les commissions de livraison par livreur, ville et zone."
          icon={Bike}
          actions={can.create ? (
            <Button size="sm" className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]" onClick={() => setDialog({ open: true, livreurId: null })}>
              <Plus className="size-3.5" /> Nouveau frais livreur
            </Button>
          ) : undefined}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Commissions actives" value={stats.commissions} description={stats.zero > 0 ? `dont ${stats.zero} à 0.00 MAD` : 'Couples livreur × ville'} icon={Wallet} />
          <StatsCard title="Livreurs" value={stats.livreurs} description="Avec au moins une commission active" icon={Users} />
          <StatsCard title="Villes couvertes" value={stats.villes} description="Par au moins une commission" icon={MapPin} />
          <StatsCard title="Commission moyenne (MAD)" value={stats.moyenne ?? '—'} description="Frais livré, par règle" icon={Coins} iconColor="text-[#ff8806]" />
        </div>

        <p className="text-[11px] text-muted-foreground">
          Commission <strong>versée au livreur</strong>, par livreur et par ville. Il n'existe pas de commission standard : sans règle, la commission d'un colis est « manquante ».
        </p>

        <TarifsTabs
          label="Commissions livreurs"
          value={filters.statut}
          onValueChange={(v) => onglet(v as Statut)}
          onglets={[
            { value: 'actives', label: 'Actives', count: stats.commissions, icon: <CheckCircle2 className="size-3.5" /> },
            { value: 'corbeille', label: 'Corbeille', count: stats.corbeille, icon: <Trash2 className="size-3.5" />, tone: 'danger' },
          ]}
        />

        <PageToolbar
          searchPlaceholder={filters.statut === 'corbeille' ? 'Livreur ou ville…' : 'Livreur, téléphone ou email…'}
          initialSearch={filters.search}
          currentParams={params}
          rightActions={filtre && (
            <Button variant="ghost" size="sm" className="h-8 rounded-md text-xs" onClick={() => router.get('/tarifs-livreurs', filters.statut === 'actives' ? {} : { statut: filters.statut }, { replace: true })}>
              <RotateCcw className="size-3.5" /> Réinitialiser
            </Button>
          )}
        >
          {filters.statut !== 'corbeille' && (
            <FilterDropdown label="Zone" value={filters.zone_id} onChange={(v) => naviguer({ zone_id: v === 'all' ? undefined : v })}
              options={[{ value: 'all', label: 'Toutes' }, ...zones.map((z) => ({ value: String(z.id), label: z.name }))]} />
          )}
        </PageToolbar>

        {corbeille !== null && (
          <CorbeilleTarifs lignes={corbeille} peutAgir={can.delete} titulaire="Livreur" montants={['Frais livré', 'Frais refusé']}
            restaurerUrl="/tarifs-livreurs/corbeille/restaurer" purgerUrl="/tarifs-livreurs/corbeille" />
        )}

        {livreurs !== null && (
          livreurs.data.length === 0 ? (
            <div className="rounded-md border border-dashed border-border bg-card p-10 text-center text-xs text-muted-foreground">
              {filtre ? 'Aucun livreur ne correspond à ces filtres.' : 'Aucune commission livreur pour le moment.'}
            </div>
          ) : (
            <div className="space-y-2.5">
              {livreurs.data.map((l) => (
                <CarteLivreur key={l.id} livreur={l} zoneId={filters.zone_id} can={can} onAjouter={(x) => setDialog({ open: true, livreurId: x.id })} />
              ))}
            </div>
          )
        )}

        {livreurs !== null && livreurs.total > 0 && <DataTablePagination meta={livreurs} currentParams={params} pageSizeOptions={[10, 15, 25, 50]} />}
      </div>

      <AjouterTarifLivreurDialog open={dialog.open} livreurId={dialog.livreurId} zoneId={null} onClose={() => setDialog({ open: false, livreurId: null })} />
    </AuthenticatedLayout>
  );
}
