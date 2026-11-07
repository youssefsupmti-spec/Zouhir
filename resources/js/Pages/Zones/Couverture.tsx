import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeftRight, Building2, MapPinned, Pencil, Radar, ShieldAlert, Truck, UserRound, Users } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Type = 'regional_flotte' | 'national_partenaire';
interface Personne { id: number; nom: string; telephone: string | null }
interface Couverture {
  id: number; type_couverture: Type; statut_couverture: string;
  zone: { id: number; code: string; name: string; type: string; villes: number };
  titulaire: Personne | null; backup: Personne | null; partenaire: { id: number; code: string; name: string } | null;
  capacite: number | null; charge: number; taux: number | null; notes: string | null; bascule_at: string | null;
}

interface Props {
  couvertures: Couverture[];
  livreurs: { id: number; nom_complet: string; telephone: string | null }[];
  partenaires: { id: number; code: string; name: string }[];
  statuts: Record<string, string>;
  can: { manage: boolean };
}

const SELECT = 'flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs';
const COULEUR: Record<Type, string> = { regional_flotte: '#004c68', national_partenaire: '#f78304' };
const TON_STATUT: Record<string, string> = {
  optimal: 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
  normal: 'bg-sky-600/10 text-sky-700 dark:text-sky-300',
  degrade: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
  non_couvert: 'bg-destructive/10 text-destructive',
};

/** Jauge : vert < 50 %, orange 50–80 %, rouge > 80 %. Sans capacité saisie, seul le volume est affiché. */
function Jauge({ c }: { c: Couverture }) {
  if (c.taux === null) {
    return <p className="text-[11px] text-muted-foreground"><span className="font-semibold tabular-nums text-foreground">{c.charge}</span> colis en cours · capacité non définie</p>;
  }
  const couleur = c.taux > 80 ? 'bg-red-600' : c.taux >= 50 ? 'bg-[#f78304]' : 'bg-emerald-600';
  return (
    <div>
      <div className="flex justify-between text-[11px] text-muted-foreground">
        <span><span className="font-semibold tabular-nums text-foreground">{c.charge}</span> / {c.capacite} colis en cours</span>
        <span className="font-semibold tabular-nums text-foreground">{c.taux}%</span>
      </div>
      <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow={c.taux} aria-valuemin={0} aria-valuemax={100}>
        <div className={`h-full rounded-full ${couleur}`} style={{ width: `${Math.min(c.taux, 100)}%` }} />
      </div>
    </div>
  );
}

function Ligne({ icone, label, valeur }: { icone: ReactNode; label: string; valeur: string | null }) {
  return (
    <li className="flex items-center gap-1.5">
      {icone}
      <span className="text-muted-foreground">{label} :</span>
      <span className={valeur ? 'truncate font-semibold' : 'italic text-muted-foreground'}>{valeur ?? 'à affecter'}</span>
    </li>
  );
}

