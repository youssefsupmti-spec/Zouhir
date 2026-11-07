import { useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, CheckCircle2, KeyRound, MapPin, Pencil, Phone, Plus, RotateCcw, Trash2, Truck, XCircle } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface Partenaire {
  id: number; code: string; name: string; type_partenaire: string; ice: string | null; telephone: string | null; email: string | null;
  adresse: string | null; ville_id: number | null; ville: string | null; notes: string | null; is_active: boolean;
  commission_defaut: number | null; delai_transit_moyen_h: number | null; compte: string | null;
  zones: { code: string; name: string }[];
  api: { jeton: boolean; secret: boolean; derniere_synchro: string | null };
  bons: number; colis: number; livres: number; crbt: number; supprime: boolean;
}

interface Props {
  partenaires: Partenaire[];
  types: Record<string, string>;
  villes?: { id: number; name_fr: string }[];
  compteurs: { actifs: number; inactifs: number; corbeille: number };
  filters: { corbeille: boolean };
  can: { manage: boolean };
}

const VIDE = {
  name: '', code: '', type_partenaire: 'transporteur_ligne', ice: '', telephone: '', email: '', adresse: '',
  ville_id: '' as string | number, commission_defaut: '' as string | number, delai_transit_moyen_h: '' as string | number, notes: '', is_active: true,
};

const SELECT = 'flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs';
const dh = (n: number) => `${n.toLocaleString('fr-FR', { maximumFractionDigits: 2 })} DH`;

function EtatApi({ api }: { api: Partenaire['api'] }) {
  const pret = api.jeton && api.secret;
  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ${pret ? 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'}`}>
      {pret ? <CheckCircle2 className="size-3" /> : <XCircle className="size-3" />}
      {pret ? 'API connectée' : api.jeton ? 'Secret manquant' : 'API non configurée'}
    </span>
  );
}

