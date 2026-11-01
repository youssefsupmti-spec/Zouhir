import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Loader2, Search } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { erreurMontant, jsonFetch, normaliserMontant } from '@/Pages/TarifsClients/types';

/**
 * Dialog générique « appliquer un tarif à une ou plusieurs villes » du domaine Tarifs :
 * villes choisies OU zone entière (avec exclusions) → montants → APERÇU serveur
 * (nouvelles / existantes, valeurs actuelles) → choix explicite pour les existantes → confirmation.
 *
 * Utilisé par : Tarifs de livraison (standard client), Tarifs livreurs (commission
 * spécifique d'un livreur, barème standard livreur). Aucun calcul tarifaire ici.
 */

export interface ChampMontant {
  cle: string;
  label: string;
  defaut?: string;
}

interface VilleRef {
  id: number;
  nom: string;
  zone_id: number | null;
  actuel: string | null;
}

interface LivreurRef {
  id: number;
  nom: string;
  telephone: string | null;
  nb_villes: number;
}

interface ReferentielReponse {
  zones: { id: number; name: string; code: string | null }[];
  villes: VilleRef[];
  livreurs?: LivreurRef[];
}

interface ApercuVille {
  ville_id: number;
  ville_nom: string;
  statut: 'nouveau' | 'existant' | 'actif' | 'supprime';
  actuel?: Record<string, string> | null;
  livraison?: string | null;
  refus?: string | null;
  standard?: string | null;
}

interface ApercuReponse {
  villes: ApercuVille[];
  compte: Record<string, number>;
  total: number;
}

export interface AppliquerTarifDialogProps {
  open: boolean;
  onClose: () => void;
  onSaved?: () => void;
  titre: string;
  description: string;
  referentielUrl: string;
  apercuUrl: string;
  storeUrl: string;
  champs: ChampMontant[];
  /** Libellé de la valeur actuelle affichée en regard de chaque ville (référentiel). */
  libelleActuel: string;
  avecDelai?: boolean;
  /** Sélection d'un livreur (commissions spécifiques). */
  avecLivreur?: boolean;
  livreurInitial?: number | null;
  avecRestauration?: boolean;
  zoneInitiale?: number | null;
}

const AFFICHAGE_MAX = 300;

const STATUT_LIBELLE: Record<string, { texte: string; classe: string }> = {
  nouveau: { texte: 'Nouvelle', classe: 'border-emerald-500/30 text-emerald-700 dark:text-emerald-400' },
  existant: { texte: 'Déjà tarifée', classe: 'border-[#ff8806]/40 text-[#b45f00] dark:text-[#ffb366]' },
  actif: { texte: 'Déjà affectée', classe: 'border-[#ff8806]/40 text-[#b45f00] dark:text-[#ffb366]' },
  supprime: { texte: 'Supprimée (restaurable)', classe: 'border-dashed text-muted-foreground' },
};

