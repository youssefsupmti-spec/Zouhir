import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronsUpDown, Loader2, MapPin, Minus, Package, PackageOpen, Plus, Repeat, Search, User } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface Client { id: number; nom_complet: string; telephone?: string | null; businesses?: { id: number; name: string }[] }
interface Ville { id: number; name_fr: string; name_ar?: string | null; code_zone?: string | null; zone?: string | null }
interface Props {
  clients: Client[];
  villes: Ville[];
  livreurs: { id: number; nom_complet: string; telephone?: string | null }[];
  estAdmin: boolean;
}
interface Estimation { manquant: boolean; type: string; livraison: string | null }

const dh = (v: number) => v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const TYPE_TARIF: Record<string, string> = { client: 'tarif négocié', standard: 'tarif standard', manual: 'saisi', missing: 'aucun tarif' };

/** Étape numérotée du formulaire ; coche verte quand elle est complète. */
function Etape({ n, titre, aide, complete, children }: { n: number; titre: string; aide: string; complete: boolean; children: ReactNode }) {
  return (
    <section>
      <header className="flex items-center gap-3 px-5 pt-5">
        <span className={`inline-flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${complete ? 'bg-emerald-600 text-white' : 'bg-[#004c68]/10 text-[#004c68] dark:bg-[#ff8806]/15 dark:text-[#ff8806]'}`}>
          {complete ? <Check className="size-3.5" /> : n}
        </span>
        <div>
          <h2 className="text-sm font-semibold">{titre}</h2>
          <p className="text-[11px] text-muted-foreground">{aide}</p>
        </div>
      </header>
      <div className="grid gap-4 px-5 pb-5 pt-4 sm:grid-cols-2">{children}</div>
    </section>
  );
}

function Champ({ label, erreur, children, large = false, requis = false }: { label: string; erreur?: string; children: ReactNode; large?: boolean; requis?: boolean }) {
  return (
    <div className={`space-y-1.5 ${large ? 'sm:col-span-2' : ''}`}>
      <Label className="text-xs">{label}{requis && <span className="text-[#ff8806]"> *</span>}</Label>
      {children}
      {erreur && <p className="text-[11px] font-medium text-destructive">{erreur}</p>}
    </div>
  );
}

/** Choix de ville avec recherche (1 350 villes) : saisie libre, 40 propositions au plus. */
function ChoixVille({ villes, valeur, onChange, erreur }: { villes: Ville[]; valeur: string; onChange: (id: string) => void; erreur?: string }) {
  const [ouvert, setOuvert] = useState(false);
  const [q, setQ] = useState('');
  const boite = useRef<HTMLDivElement>(null);
  const choisie = villes.find((v) => String(v.id) === valeur);
  const norm = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const liste = useMemo(() => {
    const t = norm(q.trim());
    return (t === '' ? villes : villes.filter((v) => norm(v.name_fr).includes(t) || (v.name_ar ?? '').includes(q.trim()) || norm(v.code_zone ?? '') === t)).slice(0, 40);
  }, [q, villes]);

  useEffect(() => {
    const fermer = (e: MouseEvent) => { if (boite.current && !boite.current.contains(e.target as Node)) setOuvert(false); };
    document.addEventListener('mousedown', fermer);
    return () => document.removeEventListener('mousedown', fermer);
  }, []);

  return (
    <div ref={boite} className="relative">
      <button type="button" onClick={() => setOuvert((o) => !o)} aria-invalid={!!erreur}
        className="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 text-left text-sm aria-invalid:border-destructive dark:bg-input/30">
        {choisie
          ? <span className="flex items-center gap-2"><MapPin className="size-3.5 text-[#ff8806]" />{choisie.name_fr}{choisie.code_zone && <span className="rounded border border-border px-1 text-[10px] font-semibold text-muted-foreground">{choisie.code_zone}</span>}</span>
          : <span className="text-muted-foreground">Choisir la ville de destination…</span>}
        <ChevronsUpDown className="size-3.5 text-muted-foreground" />
      </button>
      {ouvert && (
        <div className="absolute z-30 mt-1 w-full rounded-md border border-border bg-popover p-1 text-sm shadow-md">
          <div className="relative mb-1">
            <Search className="absolute left-2 top-2.5 size-3.5 text-muted-foreground" />
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Rechercher une ville ou une zone…"
              className="h-9 w-full rounded-sm border-0 bg-muted/50 pl-7 pr-2 text-sm outline-none" />
          </div>
          <ul className="max-h-64 overflow-y-auto">
            {liste.map((v) => (
              <li key={v.id}>
                <button type="button" onClick={() => { onChange(String(v.id)); setOuvert(false); setQ(''); }}
                  className={`flex w-full items-center justify-between rounded-sm px-2 py-1.5 text-left hover:bg-muted ${String(v.id) === valeur ? 'bg-muted font-semibold' : ''}`}>
                  <span>{v.name_fr}{v.name_ar && <span className="ml-2 text-xs text-muted-foreground" dir="rtl">{v.name_ar}</span>}</span>
                  <span className="text-[10px] text-muted-foreground">{v.zone ?? v.code_zone ?? 'sans zone'}</span>
                </button>
              </li>
            ))}
            {liste.length === 0 && <li className="px-2 py-3 text-center text-xs text-muted-foreground">Aucune ville.</li>}
          </ul>
        </div>
      )}
    </div>
  );
}

