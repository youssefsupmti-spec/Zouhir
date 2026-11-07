import { useState, type FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Bike, Building2, Loader2, MapPin, Network, Pencil, Phone, Star } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { PageToolbar } from '@/components/common/PageToolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { dateBon } from '@/components/bons/partage';

type Onglet = 'agences' | 'livreurs';

interface AgenceL { id: number; code: string; nom: string; ville: string | null; ville_id: number | null; telephone: string | null; adresse: string | null; est_principale: boolean; actif: boolean; livreurs: number; colis: number }
interface MembreL { id: number; agence: string | null; role: string; actif: boolean; user_id: number; nom: string | null; telephone: string | null; compte_archive: boolean; bons: number; dernier_bon: string | null; colis: number }

interface Props {
  onglet: Onglet;
  agences: AgenceL[];
  membres: MembreL[];
  villes: { id: number; name_fr: string }[];
  filters: { search: string };
  can: { manage: boolean };
}

const ROLES: Record<string, string> = { livreur: 'Livreur', ramasseur: 'Ramasseur', agent: 'Agent', suivi: 'Suivi', manager: 'Manager' };

function Etat({ actif }: { actif: boolean }) {
  return actif
    ? <span className="rounded-full bg-emerald-600/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">Actif</span>
    : <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">Inactif</span>;
}