export default function ZonesCouverture({ couvertures, livreurs, partenaires, statuts, can }: Props) {
  const [filtre, setFiltre] = useState<'toutes' | Type>('toutes');
  const [edition, setEdition] = useState<Couverture | null>(null);
  const [recherche, setRecherche] = useState('');
  const f = useForm({ type_couverture: 'regional_flotte' as Type, titulaire_user_id: '' as string | number, backup_user_id: '' as string | number, delivery_partner_id: '' as string | number, capacite_journaliere: '' as string | number, notes: '' });

  const visibles = useMemo(() => couvertures.filter((c) => filtre === 'toutes' || c.type_couverture === filtre), [couvertures, filtre]);
  const nonCouvertes = couvertures.filter((c) => c.statut_couverture === 'non_couvert').length;
  const livreursFiltres = livreurs.filter((l) => !recherche || `${l.nom_complet} ${l.telephone ?? ''}`.toLowerCase().includes(recherche.toLowerCase()));

  const ouvrir = (c: Couverture) => {
    f.clearErrors();
    f.setData({ type_couverture: c.type_couverture, titulaire_user_id: c.titulaire?.id ?? '', backup_user_id: c.backup?.id ?? '', delivery_partner_id: c.partenaire?.id ?? '', capacite_journaliere: c.capacite ?? '', notes: c.notes ?? '' });
    setRecherche('');
    setEdition(c);
  };
  const soumettre = (e: FormEvent) => {
    e.preventDefault();
    if (edition) f.put(`/zones/couverture/${edition.id}`, { preserveScroll: true, onSuccess: () => setEdition(null) });
  };
  const basculer = (c: Couverture) => router.post(`/zones/couverture/${c.id}/basculer`, {}, { preserveScroll: true });

  // Liste du combobox : on garde toujours visible le livreur déjà choisi.
  const options = (choisi: string | number) => {
    const liste = livreursFiltres.slice();
    const actuel = livreurs.find((l) => String(l.id) === String(choisi));
    if (actuel && !liste.includes(actuel)) liste.unshift(actuel);
    return liste;
  };

  return (
    <AuthenticatedLayout title="Couverture des zones">
      <Head title="Couverture des zones - OUAISS EXPRESS" />
      <div className="space-y-6">
        <PageHeader
          title="Couverture des zones"
          description="Qui livre chaque zone : titulaire et remplaçant de la flotte régionale, ou transporteur partenaire du réseau national."
          icon={Radar}
          badge={nonCouvertes > 0 ? `${nonCouvertes} zone(s) à affecter` : undefined}
        />

        <TarifsTabs
          label="Couverture"
          value={filtre}
          onValueChange={(v) => setFiltre(v as typeof filtre)}
          onglets={[
            { value: 'toutes', label: 'Toutes', count: couvertures.length, icon: <MapPinned className="size-3.5" /> },
            { value: 'regional_flotte', label: 'Flotte régionale', count: couvertures.filter((c) => c.type_couverture === 'regional_flotte').length, icon: <Users className="size-3.5" /> },
            { value: 'national_partenaire', label: 'Réseau national', count: couvertures.filter((c) => c.type_couverture === 'national_partenaire').length, icon: <Truck className="size-3.5" /> },
          ]}
        />

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
          {visibles.map((c) => {
            const regional = c.type_couverture === 'regional_flotte';
            return (
              <article key={c.id} className="flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xs">
                <div className="h-1" style={{ backgroundColor: COULEUR[c.type_couverture] }} />
                <div className="flex flex-1 flex-col p-4">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="font-mono text-[11px] font-semibold" style={{ color: COULEUR[c.type_couverture] }}>{c.zone.code}</p>
                      <h3 className="truncate text-sm font-bold">{c.zone.name}</h3>
                      <p className="text-[10px] text-muted-foreground">{regional ? 'Flotte régionale' : 'Réseau national'} · {c.zone.villes} ville(s)</p>
                    </div>
                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ${TON_STATUT[c.statut_couverture] ?? ''}`}>{statuts[c.statut_couverture] ?? c.statut_couverture}</span>
                  </div>

                  <ul className="mt-3 space-y-1 text-xs">
                    {regional ? (
                      <>
                        <Ligne icone={<UserRound className="size-3.5 text-[#004c68]" />} label="Titulaire" valeur={c.titulaire?.nom ?? null} />
                        <Ligne icone={<Users className="size-3.5 text-muted-foreground" />} label="Remplaçant" valeur={c.backup?.nom ?? null} />
                      </>
                    ) : (
                      <Ligne icone={<Building2 className="size-3.5 text-[#f78304]" />} label="Partenaire" valeur={c.partenaire ? `${c.partenaire.name} (${c.partenaire.code})` : null} />
                    )}
                  </ul>

                  <div className="mt-3"><Jauge c={c} /></div>
                  {c.bascule_at && <p className="mt-2 flex items-center gap-1 text-[10px] text-amber-700 dark:text-amber-300"><ShieldAlert className="size-3" /> Bascule le {c.bascule_at.slice(0, 16)}</p>}

                  {can.manage && (
                    <div className="mt-auto flex gap-2 pt-4">
                      <Button size="sm" variant="outline" className="h-7 flex-1 cursor-pointer text-xs" onClick={() => ouvrir(c)}><Pencil className="size-3.5" /> Affecter</Button>
                      {regional && c.titulaire && c.backup && (
                        <Button size="sm" variant="outline" className="h-7 flex-1 cursor-pointer border-[#f78304]/40 text-xs text-[#c26200] hover:bg-[#f78304]/10 dark:text-[#ff8806]" onClick={() => basculer(c)}>
                          <ArrowLeftRight className="size-3.5" /> Basculer sur backup
                        </Button>
                      )}
                    </div>
                  )}
                </div>
              </article>
            );
          })}
          {visibles.length === 0 && <div className="col-span-full rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground">Aucune zone.</div>}
        </div>
        <p className="text-[11px] text-muted-foreground">
          La charge compte les colis « en cours » et « planifiés » de la zone. « Basculer sur backup » fait du remplaçant le titulaire et lui réaffecte les colis en cours de l’ancien titulaire.
        </p>
      </div>

      <Dialog open={edition !== null} onOpenChange={(o) => !o && setEdition(null)}>
        <DialogContent className="max-w-lg">
          <form onSubmit={soumettre} className="space-y-3">
            <DialogHeader><DialogTitle>Couverture — {edition?.zone.code} {edition?.zone.name}</DialogTitle></DialogHeader>
            <div className="space-y-1.5">
              <Label htmlFor="c-type">Mode de couverture</Label>
              <select id="c-type" className={SELECT} value={f.data.type_couverture} onChange={(e) => f.setData('type_couverture', e.target.value as Type)}>
                <option value="regional_flotte">Flotte régionale (coursiers d’agence)</option>
                <option value="national_partenaire">Réseau national (transporteur partenaire)</option>
              </select>
            </div>
            {f.data.type_couverture === 'regional_flotte' ? (
              <>
                <div className="space-y-1.5">
                  <Label htmlFor="c-cherche">Rechercher un coursier</Label>
                  <Input id="c-cherche" placeholder="Nom ou téléphone…" value={recherche} onChange={(e) => setRecherche(e.target.value)} />
                </div>
                {(['titulaire_user_id', 'backup_user_id'] as const).map((champ) => (
                  <div key={champ} className="space-y-1.5">
                    <Label htmlFor={`c-${champ}`}>{champ === 'titulaire_user_id' ? 'Titulaire' : 'Remplaçant (backup)'}</Label>
                    <select id={`c-${champ}`} className={SELECT} value={f.data[champ]} onChange={(e) => f.setData(champ, e.target.value)}>
                      <option value="">— À affecter —</option>
                      {options(f.data[champ]).map((l) => <option key={l.id} value={l.id}>{l.nom_complet}{l.telephone ? ` · ${l.telephone}` : ''}</option>)}
                    </select>
                    {f.errors[champ] && <p className="text-[11px] text-destructive">{f.errors[champ]}</p>}
                  </div>
                ))}
              </>
            ) : (
              <div className="space-y-1.5">
                <Label htmlFor="c-partenaire">Transporteur partenaire</Label>
                <select id="c-partenaire" className={SELECT} value={f.data.delivery_partner_id} onChange={(e) => f.setData('delivery_partner_id', e.target.value)}>
                  <option value="">— À affecter —</option>
                  {partenaires.map((p) => <option key={p.id} value={p.id}>{p.name} ({p.code})</option>)}
                </select>
              </div>
            )}
            <div className="space-y-1.5">
              <Label htmlFor="c-cap">Capacité (colis en cours acceptables)</Label>
              <Input id="c-cap" type="number" min={1} placeholder="Vide = jauge sans pourcentage" value={f.data.capacite_journaliere} onChange={(e) => f.setData('capacite_journaliere', e.target.value)} />
              {f.errors.capacite_journaliere && <p className="text-[11px] text-destructive">{f.errors.capacite_journaliere}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="c-notes">Notes</Label>
              <Textarea id="c-notes" rows={2} value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" className="cursor-pointer" onClick={() => setEdition(null)}>Annuler</Button>
              <Button type="submit" disabled={f.processing} className="cursor-pointer bg-[#004c68] text-white hover:bg-[#00384d]">Enregistrer</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