export function AppliquerTarifDialog(p: AppliquerTarifDialogProps) {
  const [ref, setRef] = useState<ReferentielReponse | null>(null);
  const [refError, setRefError] = useState<string | null>(null);

  const [livreur, setLivreur] = useState<number | null>(p.livreurInitial ?? null);
  const [mode, setMode] = useState<'villes' | 'zone'>(p.zoneInitiale ? 'zone' : 'villes');
  const [zone, setZone] = useState<number | null>(p.zoneInitiale ?? null);
  const [recherche, setRecherche] = useState('');
  const [villes, setVilles] = useState<Set<number>>(new Set());
  const [exclusions, setExclusions] = useState<Set<number>>(new Set());
  const valeursInitiales = () => Object.fromEntries(p.champs.map((c) => [c.cle, c.defaut ?? '']));
  const [montants, setMontants] = useState<Record<string, string>>(valeursInitiales);
  const [delai, setDelai] = useState('');
  const [existants, setExistants] = useState<'ignorer' | 'mettre_a_jour'>('ignorer');
  const [restaurer, setRestaurer] = useState(true);

  const [etape, setEtape] = useState<'saisie' | 'apercu'>('saisie');
  const [apercu, setApercu] = useState<ApercuReponse | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!p.open) return;
    setLivreur(p.livreurInitial ?? null);
    setMode(p.zoneInitiale ? 'zone' : 'villes');
    setZone(p.zoneInitiale ?? null);
    setVilles(new Set());
    setExclusions(new Set());
    setMontants(valeursInitiales());
    setDelai('');
    setExistants('ignorer');
    setRestaurer(true);
    setEtape('saisie');
    setApercu(null);
    setError(null);
    if (ref === null) {
      jsonFetch<ReferentielReponse>(p.referentielUrl).then(setRef).catch((e: Error) => setRefError(e.message));
    }
  }, [p.open, p.livreurInitial, p.zoneInitiale]);

  const villesFiltrees = useMemo(() => {
    const q = recherche.trim().toLowerCase();
    return (ref?.villes ?? []).filter((v) => (zone === null || v.zone_id === zone) && (q === '' || v.nom.toLowerCase().includes(q)));
  }, [ref, zone, recherche]);

  const villesDeZone = useMemo(() => (zone === null ? [] : (ref?.villes ?? []).filter((v) => v.zone_id === zone)), [ref, zone]);
  const nbCible = mode === 'zone' ? villesDeZone.length - exclusions.size : villes.size;
  const erreurs = Object.fromEntries(p.champs.map((c) => [c.cle, erreurMontant(montants[c.cle] ?? '')]));
  const saisieValide = (!p.avecLivreur || livreur !== null) && nbCible > 0
    && Object.values(erreurs).every((e) => e === null) && delai.length <= 50;

  const cible = () => ({
    ...(p.avecLivreur ? { livreur_id: livreur } : {}),
    ...(mode === 'zone' ? { zone_id: zone, exclusions: [...exclusions] } : { ville_ids: [...villes] }),
  });

  const basculer = (set: Set<number>, maj: (s: Set<number>) => void, id: number, present: boolean) => {
    const next = new Set(set);
    if (present) next.add(id);
    else next.delete(id);
    maj(next);
  };

  const ouvrirApercu = async () => {
    if (!saisieValide) return;
    setBusy(true);
    setError(null);
    try {
      setApercu(await jsonFetch<ApercuReponse>(p.apercuUrl, cible()));
      setEtape('apercu');
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const confirmer = () => {
    router.post(p.storeUrl, {
      ...cible(),
      ...Object.fromEntries(p.champs.map((c) => [c.cle, normaliserMontant(montants[c.cle] ?? '')])),
      ...(p.avecDelai ? { delai } : {}),
      ...(p.avecRestauration ? { restaurer } : {}),
      existants,
    }, {
      preserveScroll: true,
      onStart: () => setBusy(true),
      onFinish: () => setBusy(false),
      onError: (errors) => setError(Object.values(errors)[0] ?? 'Enregistrement refusé.'),
      onSuccess: () => {
        p.onSaved?.();
        p.onClose();
      },
    });
  };

  const existantes = apercu ? apercu.total - (apercu.compte.nouveau ?? 0) - (apercu.compte.supprime ?? 0) : 0;
  const valeurActuelle = (v: ApercuVille) => {
    if (v.actuel) return Object.values(v.actuel).join(' / ');
    if (v.livraison !== undefined && v.livraison !== null) return `${v.livraison} / ${v.refus ?? '—'}`;
    return '—';
  };
  const livreurChoisi = ref?.livreurs?.find((l) => l.id === livreur) ?? null;

  return (
    <Dialog open={p.open} onOpenChange={(o) => !o && !busy && p.onClose()}>
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{p.titre}</DialogTitle>
          <DialogDescription>{p.description}</DialogDescription>
        </DialogHeader>

        {refError && <p role="alert" className="text-xs font-medium text-destructive">{refError}</p>}
        {ref === null && !refError && (
          <p className="flex items-center gap-2 text-xs text-muted-foreground"><Loader2 className="size-4 animate-spin" /> Chargement du référentiel…</p>
        )}

        {ref !== null && etape === 'saisie' && (
          <div className="space-y-4 text-xs">
            {p.avecLivreur && (
              <div className="space-y-1.5">
                <Label htmlFor="appliquer-livreur">Livreur</Label>
                <select id="appliquer-livreur" value={livreur ?? ''} onChange={(e) => setLivreur(e.target.value ? Number(e.target.value) : null)}
                  className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs">
                  <option value="">— Choisir un livreur —</option>
                  {(ref.livreurs ?? []).map((l) => (
                    <option key={l.id} value={l.id}>{l.nom}{l.telephone ? ` · ${l.telephone}` : ''} ({l.nb_villes} ville(s))</option>
                  ))}
                </select>
              </div>
            )}

            <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Portée">
              {(['villes', 'zone'] as const).map((m) => (
                <Button key={m} type="button" size="sm" variant={mode === m ? 'default' : 'outline'} className="h-7 rounded-md text-[11px]"
                  aria-pressed={mode === m} onClick={() => { setMode(m); setExclusions(new Set()); }}>
                  {m === 'villes' ? 'Villes choisies' : 'Toute une zone'}
                </Button>
              ))}
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="appliquer-zone">Zone {mode === 'villes' && <span className="text-muted-foreground">(filtre)</span>}</Label>
                <select id="appliquer-zone" value={zone ?? ''} onChange={(e) => { setZone(e.target.value ? Number(e.target.value) : null); setExclusions(new Set()); }}
                  className="h-8 w-full rounded-md border border-input bg-background px-2 text-xs">
                  <option value="">{mode === 'zone' ? '— Choisir une zone —' : 'Toutes les zones'}</option>
                  {ref.zones.map((z) => <option key={z.id} value={z.id}>{z.name}</option>)}
                </select>
              </div>
              {mode === 'villes' && (
                <div className="space-y-1.5">
                  <Label htmlFor="appliquer-recherche">Ville</Label>
                  <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input id="appliquer-recherche" value={recherche} onChange={(e) => setRecherche(e.target.value)} placeholder="Rechercher une ville…" className="h-8 pl-8 text-xs" />
                  </div>
                </div>
              )}
            </div>

            {(mode === 'villes' || zone !== null) && (
              <div className="max-h-56 overflow-y-auto rounded-md border border-border">
                {(mode === 'villes' ? villesFiltrees : villesDeZone).slice(0, AFFICHAGE_MAX).map((v) => {
                  const coche = mode === 'villes' ? villes.has(v.id) : !exclusions.has(v.id);
                  return (
                    <label key={v.id} className="flex cursor-pointer items-center gap-2 border-b border-border px-3 py-1.5 last:border-b-0 hover:bg-muted/40">
                      <Checkbox checked={coche} aria-label={`${mode === 'villes' ? 'Sélectionner' : 'Inclure'} ${v.nom}`}
                        onCheckedChange={(c) => mode === 'villes'
                          ? basculer(villes, setVilles, v.id, Boolean(c))
                          : basculer(exclusions, setExclusions, v.id, !c)} />
                      <span className="flex-1 truncate">{v.nom}</span>
                      <span className="font-mono text-[10px] text-muted-foreground">{p.libelleActuel} : {v.actuel ?? '—'}</span>
                    </label>
                  );
                })}
                {(mode === 'villes' ? villesFiltrees : villesDeZone).length > AFFICHAGE_MAX && (
                  <p className="px-3 py-2 text-[11px] text-muted-foreground">Affinez la recherche : {AFFICHAGE_MAX} premières villes affichées.</p>
                )}
                {(mode === 'villes' ? villesFiltrees : villesDeZone).length === 0 && (
                  <p className="px-3 py-4 text-center text-[11px] text-muted-foreground">Aucune ville active.</p>
                )}
              </div>
            )}
            <p className="text-[11px] text-muted-foreground">{nbCible} ville(s) ciblée(s){mode === 'zone' && exclusions.size > 0 ? `, ${exclusions.size} exclue(s)` : ''}.</p>

            <div className={`grid gap-3 ${p.champs.length + (p.avecDelai ? 1 : 0) > 2 ? 'sm:grid-cols-4' : 'sm:grid-cols-2'}`}>
              {p.champs.map((c) => (
                <div key={c.cle} className="space-y-1.5">
                  <Label htmlFor={`appliquer-${c.cle}`}>{c.label} (MAD)</Label>
                  <Input id={`appliquer-${c.cle}`} inputMode="decimal" value={montants[c.cle] ?? ''} aria-invalid={erreurs[c.cle] !== null}
                    onChange={(e) => setMontants((m) => ({ ...m, [c.cle]: e.target.value }))} className="h-8 font-mono text-xs" />
                  {erreurs[c.cle] && <p className="text-[10px] text-destructive">{erreurs[c.cle]}</p>}
                </div>
              ))}
              {p.avecDelai && (
                <div className="space-y-1.5">
                  <Label htmlFor="appliquer-delai">Délai estimé</Label>
                  <Input id="appliquer-delai" value={delai} maxLength={50} placeholder="24H-48H" onChange={(e) => setDelai(e.target.value)} className="h-8 text-xs" />
                </div>
              )}
            </div>
          </div>
        )}

        {apercu !== null && etape === 'apercu' && (
          <div className="space-y-3 text-xs">
            {livreurChoisi && <p>Livreur : <span className="font-semibold">{livreurChoisi.nom}</span></p>}
            <div className="flex flex-wrap gap-1.5">
              <Badge variant="secondary" className="rounded-md text-[10px]">{apercu.total} ville(s)</Badge>
              <Badge variant="outline" className="rounded-md text-[10px]">{apercu.compte.nouveau ?? 0} création(s)</Badge>
              <Badge variant="outline" className="rounded-md text-[10px]">{existantes} existante(s)</Badge>
              {(apercu.compte.supprime ?? 0) > 0 && <Badge variant="outline" className="rounded-md text-[10px]">{apercu.compte.supprime} supprimée(s)</Badge>}
            </div>

            <div className="max-h-52 overflow-y-auto rounded-md border border-border">
              {apercu.villes.slice(0, AFFICHAGE_MAX).map((v) => (
                <div key={v.ville_id} className="flex items-center gap-2 border-b border-border px-3 py-1.5 last:border-b-0">
                  <span className="flex-1 truncate">{v.ville_nom}</span>
                  <span className="font-mono text-[10px] text-muted-foreground">{v.statut === 'nouveau' ? '—' : valeurActuelle(v)}</span>
                  <Badge variant="outline" className={`rounded-md text-[10px] ${STATUT_LIBELLE[v.statut]?.classe ?? ''}`}>{STATUT_LIBELLE[v.statut]?.texte ?? v.statut}</Badge>
                </div>
              ))}
            </div>

            {existantes > 0 && (
              <fieldset className="space-y-1.5 rounded-md border border-[#ff8806]/30 bg-[#ff8806]/5 p-3">
                <legend className="px-1 text-[11px] font-semibold">{existantes} ville(s) ont déjà une valeur : que faire ?</legend>
                {([['ignorer', 'Ignorer les existantes (créer uniquement les manquantes)'], ['mettre_a_jour', 'Mettre à jour les existantes avec les nouveaux montants']] as const).map(([v, t]) => (
                  <label key={v} className="flex cursor-pointer items-center gap-2">
                    <input type="radio" name="existants" value={v} checked={existants === v} onChange={() => setExistants(v)} />
                    {t}
                  </label>
                ))}
              </fieldset>
            )}
            {p.avecRestauration && (apercu.compte.supprime ?? 0) > 0 && (
              <label className="flex cursor-pointer items-center gap-2">
                <Checkbox checked={restaurer} onCheckedChange={(c) => setRestaurer(Boolean(c))} aria-label="Restaurer les commissions supprimées" />
                Restaurer les {apercu.compte.supprime} commission(s) supprimée(s) avec ces montants
              </label>
            )}
            <p className="text-[11px] text-muted-foreground">Les colis existants conservent leurs montants figés : aucune modification rétroactive.</p>
          </div>
        )}

        {error && <p role="alert" className="text-xs font-medium text-destructive">{error}</p>}

        <DialogFooter>
          {etape === 'apercu' ? (
            <>
              <Button variant="outline" size="sm" onClick={() => setEtape('saisie')} disabled={busy}><ArrowLeft className="size-3.5" /> Retour</Button>
              <Button size="sm" className="bg-[#004c68] text-white hover:bg-[#00384d]" onClick={confirmer}
                disabled={busy || (apercu !== null && (apercu.compte.nouveau ?? 0) === 0 && existants === 'ignorer' && (!p.avecRestauration || !restaurer || (apercu.compte.supprime ?? 0) === 0))}>
                {busy ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />} Confirmer
              </Button>
            </>
          ) : (
            <>
              <Button variant="outline" size="sm" onClick={p.onClose} disabled={busy}>Annuler</Button>
              <Button size="sm" className="bg-[#004c68] text-white hover:bg-[#00384d]" onClick={ouvrirApercu} disabled={busy || !saisieValide}>
                {busy && <Loader2 className="size-3.5 animate-spin" />} Vérifier ({Math.max(nbCible, 0)})
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