/** Option à bascule, présentée comme une carte. */
function Bascule({ actif, onChange, icone, titre, aide }: { actif: boolean; onChange: (v: boolean) => void; icone: ReactNode; titre: string; aide: string }) {
  return (
    <button type="button" role="switch" aria-checked={actif} onClick={() => onChange(!actif)}
      className={`flex items-start gap-3 rounded-lg border p-3 text-left ${actif ? 'border-[#004c68] bg-[#004c68]/5 dark:border-[#ff8806] dark:bg-[#ff8806]/10' : 'border-border'}`}>
      <span className={`mt-0.5 ${actif ? 'text-[#004c68] dark:text-[#ff8806]' : 'text-muted-foreground'}`}>{icone}</span>
      <span className="flex-1">
        <span className="block text-sm font-medium">{titre}</span>
        <span className="block text-[11px] text-muted-foreground">{aide}</span>
      </span>
      <span className={`relative mt-1 h-4 w-7 shrink-0 rounded-full transition-colors ${actif ? 'bg-[#004c68] dark:bg-[#ff8806]' : 'bg-muted-foreground/30'}`}>
        <span className={`absolute top-0.5 size-3 rounded-full bg-white transition-all ${actif ? 'left-3.5' : 'left-0.5'}`} />
      </span>
    </button>
  );
}

