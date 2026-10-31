import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Loader2, Search } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type Referentiel, erreurMontant, jsonFetch, normaliserMontant } from './types';

interface Props {
  open: boolean;
  /** Client présélectionné (bouton « Ajouter des villes » d'un client). */
  clientId: number | null;
  onClose: () => void;
  onSaved: () => void;
}

interface Apercu {
  total: number;
  compte: { nouveau: number; actif: number; supprime: number };
}

const AFFICHAGE_MAX = 300;

/**
 * Négociation de nouveaux tarifs : client → villes (ou zone entière) → montants →
 * aperçu des existants (nouveau / actif / supprimé) → décision explicite → confirmation.
 */
export function AjouterTarifsDialog({ open, clientId, onClose, onSaved }: Props) {
  const [ref, setRef] = useState<Referentiel | null>(null);
  const [refError, setRefError] = useState<string | null>(null);

  const [client, setClient] = useState<number | null>(clientId);
  const [rechercheClient, setRechercheClient] = useState('');
  const [mode, setMode] = useState<'villes' | 'zone'>('villes');
  const [zone, setZone] = useState<number | null>(null);
  const [rechercheVille, setRechercheVille] = useState('');
  const [villes, setVilles] = useState<Set<number>>(new Set());
  const [montants, setMontants] = useState({ livraison: '', refus: '0', retour: '0' });
  const [existants, setExistants] = useState<'ignorer' | 'mettre_a_jour'>('ignorer');
  const [restaurer, setRestaurer] = useState(true);

  const [etape, setEtape] = useState<'saisie' | 'apercu'>('saisie');
  const [apercu, setApercu] = useState<Apercu | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setClient(clientId);
    setEtape('saisie');
    setApercu(null);
    setError(null);
    setVilles(new Set());
    setMontants({ livraison: '', refus: '0', retour: '0' });
    setExistants('ignorer');
    setRestaurer(true);
    if (ref === null) {
      jsonFetch<Referentiel>('/tarifs-clients/referentiel').then(setRef).catch((e: Error) => setRefError(e.message));
    }
  }, [open, clientId]);

  const clientsFiltres = useMemo(() => {
    const q = rechercheClient.trim().toLowerCase();
    const liste = ref?.clients ?? [];
    return q === '' ? liste : liste.filter((c) => `${c.nom} ${c.email ?? ''} ${c.enseignes.join(' ')}`.toLowerCase().includes(q));
  }, [ref, rechercheClient]);

  const villesFiltrees = useMemo(() => {
    const q = rechercheVille.trim().toLowerCase();
    return (ref?.villes ?? []).filter((v) => (zone === null || v.zone_id === zone) && (q === '' || v.nom.toLowerCase().includes(q)));
  }, [ref, zone, rechercheVille]);

  const villesDeZone = useMemo(() => (zone === null ? 0 : (ref?.villes ?? []).filter((v) => v.zone_id === zone).length), [ref, zone]);
  const nbCible = mode === 'zone' ? villesDeZone : villes.size;
  const clientChoisi = ref?.clients.find((c) => c.id === client) ?? null;
  const erreurs = {
    livraison: erreurMontant(montants.livraison),
    refus: erreurMontant(montants.refus),
    retour: erreurMontant(montants.retour),
  };
  const saisieValide = client !== null && nbCible > 0 && !erreurs.livraison && !erreurs.refus && !erreurs.retour;

  const cible = () => ({
    client_id: client,
    mode,
    zone_id: mode === 'zone' ? zone : null,
    ville_ids: mode === 'villes' ? [...villes] : [],
  });

  const ouvrirApercu = async () => {
    if (!saisieValide) return;
    setBusy(true);
    setError(null);
    try {
      setApercu(await jsonFetch<Apercu>('/tarifs-clients/apercu', cible()));
      setEtape('apercu');
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const confirmer = () => {
    router.post('/tarifs-clients', {
      ...cible(),
      livraison: normaliserMontant(montants.livraison),
      refus: normaliserMontant(montants.refus),
      retour: normaliserMontant(montants.retour),
      existants,
      restaurer,
    }, {
      preserveScroll: true,
      onStart: () => setBusy(true),
      onFinish: () => setBusy(false),
      onError: (errors) => {
        setError(Object.values(errors)[0] ?? 'Enregistrement refusé.');
        setEtape('saisie');
      },
      onSuccess: () => {
        onSaved();
        onClose();
      },
    });
  };

  const basculerVille = (id: number, coche: boolean) =>
    setVilles((prev) => {
      const next = new Set(prev);
      coche ? next.add(id) : next.delete(id);
      return next;
    });

  const ecritures = apercu && {
    crees: apercu.compte.nouveau,
    maj: existants === 'mettre_a_jour' ? apercu.compte.actif : 0,
    restaurees: restaurer ? apercu.compte.supprime : 0,
    ignorees: (existants === 'ignorer' ? apercu.compte.actif : 0) + (restaurer ? 0 : apercu.compte.supprime),
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && !busy && onClose()}>
      <DialogContent className="max-h-[92vh] max-w-2xl gap-0 overflow-hidden rounded-lg p-0">
        <DialogHeader className="border-b border-border px-5 py-4">
          <DialogTitle className="text-base font-bold">Négocier de nouveaux tarifs</DialogTitle>
          <DialogDescription className="text-xs">
            Un tarif négocié remplace le tarif standard de la ville pour ce client, même à 0.00 MAD.
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[68vh] overflow-y-auto px-5 py-4">
          {refError && <p role="alert" className="text-xs text-destructive">{refError}</p>}
          {!ref && !refError && (
            <p className="flex items-center gap-2 text-xs text-muted-foreground"><Loader2 className="size-4 animate-spin" /> Chargement des référentiels…</p>
          )}

          {ref && etape === 'saisie' && (
            <div className="space-y-5">
              {/* 1. Client */}
              <section className="space-y-1.5">
                <Label className="text-xs font-semibold">1. Client marchand</Label>
                {clientChoisi ? (
                  <div className="flex items-center justify-between rounded-md border border-[#004c68]/30 bg-[#004c68]/5 px-3 py-2 text-xs">
                    <span><strong>{clientChoisi.nom}</strong>{clientChoisi.enseignes.length > 0 && <span className="text-muted-foreground"> · {clientChoisi.enseignes.join(', ')}</span>}</span>
                    {clientId === null && <button type="button" className="text-[11px] underline" onClick={() => setClient(null)}>Changer</button>}
                  </div>
                ) : (
                  <>
                    <div className="relative">
                      <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                      <Input value={rechercheClient} onChange={(e) => setRechercheClient(e.target.value)} placeholder="Nom, email ou enseigne…" className="h-8 pl-8 text-xs" />
                    </div>
                    <ul className="max-h-36 divide-y divide-border overflow-y-auto rounded-md border border-border">
                      {clientsFiltres.slice(0, 100).map((c) => (
                        <li key={c.id}>
                          <button type="button" className="w-full px-3 py-1.5 text-left text-xs hover:bg-muted" onClick={() => setClient(c.id)}>
                            {c.nom} {c.enseignes.length > 0 && <span className="text-muted-foreground">· {c.enseignes.join(', ')}</span>}
                          </button>
                        </li>
                      ))}
                      {clientsFiltres.length === 0 && <li className="px-3 py-2 text-xs text-muted-foreground">Aucun client marchand.</li>}
                    </ul>
                  </>
                )}
              </section>

              {/* 2. Villes ou zone */}
              <section className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                  <Label className="text-xs font-semibold">2. Villes concernées</Label>
                  <div className="ml-auto inline-flex rounded-md border border-border p-0.5 text-[11px]">
                    {(['villes', 'zone'] as const).map((m) => (
                      <button key={m} type="button" onClick={() => setMode(m)}
                        className={`rounded px-2.5 py-1 ${mode === m ? 'bg-[#004c68] text-white' : 'text-muted-foreground'}`}>
                        {m === 'villes' ? 'Villes sélectionnées' : 'Zone entière'}
                      </button>
                    ))}
                  </div>
                </div>

                <select
                  value={zone ?? ''}
                  onChange={(e) => setZone(e.target.value === '' ? null : Number(e.target.value))}
                  className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
                  aria-label="Zone"
                >
                  <option value="">{mode === 'zone' ? 'Choisir une zone…' : 'Toutes les zones'}</option>
                  {ref.zones.map((z) => <option key={z.id} value={z.id}>{z.name}{z.code ? ` (${z.code})` : ''}</option>)}
                </select>

                {mode === 'zone' ? (
                  <p className="text-[11px] text-muted-foreground">
                    {zone === null ? 'Sélectionnez une zone.' : `${villesDeZone} ville(s) de cette zone seront concernées.`}
                  </p>
                ) : (
                  <>
                    <div className="flex items-center gap-2">
                      <div className="relative flex-1">
                        <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input value={rechercheVille} onChange={(e) => setRechercheVille(e.target.value)} placeholder="Rechercher une ville…" className="h-8 pl-8 text-xs" />
                      </div>
                      <Button type="button" variant="outline" size="sm" className="h-8 text-[11px]"
                        onClick={() => setVilles((p) => new Set([...p, ...villesFiltrees.map((v) => v.id)]))}>
                        Tout cocher ({villesFiltrees.length})
                      </Button>
                      <Button type="button" variant="ghost" size="sm" className="h-8 text-[11px]" disabled={villes.size === 0} onClick={() => setVilles(new Set())}>
                        Vider
                      </Button>
                    </div>
                    <ul className="grid max-h-48 grid-cols-1 gap-x-3 overflow-y-auto rounded-md border border-border p-2 sm:grid-cols-2">
                      {villesFiltrees.slice(0, AFFICHAGE_MAX).map((v) => (
                        <li key={v.id}>
                          <label className="flex cursor-pointer items-center gap-2 rounded px-1 py-1 text-xs hover:bg-muted">
                            <Checkbox checked={villes.has(v.id)} onCheckedChange={(c) => basculerVille(v.id, Boolean(c))} />
                            <span className="flex-1 truncate">{v.nom}</span>
                            <span className="font-mono text-[10px] text-muted-foreground">{v.standard === null ? 'std —' : `std ${v.standard}`}</span>
                          </label>
                        </li>
                      ))}
                    </ul>
                    <p className="text-[11px] text-muted-foreground">
                      <strong>{villes.size}</strong> ville(s) sélectionnée(s)
                      {villesFiltrees.length > AFFICHAGE_MAX && ` · ${AFFICHAGE_MAX} premiers résultats affichés, affinez la recherche`}
                    </p>
                  </>
                )}
              </section>

              {/* 3. Montants */}
              <section className="space-y-2">
                <Label className="text-xs font-semibold">3. Montants négociés (MAD)</Label>
                <div className="grid grid-cols-3 gap-3">
                  {([['livraison', 'Livraison'], ['refus', 'Refus'], ['retour', 'Retour']] as const).map(([cle, label]) => (
                    <div key={cle} className="space-y-1">
                      <Label htmlFor={`neg-${cle}`} className="text-[11px] text-muted-foreground">{label}</Label>
                      <Input id={`neg-${cle}`} inputMode="decimal" value={montants[cle]} placeholder="0.00"
                        aria-invalid={Boolean(erreurs[cle]) && montants[cle] !== ''}
                        onChange={(e) => setMontants((m) => ({ ...m, [cle]: e.target.value }))}
                        className="h-8 text-right font-mono text-xs" />
                      {erreurs[cle] && montants[cle] !== '' && <p className="text-[10px] text-destructive">{erreurs[cle]}</p>}
                    </div>
                  ))}
                </div>
                <p className="text-[11px] text-muted-foreground">Champ vide interdit. 0.00 = gratuité négociée. Pour revenir au standard, supprimez la ligne.</p>
              </section>

              {error && <p role="alert" className="rounded-md bg-destructive/5 px-3 py-2 text-xs font-medium text-destructive">{error}</p>}
            </div>
          )}

          {ref && etape === 'apercu' && apercu && ecritures && (
            <div className="space-y-4 text-xs">
              <p>
                <strong>{clientChoisi?.nom}</strong> · {apercu.total} ville(s) ·{' '}
                <span className="font-mono">{normaliserMontant(montants.livraison)} / {normaliserMontant(montants.refus)} / {normaliserMontant(montants.retour)} MAD</span>
              </p>
              <div className="grid grid-cols-3 gap-2">
                <div className="rounded-md border border-border p-2.5"><p className="text-[10px] uppercase text-muted-foreground">Nouvelles</p><p className="text-lg font-bold">{apercu.compte.nouveau}</p></div>
                <div className="rounded-md border border-border p-2.5"><p className="text-[10px] uppercase text-muted-foreground">Déjà négociées</p><p className="text-lg font-bold">{apercu.compte.actif}</p></div>
                <div className="rounded-md border border-border p-2.5"><p className="text-[10px] uppercase text-muted-foreground">Supprimées</p><p className="text-lg font-bold">{apercu.compte.supprime}</p></div>
              </div>

              {apercu.compte.actif > 0 && (
                <fieldset className="space-y-1.5 rounded-md border border-[#ff8806]/40 bg-[#ff8806]/5 p-3">
                  <legend className="px-1 text-[11px] font-semibold">{apercu.compte.actif} ville(s) ont déjà un tarif actif</legend>
                  {([['ignorer', 'Ignorer les tarifs existants (recommandé)'], ['mettre_a_jour', 'Mettre à jour les tarifs existants avec ces montants']] as const).map(([v, l]) => (
                    <label key={v} className="flex cursor-pointer items-center gap-2">
                      <input type="radio" name="existants" checked={existants === v} onChange={() => setExistants(v)} /> {l}
                    </label>
                  ))}
                </fieldset>
              )}
              {apercu.compte.supprime > 0 && (
                <label className="flex cursor-pointer items-center gap-2 rounded-md border border-border p-3">
                  <Checkbox checked={restaurer} onCheckedChange={(c) => setRestaurer(Boolean(c))} />
                  Restaurer les {apercu.compte.supprime} tarif(s) supprimé(s) avec ces nouveaux montants
                </label>
              )}

              <div className="rounded-md bg-muted/40 p-3">
                <p className="mb-1 flex items-center gap-1.5 font-semibold"><CheckCircle2 className="size-3.5 text-emerald-600" /> Récapitulatif</p>
                <div className="flex flex-wrap gap-1.5">
                  <Badge variant="secondary" className="rounded-md text-[10px]">{ecritures.crees} création(s)</Badge>
                  <Badge variant="secondary" className="rounded-md text-[10px]">{ecritures.maj} mise(s) à jour</Badge>
                  <Badge variant="secondary" className="rounded-md text-[10px]">{ecritures.restaurees} restauration(s)</Badge>
                  <Badge variant="outline" className="rounded-md text-[10px]">{ecritures.ignorees} ignorée(s)</Badge>
                </div>
                <p className="mt-2 text-[11px] text-muted-foreground">Aucun colis existant ne sera modifié.</p>
              </div>
              {error && <p role="alert" className="text-destructive">{error}</p>}
            </div>
          )}
        </div>

        <DialogFooter className="border-t border-border px-5 py-3">
          {etape === 'apercu' ? (
            <>
              <Button variant="outline" size="sm" className="h-8 text-xs" disabled={busy} onClick={() => setEtape('saisie')}>
                <ArrowLeft className="size-3.5" /> Retour
              </Button>
              <Button size="sm" className="h-8 bg-[#004c68] text-xs text-white hover:bg-[#00384d]" disabled={busy || (ecritures?.crees ?? 0) + (ecritures?.maj ?? 0) + (ecritures?.restaurees ?? 0) === 0} onClick={confirmer}>
                {busy && <Loader2 className="size-3.5 animate-spin" />} Confirmer l'enregistrement
              </Button>
            </>
          ) : (
            <>
              <Button variant="outline" size="sm" className="h-8 text-xs" disabled={busy} onClick={onClose}>Annuler</Button>
              <Button size="sm" className="h-8 bg-[#004c68] text-xs text-white hover:bg-[#00384d]" disabled={busy || !saisieValide} onClick={ouvrirApercu}>
                {busy && <Loader2 className="size-3.5 animate-spin" />} Vérifier ({nbCible} ville{nbCible > 1 ? 's' : ''})
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