export default function PartenairesIndex({ partenaires, types, villes = [], compteurs, filters, can }: Props) {
  const [edition, setEdition] = useState<Partenaire | 'nouveau' | null>(null);
  const f = useForm({ ...VIDE });

  const ouvrir = (p: Partenaire | 'nouveau') => {
    f.clearErrors();
    f.setData(p === 'nouveau' ? { ...VIDE } : {
      name: p.name, code: p.code, type_partenaire: p.type_partenaire, ice: p.ice ?? '', telephone: p.telephone ?? '', email: p.email ?? '',
      adresse: p.adresse ?? '', ville_id: p.ville_id ?? '', commission_defaut: p.commission_defaut ?? '', delai_transit_moyen_h: p.delai_transit_moyen_h ?? '',
      notes: p.notes ?? '', is_active: p.is_active,
    });
    setEdition(p);
  };

  const soumettre = (e: FormEvent) => {
    e.preventDefault();
    const fin = { preserveScroll: true, onSuccess: () => setEdition(null) };
    if (edition === 'nouveau') f.post('/partenaires', fin);
    else if (edition) f.put(`/partenaires/${edition.id}`, fin);
  };

  const champ = (id: keyof typeof VIDE, label: string, props: Record<string, unknown> = {}) => (
    <div className="space-y-1.5">
      <Label htmlFor={`p-${id}`}>{label}</Label>
      <Input id={`p-${id}`} value={String(f.data[id] ?? '')} onChange={(e) => f.setData(id, e.target.value as never)} {...props} />
      {f.errors[id] && <p className="text-[11px] text-destructive">{f.errors[id]}</p>}
    </div>
  );

  return (
    <AuthenticatedLayout title="Transporteurs partenaires">
      <Head title="Transporteurs partenaires - OUAISS EXPRESS" />
      <div className="space-y-6">
        <PageHeader
          title="Transporteurs partenaires"
          description="Réseau national : transporteurs de ligne, agences consœurs et plateformes qui livrent hors de la zone régionale."
          icon={Truck}
          actions={can.manage && !filters.corbeille ? (
            <Button onClick={() => ouvrir('nouveau')} className="cursor-pointer bg-[#004c68] text-xs font-semibold text-white hover:bg-[#00384d]">
              <Plus className="mr-1.5 size-3.5" /> Nouveau partenaire
            </Button>
          ) : undefined}
        />

        <TarifsTabs
          label="Partenaires"
          value={filters.corbeille ? 'corbeille' : 'annuaire'}
          onValueChange={(v) => router.get('/partenaires', v === 'corbeille' ? { corbeille: 1 } : {}, { preserveScroll: true })}
          onglets={[
            { value: 'annuaire', label: 'Annuaire', count: compteurs.actifs + compteurs.inactifs, icon: <Building2 className="size-3.5" /> },
            { value: 'corbeille', label: 'Corbeille', count: compteurs.corbeille, icon: <Trash2 className="size-3.5" />, tone: 'danger' },
          ]}
        />

        {partenaires.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border bg-card p-12 text-center text-sm text-muted-foreground">
            {filters.corbeille ? 'La corbeille est vide.' : 'Aucun transporteur partenaire. Ajoutez le premier pour ouvrir le réseau national.'}
          </div>
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {partenaires.map((p) => (
              <article key={p.id} className="flex flex-col rounded-xl border border-border bg-card p-5 shadow-2xs">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="font-mono text-[11px] text-muted-foreground">{p.code} · {types[p.type_partenaire] ?? p.type_partenaire}</p>
                    <h3 className="truncate text-base font-bold">{p.name}</h3>
                  </div>
                  <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ${p.is_active ? 'bg-[#f78304]/15 text-[#c26200] dark:text-[#ff8806]' : 'bg-muted text-muted-foreground'}`}>
                    {p.is_active ? 'Actif' : 'Inactif'}
                  </span>
                </div>
                <ul className="mt-3 space-y-1 text-xs text-muted-foreground">
                  <li className="flex items-center gap-1.5"><Phone className="size-3.5" /> {p.telephone ?? '—'}{p.email ? ` · ${p.email}` : ''}</li>
                  <li className="flex items-center gap-1.5"><MapPin className="size-3.5" /> {p.ville ?? 'Ville non renseignée'}{p.adresse ? ` — ${p.adresse}` : ''}</li>
                  <li>ICE : <span className="font-mono">{p.ice ?? '—'}</span>{p.compte ? ` · compte ${p.compte}` : ''}</li>
                </ul>
                <div className="mt-3 flex flex-wrap gap-1">
                  {p.zones.length === 0
                    ? <span className="text-[11px] text-muted-foreground">Aucune zone desservie</span>
                    : p.zones.map((z) => <span key={z.code} title={z.name} className="rounded bg-[#f78304]/10 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-[#c26200] dark:text-[#ff8806]">{z.code}</span>)}
                </div>
                <div className="mt-4 grid grid-cols-3 gap-2 text-center">
                  <div className="rounded-lg bg-muted/50 p-2"><p className="text-base font-bold tabular-nums">{p.bons}</p><p className="text-[10px] text-muted-foreground">bons</p></div>
                  <div className="rounded-lg bg-muted/50 p-2"><p className="text-base font-bold tabular-nums">{p.livres}/{p.colis}</p><p className="text-[10px] text-muted-foreground">livrés</p></div>
                  <div className="rounded-lg bg-muted/50 p-2"><p className="truncate text-base font-bold tabular-nums">{dh(p.crbt)}</p><p className="text-[10px] text-muted-foreground">CRBT livré</p></div>
                </div>
                <div className="mt-4 flex items-center justify-between gap-2">
                  <EtatApi api={p.api} />
                  {p.delai_transit_moyen_h && <span className="text-[11px] text-muted-foreground">Transit ≈ {p.delai_transit_moyen_h} h</span>}
                </div>
                {can.manage && (
                  <div className="mt-4 flex gap-2 border-t border-border pt-3">
                    {p.supprime ? (
                      <Button size="sm" variant="outline" className="h-7 flex-1 cursor-pointer text-xs" onClick={() => router.post(`/partenaires/${p.id}/restaurer`, {}, { preserveScroll: true })}>
                        <RotateCcw className="size-3.5" /> Restaurer
                      </Button>
                    ) : (
                      <>
                        <Button size="sm" variant="outline" className="h-7 flex-1 cursor-pointer text-xs" onClick={() => ouvrir(p)}><Pencil className="size-3.5" /> Modifier</Button>
                        <Button size="sm" variant="outline" className="h-7 flex-1 cursor-pointer text-xs" render={<Link href={`/partenaires/api?partenaire=${p.id}`} />}><KeyRound className="size-3.5" /> API</Button>
                        <Button size="icon" variant="ghost" className="size-7 cursor-pointer" aria-label="Mettre en corbeille" onClick={() => router.delete(`/partenaires/${p.id}`, { preserveScroll: true })}>
                          <Trash2 className="size-3.5 text-destructive" />
                        </Button>
                      </>
                    )}
                  </div>
                )}
              </article>
            ))}
          </div>
        )}
      </div>

      <Dialog open={edition !== null} onOpenChange={(o) => !o && setEdition(null)}>
        <DialogContent className="max-w-2xl">
          <form onSubmit={soumettre} className="space-y-4">
            <DialogHeader><DialogTitle>{edition === 'nouveau' ? 'Nouveau transporteur partenaire' : `Partenaire ${edition ? edition.code : ''}`}</DialogTitle></DialogHeader>
            <div className="grid gap-3 sm:grid-cols-2">
              {champ('name', 'Raison sociale')}
              {edition === 'nouveau' ? champ('code', 'Code (facultatif, déduit du nom)', { placeholder: 'ex. CTMX' }) : (
                <div className="space-y-1.5"><Label>Code</Label><Input value={f.data.code} disabled /></div>
              )}
              <div className="space-y-1.5">
                <Label htmlFor="p-type">Type</Label>
                <select id="p-type" className={SELECT} value={f.data.type_partenaire} onChange={(e) => f.setData('type_partenaire', e.target.value)}>
                  {Object.entries(types).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>
              {champ('ice', 'ICE')}
              {champ('telephone', 'Téléphone')}
              {champ('email', 'E-mail', { type: 'email' })}
              <div className="space-y-1.5">
                <Label htmlFor="p-ville">Ville du siège</Label>
                <select id="p-ville" className={SELECT} value={f.data.ville_id} onChange={(e) => f.setData('ville_id', e.target.value)}>
                  <option value="">—</option>
                  {villes.map((v) => <option key={v.id} value={v.id}>{v.name_fr}</option>)}
                </select>
              </div>
              {champ('adresse', 'Adresse')}
              {champ('commission_defaut', 'Tarif de transit par défaut (DH)', { type: 'number', step: '0.01', min: 0 })}
              {champ('delai_transit_moyen_h', 'Délai de transit moyen (heures)', { type: 'number', min: 1 })}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-notes">Notes</Label>
              <Textarea id="p-notes" rows={2} value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
            </div>
            <label className="flex cursor-pointer items-center gap-2 text-xs">
              <input type="checkbox" className="accent-[#004c68]" checked={f.data.is_active} onChange={(e) => f.setData('is_active', e.target.checked)} />
              Partenaire actif (peut recevoir des bons et appeler l’API)
            </label>
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
