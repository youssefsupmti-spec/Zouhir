import { Fragment, useEffect, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Bike, CircleOff, Coins, Loader2, MapPin, MapPinned, Plus, RotateCcw, Save, Search, Trash2, Undo2, Users, X } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { erreurMontant, normaliserMontant } from '@/Pages/TarifsClients/types';
import { AjouterTarifLivreurDialog } from './AjouterTarifLivreurDialog';

interface LivreurResume {
  id: number;
  nom: string;
  telephone: string | null;
  avatar: string | null;
  nb_villes: number;
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

interface Grille {
  zones: { zone_id: number | null; zone_nom: string; zone_code: string | null; lignes: Commission[] }[];
  supprimees: Commission[];
}

interface Filters {
  livreur_id: string;
  zone_id: string;
}

interface Props {
  livreurs: LivreurResume[];
  stats: { livreurs: number; regles: number; villes: number; moyenne: string | null; zero: number };
  zones: { id: number; name: string; code: string | null }[];
  livreur: { id: number; nom: string; telephone: string | null } | null;
  grille: Grille | null;
  filters: Filters;
  can: { create: boolean; update: boolean; delete: boolean };
}

const initiales = (nom: string) => nom.split(/\s+/).filter(Boolean).slice(0, 2).map((m) => m[0]?.toUpperCase()).join('');

/** Statut d'une commission spécifique (pas de barème standard : aucune comparaison). */
function BadgeCommission({ c }: { c: Commission }) {
  if (c.zero) return <Badge variant="outline" className="rounded-md border-sky-500/30 text-[10px] text-sky-700 dark:text-sky-300">Commission à zéro</Badge>;
  return <Badge variant="outline" className="rounded-md border-emerald-500/30 text-[10px] text-emerald-700 dark:text-emerald-400">Commission spécifique</Badge>;
}

/** Sélecteur de livreur avec recherche (nom, téléphone). Livreurs actifs uniquement (serveur). */
function SelecteurLivreur({ livreurs, actuel, onChoisir }: { livreurs: LivreurResume[]; actuel: number | null; onChoisir: (id: number | null) => void }) {
  const [recherche, setRecherche] = useState('');
  const [ouvert, setOuvert] = useState(false);
  const choisi = livreurs.find((l) => l.id === actuel) ?? null;
  const liste = useMemo(() => {
    const q = recherche.trim().toLowerCase();
    return q === '' ? livreurs : livreurs.filter((l) => `${l.nom} ${l.telephone ?? ''}`.toLowerCase().includes(q));
  }, [livreurs, recherche]);

  return (
    <div className="relative w-full sm:w-80">
      <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
      <Input
        role="combobox"
        aria-expanded={ouvert}
        aria-controls="liste-livreurs"
        aria-label="Rechercher un livreur"
        value={ouvert ? recherche : choisi?.nom ?? recherche}
        placeholder="Choisir un livreur (nom, téléphone)…"
        onFocus={() => { setOuvert(true); setRecherche(''); }}
        onBlur={() => setTimeout(() => setOuvert(false), 150)}
        onChange={(e) => setRecherche(e.target.value)}
        className="h-8 rounded-md bg-background pr-8 pl-8 text-xs"
      />
      {choisi && (
        <button type="button" aria-label="Retirer le livreur" className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground" onClick={() => onChoisir(null)}>
          <X className="size-3.5" />
        </button>
      )}
      {ouvert && (
        <ul id="liste-livreurs" role="listbox" className="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-md">
          {liste.map((l) => (
            <li key={l.id} role="option" aria-selected={l.id === actuel}>
              <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => { onChoisir(l.id); setOuvert(false); }}
                className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-xs hover:bg-muted">
                {l.avatar
                  ? <img src={l.avatar} alt="" className="size-6 rounded-full object-cover" />
                  : <span className="flex size-6 items-center justify-center rounded-full bg-[#004c68]/10 text-[10px] font-bold text-[#004c68] dark:text-[#ff8806]">{initiales(l.nom)}</span>}
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-medium">{l.nom}</span>
                  {l.telephone && <span className="block truncate text-[10px] text-muted-foreground">{l.telephone}</span>}
                </span>
                <Badge variant="secondary" className="rounded-md text-[10px]">{l.nb_villes} ville(s)</Badge>
              </button>
            </li>
          ))}
          {liste.length === 0 && <li className="px-2 py-3 text-center text-[11px] text-muted-foreground">Aucun livreur actif.</li>}
        </ul>
      )}
    </div>
  );
}