export default function ReseauIndex({ onglet, agences, membres, villes, filters, can }: Props) {
  const [agence, setAgence] = useState<AgenceL | null>(null);
  const [membre, setMembre] = useState<MembreL | null>(null);
  const fa = useForm({ nom: '', ville_id: '' as string | number, telephone: '', adresse: '', actif: true });
  const fm = useForm({ role: 'livreur', actif: true });
  const params = { onglet, ...filters };
  const actifs = membres.filter((m) => m.actif).length;

  const soumettre = (e: FormEvent, f: typeof fa | typeof fm, url: string, fermer: () => void) => {
    e.preventDefault();
    f.put(url, { preserveScroll: true, onSuccess: fermer });
  };

  return (
    <AuthenticatedLayout title="Réseau">
      <Head title="Réseau - OUAISS EXPRESS" />
      <div className="space-y-6">
        <PageHeader title="Réseau" description="Agences OUAISS EXPRESS et livreurs rattachés. Les partenaires nationaux ont leur propre section." icon={Network} />

        <TarifsTabs
          label="Réseau"
          value={onglet}
          onValueChange={(v) => router.get('/reseau', { onglet: v }, { preserveScroll: true })}
          onglets={[
            { value: 'agences', label: 'Agences', count: agences.length, icon: <Building2 className="size-3.5" /> },
            { value: 'livreurs', label: 'Livreurs rattachés', count: membres.length, icon: <Bike className="size-3.5" /> },
          ]}
        />

        {onglet === 'agences' && (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {agences.map((a) => (
              <article key={a.id} className="rounded-xl border border-border bg-card p-5 shadow-2xs">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="font-mono text-[11px] text-muted-foreground">{a.code}</p>
                    <h3 className="flex items-center gap-1.5 text-base font-bold">{a.nom}{a.est_principale && <Star className="size-3.5 fill-[#ff8806] text-[#ff8806]" />}</h3>
                  </div>
                  <Etat actif={a.actif} />
                </div>
                <ul className="mt-3 space-y-1 text-xs text-muted-foreground">
                  <li className="flex items-center gap-1.5"><MapPin className="size-3.5" /> {a.ville ?? 'Ville non renseignée'}{a.adresse ? ` — ${a.adresse}` : ''}</li>
                  <li className="flex items-center gap-1.5"><Phone className="size-3.5" /> {a.telephone ?? '—'}</li>
                </ul>
                <div className="mt-4 grid grid-cols-2 gap-2 text-center">
                  <div className="rounded-lg bg-muted/50 p-2"><p className="text-lg font-bold tabular-nums">{a.livreurs}</p><p className="text-[10px] text-muted-foreground">livreurs</p></div>
                  <div className="rounded-lg bg-muted/50 p-2"><p className="text-lg font-bold tabular-nums">{a.colis.toLocaleString('fr-FR')}</p><p className="text-[10px] text-muted-foreground">colis</p></div>
                </div>
                {can.manage && (
                  <Button size="sm" variant="outline" className="mt-4 h-7 w-full text-xs" onClick={() => { fa.setData({ nom: a.nom, ville_id: a.ville_id ?? '', telephone: a.telephone ?? '', adresse: a.adresse ?? '', actif: a.actif }); setAgence(a); }}>
                    <Pencil className="size-3.5" /> Modifier
                  </Button>
                )}
              </article>
            ))}
          </div>
        )}

        {onglet !== 'agences' && <PageToolbar searchPlaceholder={onglet === 'livreurs' ? 'Nom ou téléphone du livreur…' : 'Raison sociale ou code…'} initialSearch={filters.search} currentParams={params} />}

        {onglet === 'livreurs' && (
          <>
            <p className="text-xs text-muted-foreground">{actifs} actif(s) sur {membres.length}. Le nombre de bons et la date du dernier bon viennent des bons de livraison repris.</p>
            <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
              <Table className="min-w-[760px] text-xs">
                <TableHeader>
                  <TableRow className="hover:bg-transparent">
                    <TableHead className="pl-4">Livreur</TableHead><TableHead>Agence</TableHead><TableHead>Rôle</TableHead>
                    <TableHead className="text-right">Bons</TableHead><TableHead className="text-right">Colis</TableHead><TableHead>Dernier bon</TableHead><TableHead>État</TableHead><TableHead className="pr-4" />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {membres.map((m) => (
                    <TableRow key={m.id}>
                      <TableCell className="pl-4"><span className="font-semibold">{m.nom ?? `#${m.user_id}`}</span><div className="text-[10px] text-muted-foreground">{m.telephone}{m.compte_archive ? ' · compte archivé' : ''}</div></TableCell>
                      <TableCell>{m.agence}</TableCell>
                      <TableCell>{ROLES[m.role] ?? m.role}</TableCell>
                      <TableCell className="text-right tabular-nums">{m.bons}</TableCell>
                      <TableCell className="text-right tabular-nums">{m.colis.toLocaleString('fr-FR')}</TableCell>
                      <TableCell className="text-muted-foreground">{dateBon(m.dernier_bon)}</TableCell>
                      <TableCell><Etat actif={m.actif} /></TableCell>
                      <TableCell className="pr-4 text-right">
                        {can.manage && <Button size="icon" variant="ghost" className="size-7" aria-label="Modifier" onClick={() => { fm.setData({ role: m.role, actif: m.actif }); setMembre(m); }}><Pencil className="size-3.5 text-[#ff8806]" /></Button>}
                      </TableCell>
                    </TableRow>
                  ))}
                  {membres.length === 0 && <TableRow><TableCell colSpan={8} className="py-12 text-center text-muted-foreground">Aucun livreur rattaché (lancer la reprise « equipes »).</TableCell></TableRow>}
                </TableBody>
              </Table>
            </div>
          </>
        )}
      </div>

      <Dialog open={agence !== null} onOpenChange={(o) => !o && setAgence(null)}>
        <DialogContent className="max-w-md">
          <form onSubmit={(e) => soumettre(e, fa, `/reseau/agences/${agence?.id}`, () => setAgence(null))} className="space-y-3">
            <DialogHeader><DialogTitle>Agence {agence?.code}</DialogTitle></DialogHeader>
            <div className="space-y-1.5"><Label htmlFor="a-nom">Nom</Label><Input id="a-nom" value={fa.data.nom} onChange={(e) => fa.setData('nom', e.target.value)} />{fa.errors.nom && <p className="text-[11px] text-destructive">{fa.errors.nom}</p>}</div>
            <div className="space-y-1.5">
              <Label htmlFor="a-ville">Ville</Label>
              <select id="a-ville" value={fa.data.ville_id} onChange={(e) => fa.setData('ville_id', e.target.value)} className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs">
                <option value="">—</option>
                {villes.map((v) => <option key={v.id} value={v.id}>{v.name_fr}</option>)}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div className="space-y-1.5"><Label htmlFor="a-tel">Téléphone</Label><Input id="a-tel" value={fa.data.telephone} onChange={(e) => fa.setData('telephone', e.target.value)} /></div>
              <div className="space-y-1.5"><Label htmlFor="a-adr">Adresse</Label><Input id="a-adr" value={fa.data.adresse} onChange={(e) => fa.setData('adresse', e.target.value)} /></div>
            </div>
            <label className="flex items-center gap-2 text-xs"><input type="checkbox" checked={fa.data.actif} onChange={(e) => fa.setData('actif', e.target.checked)} className="size-4" /> Agence active</label>
            <DialogFooter><Button type="submit" size="sm" disabled={fa.processing} className="bg-[#004c68] text-white">{fa.processing && <Loader2 className="size-3.5 animate-spin" />} Enregistrer</Button></DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={membre !== null} onOpenChange={(o) => !o && setMembre(null)}>
        <DialogContent className="max-w-sm">
          <form onSubmit={(e) => soumettre(e, fm, `/reseau/membres/${membre?.id}`, () => setMembre(null))} className="space-y-3">
            <DialogHeader><DialogTitle>{membre?.nom}</DialogTitle></DialogHeader>
            <div className="grid grid-cols-3 gap-2">
              {Object.entries(ROLES).map(([r, l]) => (
                <button key={r} type="button" onClick={() => fm.setData('role', r)} className={`rounded-md border px-2 py-1.5 text-xs ${fm.data.role === r ? 'border-[#004c68] bg-[#004c68]/10 text-[#004c68] dark:border-[#ff8806] dark:text-[#ff8806]' : 'border-border text-muted-foreground'}`}>{l}</button>
              ))}
            </div>
            <label className="flex items-center gap-2 text-xs"><input type="checkbox" checked={fm.data.actif} onChange={(e) => fm.setData('actif', e.target.checked)} className="size-4" /> Rattachement actif</label>
            <DialogFooter><Button type="submit" size="sm" disabled={fm.processing} className="bg-[#004c68] text-white">Enregistrer</Button></DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

    </AuthenticatedLayout>
  );
}