export default function AjouterColis({ clients, villes, livreurs, estAdmin }: Props) {
  const premier = clients.length === 1 ? clients[0] : null;
  const form = useForm({
    client_id: premier ? String(premier.id) : '',
    business_id: premier?.businesses?.[0] ? String(premier.businesses[0].id) : '',
    destinataire_nom: '',
    destinataire_telephone: '',
    destinataire_ville_id: '',
    destinataire_adresse: '',
    produit: '',
    quantite: 1,
    prix_brut: '',
    frais_livraison_client: '',
    livreur_id: '',
    ouverture_autorisee: false,
    echange: false,
    note: '',
    depuis_page: true,
    apres: 'liste' as 'liste' | 'nouveau',
  });
  const d = form.data;
  const client = clients.find((c) => String(c.id) === d.client_id);
  const enseignes = client?.businesses ?? [];
  const ville = villes.find((v) => String(v.id) === d.destinataire_ville_id);
  const [estimation, setEstimation] = useState<Estimation | null>(null);
  const [calcul, setCalcul] = useState(false);

  // Frais prévus : tarif négocié du marchand, sinon tarif standard de la ville.
  useEffect(() => {
    if (!d.destinataire_ville_id) { setEstimation(null); return; }
    const ctrl = new AbortController();
    setCalcul(true);
    fetch(`/colis/estimation?ville_id=${d.destinataire_ville_id}${d.client_id ? `&client_id=${d.client_id}` : ''}`, { headers: { Accept: 'application/json' }, signal: ctrl.signal })
      .then((r) => (r.ok ? r.json() : null)).then((e) => setEstimation(e)).catch(() => {}).finally(() => setCalcul(false));
    return () => ctrl.abort();
  }, [d.destinataire_ville_id, d.client_id]);

  const crbt = Number(String(d.prix_brut).replace(',', '.')) || 0;
  const frais = d.frais_livraison_client !== '' ? Number(String(d.frais_livraison_client).replace(',', '.')) || 0 : Number(estimation?.livraison ?? 0);
  const complet = {
    expediteur: d.client_id !== '',
    destinataire: d.destinataire_nom.trim() !== '' && d.destinataire_telephone.trim().length >= 9 && d.destinataire_ville_id !== '',
    colis: d.prix_brut !== '',
  };

  const enregistrer = (apres: 'liste' | 'nouveau') => (e?: FormEvent) => {
    e?.preventDefault();
    form.transform((x) => ({ ...x, apres }));
    form.post('/colis', {
      preserveScroll: true,
      onSuccess: () => { if (apres === 'nouveau') form.reset('destinataire_nom', 'destinataire_telephone', 'destinataire_ville_id', 'destinataire_adresse', 'produit', 'quantite', 'prix_brut', 'frais_livraison_client', 'note', 'ouverture_autorisee', 'echange'); },
    });
  };

  return (
    <AuthenticatedLayout title="Ajouter un colis">
      <Head title="Ajouter un colis - OUAISS EXPRESS" />

      <form onSubmit={enregistrer('liste')} className="space-y-6 pb-24">
        <div>
          <Link href="/colis" className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"><ArrowLeft className="size-3.5" /> Colis</Link>
          <h1 className="mt-1 text-xl font-bold tracking-tight">Ajouter un colis</h1>
          <p className="text-xs text-muted-foreground">Expéditeur, destinataire, colis et encaissement, puis options.</p>
        </div>

        <div className="mx-auto max-w-4xl">
          <div className="divide-y divide-border rounded-lg border border-border bg-card">
            <Etape n={1} titre="Expéditeur" aide="Le marchand qui envoie le colis" complete={complet.expediteur}>
              {estAdmin ? (
                <Champ label="Marchand" erreur={form.errors.client_id} requis>
                  <select value={d.client_id} onChange={(e) => { const c = clients.find((x) => String(x.id) === e.target.value); form.setData((x) => ({ ...x, client_id: e.target.value, business_id: c?.businesses?.[0] ? String(c.businesses[0].id) : '' })); }}
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30">
                    <option value="">Choisir le marchand…</option>
                    {clients.map((c) => <option key={c.id} value={c.id}>{c.businesses?.[0]?.name ? `${c.businesses[0].name} — ${c.nom_complet}` : c.nom_complet}</option>)}
                  </select>
                </Champ>
              ) : (
                <Champ label="Marchand"><div className="flex h-9 items-center gap-2 rounded-md bg-muted/50 px-3 text-sm"><User className="size-3.5" />{client?.nom_complet}</div></Champ>
              )}
              <Champ label="Business" erreur={form.errors.business_id}>
                <select value={d.business_id} onChange={(e) => form.setData('business_id', e.target.value)} disabled={enseignes.length === 0}
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-60 dark:bg-input/30">
                  {enseignes.length === 0 && <option value="">Aucun business</option>}
                  {enseignes.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                </select>
              </Champ>
            </Etape>

            <Etape n={2} titre="Destinataire" aide="À qui livrer, et où" complete={complet.destinataire}>
              <Champ label="Nom du destinataire" erreur={form.errors.destinataire_nom} requis>
                <Input value={d.destinataire_nom} onChange={(e) => form.setData('destinataire_nom', e.target.value)} placeholder="Ex. Salma Bennani" autoComplete="off" />
              </Champ>
              <Champ label="Téléphone" erreur={form.errors.destinataire_telephone} requis>
                <Input value={d.destinataire_telephone} onChange={(e) => form.setData('destinataire_telephone', e.target.value.replace(/[^\d+ ]/g, ''))} placeholder="06 00 00 00 00" inputMode="tel" className="font-mono" />
              </Champ>
              <Champ label="Ville de destination" erreur={form.errors.destinataire_ville_id} large requis>
                <ChoixVille villes={villes} valeur={d.destinataire_ville_id} onChange={(id) => form.setData('destinataire_ville_id', id)} erreur={form.errors.destinataire_ville_id} />
              </Champ>
              <Champ label="Adresse" erreur={form.errors.destinataire_adresse} large>
                <Textarea rows={2} value={d.destinataire_adresse} onChange={(e) => form.setData('destinataire_adresse', e.target.value)} placeholder="Quartier, rue, numéro, repère…" />
              </Champ>
            </Etape>

            <Etape n={3} titre="Colis et encaissement" aide="Contenu et montant à encaisser auprès du destinataire" complete={complet.colis}>
              <Champ label="Contenu" erreur={form.errors.produit} large>
                <Input value={d.produit} onChange={(e) => form.setData('produit', e.target.value)} placeholder="Ex. Robe en lin taille M" />
              </Champ>
              <Champ label="Quantité" erreur={form.errors.quantite}>
                <div className="flex h-9 items-center rounded-md border border-input">
                  <button type="button" aria-label="Moins" className="px-3 text-muted-foreground hover:text-foreground" onClick={() => form.setData('quantite', Math.max(1, d.quantite - 1))}><Minus className="size-3.5" /></button>
                  <input value={d.quantite} onChange={(e) => form.setData('quantite', Math.max(1, Number(e.target.value.replace(/\D/g, '')) || 1))} className="w-full bg-transparent text-center text-sm tabular-nums outline-none" />
                  <button type="button" aria-label="Plus" className="px-3 text-muted-foreground hover:text-foreground" onClick={() => form.setData('quantite', d.quantite + 1)}><Plus className="size-3.5" /></button>
                </div>
              </Champ>
              <Champ label="Montant à encaisser (CRBT)" erreur={form.errors.prix_brut} requis>
                <div className="relative">
                  <Input value={d.prix_brut} onChange={(e) => form.setData('prix_brut', e.target.value.replace(/[^\d.,]/g, ''))} placeholder="0,00" inputMode="decimal" className="pr-10 text-right font-semibold tabular-nums" />
                  <span className="absolute right-3 top-2 text-xs text-muted-foreground">DH</span>
                </div>
              </Champ>
              {estAdmin && (
                <>
                  <Champ label="Frais de livraison (laisser vide = tarif)" erreur={form.errors.frais_livraison_client}>
                    <Input value={d.frais_livraison_client} onChange={(e) => form.setData('frais_livraison_client', e.target.value.replace(/[^\d.,]/g, ''))}
                      placeholder={estimation?.livraison ? `${estimation.livraison} (${TYPE_TARIF[estimation.type] ?? estimation.type})` : 'Selon le tarif'} inputMode="decimal" className="text-right tabular-nums" />
                  </Champ>
                  <Champ label="Livreur (facultatif)" erreur={form.errors.livreur_id}>
                    <select value={d.livreur_id} onChange={(e) => form.setData('livreur_id', e.target.value)} className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30">
                      <option value="">Non assigné</option>
                      {livreurs.map((l) => <option key={l.id} value={l.id}>{l.nom_complet}</option>)}
                    </select>
                  </Champ>
                </>
              )}
            </Etape>

            <Etape n={4} titre="Options et consignes" aide="Ce que le livreur doit savoir" complete={d.ouverture_autorisee || d.echange || d.note.trim() !== ''}>
              <Bascule actif={d.ouverture_autorisee} onChange={(v) => form.setData('ouverture_autorisee', v)} icone={<PackageOpen className="size-4" />} titre="Ouverture autorisée" aide="Le destinataire peut ouvrir avant de payer" />
              <Bascule actif={d.echange} onChange={(v) => form.setData('echange', v)} icone={<Repeat className="size-4" />} titre="Échange" aide="Un article est à reprendre au destinataire" />
              <Champ label="Note pour le livreur" erreur={form.errors.note} large>
                <Textarea rows={2} maxLength={2000} value={d.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="Ex. appeler avant 18 h, livrer au bureau…" />
              </Champ>
            </Etape>
          </div>

        </div>

        {/* Barre d'actions fixe */}
        <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-background/95 backdrop-blur md:left-[var(--sidebar-width,16rem)]">
          <div className="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
            <span className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
              <span className="inline-flex items-center gap-1.5"><Package className="size-4" /> {[complet.expediteur, complet.destinataire, complet.colis].filter(Boolean).length}/3 étapes obligatoires</span>
              <span>CRBT <b className="tabular-nums text-foreground">{dh(crbt)} DH</b></span>
              <span>
                Frais {calcul ? <Loader2 className="inline size-3 animate-spin" /> : estimation?.manquant && d.frais_livraison_client === ''
                  ? <b className="text-[#c26200]">à définir</b>
                  : <b className="tabular-nums text-foreground">{dh(frais)} DH</b>}
                {estimation && d.frais_livraison_client === '' && !estimation.manquant && <span> ({TYPE_TARIF[estimation.type] ?? estimation.type})</span>}
              </span>
              <span>Net <b className="tabular-nums text-emerald-700 dark:text-emerald-400">{dh(crbt - frais)} DH</b></span>
            </span>
            <div className="flex gap-2">
              <Button type="button" variant="ghost" size="sm" render={<Link href="/colis" />}>Annuler</Button>
              <Button type="button" variant="outline" size="sm" disabled={form.processing} onClick={() => enregistrer('nouveau')()}>Enregistrer et ajouter un autre</Button>
              <Button type="submit" size="sm" disabled={form.processing} className="bg-[#004c68] text-white hover:bg-[#00384d]">
                {form.processing && <Loader2 className="size-3.5 animate-spin" />} Enregistrer le colis
              </Button>
            </div>
          </div>
        </div>
      </form>
    </AuthenticatedLayout>
  );
}
