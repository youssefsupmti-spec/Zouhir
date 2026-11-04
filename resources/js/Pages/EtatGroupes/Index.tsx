import { useMemo, useState, type FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Layers, Link2, Loader2, Lock, Pencil, Plus, Search, Tags, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { ActionGroupee, BarreSelection, CaseACocher, useSelection } from '@/components/common/SelectionGroupee';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';

interface Groupe {
  id: number; code: string; nom: string; description: string | null; couleur: string | null; sort_order: number;
  actif: boolean; suivi_actif: boolean; suivi_planifie: boolean; systeme: boolean; etats: number; colis: number;
}
interface Etat { id: number; libelle: string; couleur: string | null; actif: boolean; origine: string; groupe_id: number | null; colis: number }

interface Props {
  onglet: 'groupes' | 'etats';
  groupes: Groupe[];
  etats: Etat[];
  stats: { groupes: number; actifs: number; etats: number; non_classes: number };
  can: { manage: boolean };
}

const VIDE = { nom: '', code: '', description: '', couleur: '#004c68', sort_order: 10, actif: true, suivi_actif: false, suivi_planifie: false };
const estCouleur = (c: string | null) => !!c && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(c);

export default function EtatGroupesIndex({ onglet, groupes, etats, stats, can }: Props) {
  const [edition, setEdition] = useState<Groupe | 'nouveau' | null>(null);
  const [suppression, setSuppression] = useState<Groupe | null>(null);
  const [filtreGroupe, setFiltreGroupe] = useState<string>('tous');
  const [recherche, setRecherche] = useState('');
  const [cible, setCible] = useState<string>('');
  const form = useForm({ ...VIDE });

  const parId = useMemo(() => Object.fromEntries(groupes.map((g) => [g.id, g])), [groupes]);
  const etatsVisibles = etats.filter((e) =>
    (filtreGroupe === 'tous' || (filtreGroupe === 'aucun' ? e.groupe_id === null : String(e.groupe_id) === filtreGroupe))
    && e.libelle.toLowerCase().includes(recherche.toLowerCase()));
  const selection = useSelection(etatsVisibles.map((e) => e.id));

  const ouvrir = (g: Groupe | 'nouveau') => {
    form.clearErrors();
    form.setData(g === 'nouveau' ? { ...VIDE, sort_order: (groupes.at(-1)?.sort_order ?? 0) + 1 } : {
      nom: g.nom, code: g.code, description: g.description ?? '', couleur: g.couleur ?? '#004c68', sort_order: g.sort_order,
      actif: g.actif, suivi_actif: g.suivi_actif, suivi_planifie: g.suivi_planifie,
    });
    setEdition(g);
  };
  const enregistrer = (e: FormEvent) => {
    e.preventDefault();
    const fin = { preserveScroll: true, onSuccess: () => setEdition(null) };
    if (edition === 'nouveau') form.post('/etat-groupes', fin);
    else if (edition) form.put(`/etat-groupes/${edition.id}`, fin);
  };
  const rattacher = (ids: number[], groupeId: string) => {
    if (!groupeId) return;
    router.post(`/etat-groupes/${groupeId}/etats`, { etat_ids: ids }, { preserveScroll: true, onSuccess: () => { selection.vider(); setCible(''); } });
  };

  return (
    <AuthenticatedLayout title="Groupes d’état">
      <Head title="Groupes d’état - OUAISS EXPRESS" />
      <div className="space-y-6">
        <PageHeader
          title="Groupes d’état"
          description="Classification des états des colis. Chaque groupe actif devient une carte-onglet de la page Colis ; chaque état appartient à un seul groupe."
          icon={Layers}
          actions={can.manage && onglet === 'groupes' && (
            <Button size="sm" className="h-8 bg-[#004c68] text-xs text-white hover:bg-[#00384d]" onClick={() => ouvrir('nouveau')}><Plus className="size-3.5" /> Nouveau groupe</Button>
          )}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Groupes" value={stats.groupes} description={`${stats.actifs} affichés sur la page Colis`} icon={Layers} />
          <StatsCard title="États" value={stats.etats} description="Statuts du référentiel" icon={Tags} />
          <StatsCard title="États non classés" value={stats.non_classes} description="Rattachez-les à un groupe" icon={Link2} iconColor={stats.non_classes > 0 ? 'text-destructive' : undefined} />
          <StatsCard title="Colis classés" value={groupes.reduce((a, g) => a + g.colis, 0).toLocaleString('fr-FR')} description="Hors corbeille comprise" icon={Layers} />
        </div>

        <TarifsTabs
          label="Classification"
          value={onglet}
          onValueChange={(v) => router.get('/etat-groupes', { onglet: v }, { preserveScroll: true })}
          onglets={[
            { value: 'groupes', label: 'Groupes', count: stats.groupes, icon: <Layers className="size-3.5" /> },
            { value: 'etats', label: 'États par groupe', count: stats.etats, icon: <Tags className="size-3.5" />, tone: stats.non_classes > 0 ? 'danger' : 'default' },
          ]}
        />

        {onglet === 'groupes' && (
          <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
            <Table className="min-w-[860px] text-xs">
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="w-12 pl-4">Ordre</TableHead>
                  <TableHead>Groupe</TableHead>
                  <TableHead>Code</TableHead>
                  <TableHead className="text-right">États</TableHead>
                  <TableHead className="text-right">Colis</TableHead>
                  <TableHead>Suivi</TableHead>
                  <TableHead>Page Colis</TableHead>
                  <TableHead className="w-24 pr-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {groupes.map((g) => (
                  <TableRow key={g.id} className={g.actif ? undefined : 'opacity-60'}>
                    <TableCell className="pl-4 tabular-nums text-muted-foreground">{g.sort_order}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <span className="size-3 shrink-0 rounded-full" style={{ background: estCouleur(g.couleur) ? g.couleur! : '#94a3b8' }} />
                        <span className="font-semibold">{g.nom}</span>
                        {g.systeme && <span title="Groupe utilisé par les règles métier" className="text-muted-foreground"><Lock className="size-3" /></span>}
                      </div>
                      {g.description && <div className="mt-0.5 pl-5 text-[11px] text-muted-foreground">{g.description}</div>}
                    </TableCell>
                    <TableCell><code className="rounded bg-muted px-1.5 py-px text-[10px]">{g.code}</code></TableCell>
                    <TableCell className="text-right tabular-nums">
                      <button type="button" className="hover:underline" onClick={() => { router.get('/etat-groupes', { onglet: 'etats' }, { preserveScroll: true, onSuccess: () => setFiltreGroupe(String(g.id)) }); }}>{g.etats}</button>
                    </TableCell>
                    <TableCell className="text-right tabular-nums">{g.colis.toLocaleString('fr-FR')}</TableCell>
                    <TableCell className="text-[11px] text-muted-foreground">{[g.suivi_actif && 'actif', g.suivi_planifie && 'planifié'].filter(Boolean).join(' · ') || '—'}</TableCell>
                    <TableCell>{g.actif ? <span className="rounded-full bg-emerald-600/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">Affiché</span> : <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">Masqué</span>}</TableCell>
                    <TableCell className="pr-4 text-right">
                      {can.manage && (
                        <span className="inline-flex gap-0.5">
                          <Button size="icon" variant="ghost" className="size-7" aria-label={`Modifier ${g.nom}`} onClick={() => ouvrir(g)}><Pencil className="size-3.5 text-[#ff8806]" /></Button>
                          <Button size="icon" variant="ghost" className="size-7" aria-label={`Supprimer ${g.nom}`} disabled={g.systeme || g.etats > 0}
                            title={g.systeme ? 'Groupe système : désactivez-le plutôt' : g.etats > 0 ? 'Retirez d’abord ses états' : 'Supprimer'} onClick={() => setSuppression(g)}>
                            <Trash2 className="size-3.5 text-destructive" />
                          </Button>
                        </span>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}

        {onglet === 'etats' && (
          <>
            <div className="flex flex-wrap items-center gap-2">
              <div className="relative">
                <Search className="absolute left-2 top-2 size-3.5 text-muted-foreground" />
                <Input value={recherche} onChange={(e) => setRecherche(e.target.value)} placeholder="Rechercher un état…" className="h-8 w-60 pl-7 text-xs" />
              </div>
              <select value={filtreGroupe} onChange={(e) => setFiltreGroupe(e.target.value)} className="h-8 rounded-md border border-input bg-transparent px-2 text-xs">
                <option value="tous">Tous les groupes</option>
                <option value="aucun">Non classés ({stats.non_classes})</option>
                {groupes.map((g) => <option key={g.id} value={g.id}>{g.nom} ({g.etats})</option>)}
              </select>
            </div>

            {can.manage && (
              <BarreSelection taille={selection.taille} onVider={selection.vider}>
                <span>Rattacher à</span>
                <select value={cible} onChange={(e) => setCible(e.target.value)} className="h-7 rounded border-0 bg-white/15 px-2 text-xs text-white [&>option]:text-foreground">
                  <option value="">choisir un groupe…</option>
                  {groupes.map((g) => <option key={g.id} value={g.id}>{g.nom}</option>)}
                </select>
                <ActionGroupee icone={<Link2 className="size-3.5" />} disabled={!cible} onClick={() => rattacher(selection.ids, cible)}>Rattacher</ActionGroupee>
              </BarreSelection>
            )}

            <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
              <Table className="min-w-[760px] text-xs">
                <TableHeader>
                  <TableRow className="hover:bg-transparent">
                    {can.manage && <TableHead className="w-10 pl-4"><CaseACocher label="Tout sélectionner" coche={selection.tous} partiel={selection.partiel} onChange={selection.basculerTous} /></TableHead>}
                    <TableHead className={can.manage ? '' : 'pl-4'}>État</TableHead>
                    <TableHead>Origine</TableHead>
                    <TableHead className="text-right">Colis</TableHead>
                    <TableHead className="w-64 pr-4">Groupe</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {etatsVisibles.map((e) => {
                    const g = e.groupe_id !== null ? parId[e.groupe_id] : null;
                    return (
                      <TableRow key={e.id} data-state={selection.estChoisi(e.id) ? 'selected' : undefined}>
                        {can.manage && <TableCell className="pl-4"><CaseACocher label={`Sélectionner ${e.libelle}`} coche={selection.estChoisi(e.id)} onChange={() => selection.basculer(e.id)} /></TableCell>}
                        <TableCell className={can.manage ? '' : 'pl-4'}>
                          <div className="flex items-center gap-2">
                            <span className="size-2.5 rounded-full border border-black/10" style={estCouleur(e.couleur) ? { background: e.couleur! } : undefined} />
                            <span className="font-semibold">{e.libelle}</span>
                            {!e.actif && <span className="rounded bg-muted px-1.5 text-[10px] text-muted-foreground">inactif</span>}
                          </div>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{e.origine === 'referentiel_legacy' ? 'Ancien référentiel' : e.origine === 'hors_referentiel' ? 'Hors référentiel' : 'Plateforme'}</TableCell>
                        <TableCell className="text-right tabular-nums">{e.colis.toLocaleString('fr-FR')}</TableCell>
                        <TableCell className="pr-4">
                          {can.manage ? (
                            <select
                              value={e.groupe_id ?? ''}
                              onChange={(ev) => rattacher([e.id], ev.target.value)}
                              className="h-7 w-full rounded-md border px-2 text-xs"
                              style={g && estCouleur(g.couleur) ? { borderColor: g.couleur!, color: g.couleur! } : undefined}
                            >
                              <option value="" disabled>— non classé —</option>
                              {groupes.map((x) => <option key={x.id} value={x.id}>{x.nom}</option>)}
                            </select>
                          ) : (g?.nom ?? '—')}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                  {etatsVisibles.length === 0 && <TableRow><TableCell colSpan={5} className="py-10 text-center text-muted-foreground">Aucun état.</TableCell></TableRow>}
                </TableBody>
              </Table>
            </div>
          </>
        )}
      </div>

      <Dialog open={edition !== null} onOpenChange={(o) => !o && !form.processing && setEdition(null)}>
        <DialogContent className="max-w-md">
          <form onSubmit={enregistrer} className="space-y-3">
            <DialogHeader>
              <DialogTitle>{edition === 'nouveau' ? 'Nouveau groupe d’état' : `Groupe « ${typeof edition === 'object' && edition ? edition.nom : ''} »`}</DialogTitle>
              <DialogDescription className="text-xs">Un groupe actif apparaît comme carte-onglet sur la page Colis.</DialogDescription>
            </DialogHeader>
            <div className="grid grid-cols-[1fr_120px] gap-2">
              <div className="space-y-1.5"><Label htmlFor="g-nom">Nom</Label><Input id="g-nom" value={form.data.nom} onChange={(e) => form.setData('nom', e.target.value)} />{form.errors.nom && <p className="text-[11px] text-destructive">{form.errors.nom}</p>}</div>
              <div className="space-y-1.5"><Label htmlFor="g-code">Code</Label><Input id="g-code" value={form.data.code} disabled={edition !== 'nouveau'} placeholder="auto" onChange={(e) => form.setData('code', e.target.value)} />{form.errors.code && <p className="text-[11px] text-destructive">{form.errors.code}</p>}</div>
            </div>
            <div className="space-y-1.5"><Label htmlFor="g-desc">Description (affichée sur la carte)</Label><Textarea id="g-desc" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></div>
            <div className="grid grid-cols-2 gap-2">
              <div className="space-y-1.5">
                <Label htmlFor="g-couleur">Couleur</Label>
                <div className="flex gap-2"><input type="color" value={form.data.couleur} onChange={(e) => form.setData('couleur', e.target.value)} className="h-8 w-10 rounded border" /><Input id="g-couleur" value={form.data.couleur} onChange={(e) => form.setData('couleur', e.target.value)} /></div>
                {form.errors.couleur && <p className="text-[11px] text-destructive">{form.errors.couleur}</p>}
              </div>
              <div className="space-y-1.5"><Label htmlFor="g-ordre">Ordre</Label><Input id="g-ordre" type="number" min={0} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} /></div>
            </div>
            <div className="space-y-1.5 text-xs">
              <label className="flex items-center gap-2"><input type="checkbox" className="size-4" checked={form.data.actif} onChange={(e) => form.setData('actif', e.target.checked)} /> Affiché sur la page Colis</label>
              <label className="flex items-center gap-2"><input type="checkbox" className="size-4" checked={form.data.suivi_actif} onChange={(e) => form.setData('suivi_actif', e.target.checked)} /> Colis à suivre (en circulation)</label>
              <label className="flex items-center gap-2"><input type="checkbox" className="size-4" checked={form.data.suivi_planifie} onChange={(e) => form.setData('suivi_planifie', e.target.checked)} /> Colis à date convenue (planifiés)</label>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" size="sm" onClick={() => setEdition(null)}>Annuler</Button>
              <Button type="submit" size="sm" disabled={form.processing} className="bg-[#004c68] text-white hover:bg-[#00384d]">{form.processing && <Loader2 className="size-3.5 animate-spin" />} Enregistrer</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <ConfirmDeleteDialog
        open={suppression !== null}
        onOpenChange={(o) => !o && setSuppression(null)}
        title="Supprimer le groupe"
        description={`Supprimer le groupe « ${suppression?.nom ?? ''} » ? Il ne contient aucun état.`}
        onConfirm={() => suppression && router.delete(`/etat-groupes/${suppression.id}`, { preserveScroll: true, onFinish: () => setSuppression(null) })}
      />
    </AuthenticatedLayout>
  );
}