/** Grille des commissions SPÉCIFIQUES d'un livreur : édition inline, sélection, lot, suppression, restauration. */
function GrilleSpecifique({ livreurId, grille, can, onZone }: { livreurId: number; grille: Grille; can: Props['can']; onZone: (z: number) => void }) {
  const toutes = useMemo(() => grille.zones.flatMap((z) => z.lignes), [grille]);
  const [brouillons, setBrouillons] = useState<Record<number, { livraison: string; refus: string }>>({});
  const [selection, setSelection] = useState<Set<number>>(new Set());
  const [lot, setLot] = useState({ livraison: '', refus: '0' });
  const [suppression, setSuppression] = useState(false);
  const [busy, setBusy] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  useEffect(() => {
    setBrouillons({});
    setSelection(new Set());
  }, [grille]);

  const valeur = (c: Commission, k: 'livraison' | 'refus') => brouillons[c.id]?.[k] ?? c[k];
  const modifiee = (c: Commission) => brouillons[c.id] !== undefined
    && (normaliserMontant(valeur(c, 'livraison')) !== c.livraison || normaliserMontant(valeur(c, 'refus')) !== c.refus);
  const modifiees = toutes.filter(modifiee);
  const invalides = modifiees.filter((c) => erreurMontant(valeur(c, 'livraison')) || erreurMontant(valeur(c, 'refus'))).length;
  const selectionnees = toutes.filter((c) => selection.has(c.id));

  const envoyer = (lignes: { id: number; livraison: string; refus: string }[], apres?: () => void) =>
    router.put('/tarifs-livreurs', { livreur_id: livreurId, lignes }, {
      preserveScroll: true,
      onStart: () => setBusy(true),
      onFinish: () => setBusy(false),
      onError: (e) => setErreur(Object.values(e)[0] ?? 'Enregistrement refusé.'),
      onSuccess: () => { setErreur(null); apres?.(); },
    });

  const basculer = (ids: number[], coche: boolean) => setSelection((prev) => {
    const n = new Set(prev);
    ids.forEach((id) => (coche ? n.add(id) : n.delete(id)));
    return n;
  });

  return (
    <div className="space-y-3">
      {erreur && <div role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-2 text-xs text-destructive">{erreur}</div>}

      {(can.update || can.delete) && (
        <div className="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted/30 px-4 py-2 text-[11px]">
          <span className="text-muted-foreground">{selectionnees.length > 0 ? `${selectionnees.length} commission(s) sélectionnée(s)` : 'Cochez des villes pour agir en lot'}</span>
          {can.update && selectionnees.length > 0 && (
            <>
              <Input aria-label="Commission livraison du lot" inputMode="decimal" placeholder="Livraison" value={lot.livraison}
                onChange={(e) => setLot((l) => ({ ...l, livraison: e.target.value }))} className="h-7 w-24 font-mono text-xs" />
              <Input aria-label="Commission refus du lot" inputMode="decimal" placeholder="Refus" value={lot.refus}
                onChange={(e) => setLot((l) => ({ ...l, refus: e.target.value }))} className="h-7 w-20 font-mono text-xs" />
              <Button size="sm" variant="outline" className="h-7 rounded-md text-[11px]" disabled={busy || Boolean(erreurMontant(lot.livraison) || erreurMontant(lot.refus))}
                onClick={() => envoyer(selectionnees.map((c) => ({ id: c.id, livraison: normaliserMontant(lot.livraison), refus: normaliserMontant(lot.refus) })), () => setLot({ livraison: '', refus: '0' }))}>
                Appliquer à la sélection
              </Button>
            </>
          )}
          {can.delete && (
            <Button size="sm" variant="outline" className="ml-auto h-7 rounded-md text-[11px] text-destructive hover:text-destructive" disabled={selectionnees.length === 0} onClick={() => setSuppression(true)}>
              <Trash2 className="size-3.5" /> Supprimer la sélection
            </Button>
          )}
        </div>
      )}

      {can.update && modifiees.length > 0 && (
        <div className="sticky top-16 z-10 flex flex-wrap items-center gap-2 rounded-md border border-[#ff8806]/30 bg-[#ff8806]/10 px-4 py-2 text-[11px] backdrop-blur">
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

      <div className="rounded-md border border-border bg-card shadow-2xs">
        <div className="overflow-x-auto">
          <Table className="min-w-[700px] table-fixed text-xs">
            <colgroup>
              <col className="w-10" />
              <col />
              <col className="w-44" />
              <col className="w-36" />
              <col className="w-32" />
              <col className="w-40" />
            </colgroup>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="pl-4"><span className="sr-only">Sélection</span></TableHead>
                <TableHead>Ville</TableHead>
                <TableHead>Zone</TableHead>
                <TableHead className="text-right">Commission livraison</TableHead>
                <TableHead className="text-right">Commission refus</TableHead>
                <TableHead className="pr-4">Statut</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {grille.zones.map((z) => {
                const ids = z.lignes.map((l) => l.id);
                const cochees = ids.filter((id) => selection.has(id)).length;
                return (
                  <Fragment key={z.zone_id ?? 'sans'}>
                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                      <TableCell className="pl-4">
                        <Checkbox aria-label={`Sélectionner toute la zone ${z.zone_nom}`} checked={cochees === ids.length && ids.length > 0}
                          indeterminate={cochees > 0 && cochees < ids.length} onCheckedChange={(c) => basculer(ids, Boolean(c))} />
                      </TableCell>
                      <TableCell colSpan={4}>
                        <span className="flex items-center gap-2 font-semibold">
                          <MapPinned className="size-3.5 text-[#004c68] dark:text-[#ff8806]" /> {z.zone_nom}
                          {z.zone_code && <code className="rounded bg-muted px-1 font-mono text-[10px]">{z.zone_code}</code>}
                          <span className="font-normal text-muted-foreground">· {z.lignes.length} ville(s)</span>
                        </span>
                      </TableCell>
                      <TableCell className="pr-4 text-right">
                        {can.create && z.zone_id !== null && (
                          <Button size="sm" variant="ghost" className="h-6 rounded-md px-2 text-[10px]" onClick={() => onZone(z.zone_id as number)}>
                            <Plus className="size-3" /> Villes de la zone
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                    {z.lignes.map((c) => (
                      <TableRow key={c.id} data-state={selection.has(c.id) || modifiee(c) ? 'selected' : undefined}>
                        <TableCell className="pl-4">
                          <Checkbox aria-label={`Sélectionner ${c.ville_nom}`} checked={selection.has(c.id)} onCheckedChange={(v) => basculer([c.id], Boolean(v))} />
                        </TableCell>
                        <TableCell className="truncate font-medium" title={c.ville_nom}>{c.ville_nom}</TableCell>
                        <TableCell className="truncate text-muted-foreground">{c.zone_nom}</TableCell>
                        {(['livraison', 'refus'] as const).map((k) => (
                          <TableCell key={k} className="text-right">
                            {can.update ? (
                              <div>
                                <Input inputMode="decimal" aria-label={`Commission ${k} ${c.ville_nom}`} value={valeur(c, k)}
                                  aria-invalid={Boolean(erreurMontant(valeur(c, k)))}
                                  onChange={(e) => setBrouillons((b) => ({ ...b, [c.id]: { livraison: valeur(c, 'livraison'), refus: valeur(c, 'refus'), [k]: e.target.value } }))}
                                  className={`h-7 rounded-md text-right font-mono text-xs ${k === 'livraison' ? 'font-semibold text-[#004c68] dark:text-[#ff8806]' : ''}`} />
                                {erreurMontant(valeur(c, k)) && <p className="mt-0.5 text-[10px] text-destructive">{erreurMontant(valeur(c, k))}</p>}
                              </div>
                            ) : <span className="font-mono">{c[k]}</span>}
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
              {grille.zones.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                    Aucune commission pour ce livreur : ses colis auront une commission « manquante » tant qu'aucune ville ne lui est affectée.
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {grille.supprimees.length > 0 && (
        <div className="rounded-md border border-dashed border-border bg-card p-4 text-xs">
          <p className="mb-2 font-semibold">Commissions supprimées ({grille.supprimees.length})</p>
          <div className="flex flex-wrap gap-2">
            {grille.supprimees.map((c) => (
              <span key={c.id} className="inline-flex items-center gap-2 rounded-md border border-border px-2 py-1">
                {c.ville_nom} <span className="font-mono text-muted-foreground">{c.livraison} / {c.refus}</span>
                {can.delete && (
                  <Button size="sm" variant="ghost" className="h-6 rounded-md px-1.5 text-[10px]" aria-label={`Restaurer ${c.ville_nom}`} disabled={busy}
                    onClick={() => router.post('/tarifs-livreurs/restaurer', { livreur_id: livreurId, ids: [c.id] }, { preserveScroll: true, onError: (e) => setErreur(Object.values(e)[0] ?? 'Restauration refusée.') })}>
                    <Undo2 className="size-3" /> Restaurer
                  </Button>
                )}
              </span>
            ))}
          </div>
        </div>
      )}

      <ConfirmDeleteDialog
        open={suppression}
        onOpenChange={(o) => !o && !busy && setSuppression(false)}
        title={`Supprimer ${selectionnees.length} commission(s)`}
        description={`${selectionnees.map((c) => c.ville_nom).slice(0, 8).join(', ')}${selectionnees.length > 8 ? '…' : ''}. Les prochains colis de ces villes n'auront plus de commission pour ce livreur (« manquante »). Les colis existants ne sont pas modifiés ; la commission reste restaurable.`}
        isLoading={busy}
        onConfirm={() => router.delete('/tarifs-livreurs', {
          data: { livreur_id: livreurId, ids: selectionnees.map((c) => c.id) },
          preserveScroll: true,
          onStart: () => setBusy(true),
          onFinish: () => setBusy(false),
          onError: (e) => setErreur(Object.values(e)[0] ?? 'Suppression refusée.'),
          onSuccess: () => setSuppression(false),
        })}
      />
    </div>
  );
}

export default function TarifsLivreursIndex({ livreurs, stats, zones, livreur, grille, filters, can }: Props) {
  const [dialog, setDialog] = useState<{ open: boolean; zoneId: number | null }>({ open: false, zoneId: null });
  const params = { ...filters };
  const naviguer = (changes: Partial<Record<keyof Filters, string | undefined>>) =>
    router.get('/tarifs-livreurs', { ...params, ...changes }, { preserveState: true, preserveScroll: true, replace: true });

  return (
    <AuthenticatedLayout title="Tarifs livreurs">
      <Head title="Tarifs livreurs - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Tarifs livreurs"
          description="Gérez les commissions de livraison par livreur, ville et zone."
          icon={Bike}
          actions={can.create ? (
            <Button size="sm" className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]" onClick={() => setDialog({ open: true, zoneId: null })}>
              <Plus className="size-3.5" /> Affecter des villes
            </Button>
          ) : undefined}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <StatsCard title="Livreurs configurés" value={stats.livreurs} description="Avec commission spécifique" icon={Users} />
          <StatsCard title="Règles spécifiques" value={stats.regles} description="Couples livreur × ville actifs" icon={Bike} />
          <StatsCard title="Villes couvertes" value={stats.villes} description="Par une commission spécifique" icon={MapPin} />
          <StatsCard title="Commission moyenne" value={stats.moyenne ?? '—'} description="MAD, règles spécifiques" icon={Coins} iconColor="text-[#ff8806]" />
          <StatsCard title="Commissions à zéro" value={stats.zero} description="Règles spécifiques à 0.00 MAD" icon={CircleOff} />
        </div>

        <p className="rounded-md border border-[#ff8806]/30 bg-[#ff8806]/5 px-4 py-2 text-[11px] text-[#7a3f00] dark:text-[#ffb366]">
          Commission <strong>versée au livreur</strong>, définie par livreur et par ville. Il n'existe pas de barème standard des commissions :
          sans commission affectée, la commission d'un colis est « manquante ». Distincte du tarif de livraison <strong>facturé au client</strong>.
        </p>

            <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3 shadow-2xs sm:flex-row sm:flex-wrap sm:items-center">
              <SelecteurLivreur livreurs={livreurs} actuel={livreur?.id ?? null} onChoisir={(id) => naviguer({ livreur_id: id === null ? undefined : String(id) })} />
              <FilterDropdown label="Zone" value={filters.zone_id} onChange={(v) => naviguer({ zone_id: v === 'all' ? undefined : v })}
                options={[{ value: 'all', label: 'Toutes' }, ...zones.map((z) => ({ value: String(z.id), label: z.name }))]} />
              {livreur && can.create && (
                <Button size="sm" variant="outline" className="h-8 rounded-md text-xs sm:ml-auto" onClick={() => setDialog({ open: true, zoneId: null })}>
                  <Plus className="size-3.5" /> Affecter des villes à {livreur.nom}
                </Button>
              )}
            </div>

            {livreur === null || grille === null ? (
              <div className="rounded-md border border-dashed border-border bg-card p-10 text-center text-xs text-muted-foreground">
                Sélectionnez un livreur pour afficher ses commissions par zone et ville.
              </div>
            ) : (
              <GrilleSpecifique livreurId={livreur.id} grille={grille} can={can} onZone={(z) => setDialog({ open: true, zoneId: z })} />
            )}

      </div>

      <AjouterTarifLivreurDialog
        open={dialog.open}
        livreurId={livreur?.id ?? null}
        zoneId={dialog.zoneId}
        onClose={() => setDialog({ open: false, zoneId: null })}
      />
    </AuthenticatedLayout>
  );
}
