import { Fragment, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Layers, Loader2, MapPinned, RotateCcw, Save } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { erreurMontant, normaliserMontant } from '@/Pages/TarifsClients/types';
import type { ChampMontant } from './AppliquerTarifDialog';

/**
 * Grille « Zone → Ville → Tarif » d'un barème à une ligne par ville (standard client ou
 * standard livreur), avec édition INLINE : état local « dirty », barre d'enregistrement en lot
 * visible seulement s'il y a des modifications, erreurs serveur rendues PAR LIGNE.
 * Seules les lignes modifiées sont envoyées : aucune valeur non modifiée n'est réécrite.
 */

export interface BaremeLigne {
  ville_id: number;
  ville_nom: string;
  ville_ar: string | null;
  zone_id: number | null;
  zone_nom: string;
  zone_code: string | null;
  tarif: ({ id: number; delai?: string | null } & Record<string, string | number | null | undefined>) | null;
  nb_specifiques?: number;
}

interface Props {
  lignes: BaremeLigne[];
  champs: ChampMontant[];
  saveUrl: string;
  peutModifier: boolean;
  avecDelai?: boolean;
  libelleActif: string;
  onAppliquerZone?: (zoneId: number) => void;
  colonneExtra?: { titre: string; rendu: (l: BaremeLigne) => React.ReactNode };
}

type Brouillon = Record<string, string>;

export function BaremeVillesTable({ lignes, champs, saveUrl, peutModifier, avecDelai = false, libelleActif, onAppliquerZone, colonneExtra }: Props) {
  const [brouillons, setBrouillons] = useState<Record<number, Brouillon>>({});
  const [erreursServeur, setErreursServeur] = useState<Record<number, Record<string, string>>>({});
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  // Nouvelle page / nouveaux filtres : les brouillons de l'ancienne liste sont abandonnés.
  useEffect(() => {
    setBrouillons({});
    setErreursServeur({});
  }, [lignes]);

  const cles = [...champs.map((c) => c.cle), ...(avecDelai ? ['delai'] : [])];
  const valeurInitiale = (l: BaremeLigne, cle: string) => (l.tarif ? String(l.tarif[cle] ?? '') : '');
  const valeur = (l: BaremeLigne, cle: string) => brouillons[l.ville_id]?.[cle] ?? valeurInitiale(l, cle);
  const estModifiee = (l: BaremeLigne) => brouillons[l.ville_id] !== undefined
    && cles.some((c) => normaliserMontant(valeur(l, c)) !== normaliserMontant(valeurInitiale(l, c)));

  const modifiees = lignes.filter(estModifiee);
  const erreursLocales = (l: BaremeLigne): Record<string, string> => {
    if (!estModifiee(l)) return {};
    const e: Record<string, string> = {};
    champs.forEach((c) => {
      const m = erreurMontant(valeur(l, c.cle));
      if (m) e[c.cle] = m;
    });
    if (avecDelai && valeur(l, 'delai').length > 50) e.delai = '50 car. max';
    return e;
  };
  const invalides = modifiees.filter((l) => Object.keys(erreursLocales(l)).length > 0).length;

  const zones = useMemo(() => {
    const groupes: { cle: string; zone_id: number | null; zone_nom: string; zone_code: string | null; lignes: BaremeLigne[] }[] = [];
    lignes.forEach((l) => {
      const cle = l.zone_id === null ? 'sans' : `z${l.zone_id}`;
      let g = groupes.find((x) => x.cle === cle);
      if (!g) {
        g = { cle, zone_id: l.zone_id, zone_nom: l.zone_nom, zone_code: l.zone_code, lignes: [] };
        groupes.push(g);
      }
      g.lignes.push(l);
    });
    return groupes;
  }, [lignes]);

  const saisir = (l: BaremeLigne, cle: string, v: string) => {
    setMessage(null);
    setBrouillons((b) => ({ ...b, [l.ville_id]: { ...(b[l.ville_id] ?? {}), [cle]: v } }));
    setErreursServeur((e) => ({ ...e, [l.ville_id]: {} }));
  };

  const enregistrer = () => {
    const envoi = modifiees.map((l) => ({
      ville_id: l.ville_id,
      ...Object.fromEntries(champs.map((c) => [c.cle, normaliserMontant(valeur(l, c.cle))])),
      ...(avecDelai ? { delai: valeur(l, 'delai').trim() } : {}),
    }));

    router.put(saveUrl, { lignes: envoi }, {
      preserveScroll: true,
      onStart: () => setBusy(true),
      onFinish: () => setBusy(false),
      onSuccess: () => {
        setBrouillons({});
        setErreursServeur({});
      },
      onError: (errors) => {
        const parVille: Record<number, Record<string, string>> = {};
        const autres: string[] = [];
        Object.entries(errors).forEach(([k, m]) => {
          const match = /^lignes\.(\d+)\.(\w+)$/.exec(k);
          const ligne = match ? envoi[Number(match[1])] : undefined;
          if (match && ligne) parVille[ligne.ville_id] = { ...(parVille[ligne.ville_id] ?? {}), [match[2]]: m };
          else autres.push(m);
        });
        setErreursServeur(parVille);
        setMessage(autres[0] ?? 'Certaines lignes sont invalides : corrigez les cellules signalées.');
      },
    });
  };

  const nbColonnes = 6 + champs.length + (avecDelai ? 1 : 0) + (colonneExtra ? 1 : 0);

  return (
    <div className="rounded-md border border-border bg-card shadow-2xs">
      {peutModifier && modifiees.length > 0 && (
        <div className="sticky top-16 z-10 flex flex-wrap items-center gap-2 border-b border-[#ff8806]/30 bg-[#ff8806]/10 px-4 py-2 text-[11px] backdrop-blur">
          <span className="font-semibold text-[#b45f00] dark:text-[#ffb366]">{modifiees.length} ligne(s) modifiée(s)</span>
          {invalides > 0 && <span className="text-destructive">· {invalides} invalide(s)</span>}
          {message && <span role="alert" className="text-destructive">· {message}</span>}
          <div className="ml-auto flex gap-1.5">
            <Button size="sm" variant="ghost" className="h-7 rounded-md text-[11px]" disabled={busy}
              onClick={() => { setBrouillons({}); setErreursServeur({}); setMessage(null); }}>
              <RotateCcw className="size-3.5" /> Annuler
            </Button>
            <Button size="sm" className="h-7 rounded-md bg-[#004c68] text-[11px] text-white hover:bg-[#00384d]" disabled={busy || invalides > 0} onClick={enregistrer}>
              {busy ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />} Enregistrer ({modifiees.length})
            </Button>
          </div>
        </div>
      )}

      <div className="overflow-x-auto">
        <Table className="min-w-[900px] table-fixed text-xs">
          <colgroup>
            <col />
            <col className="w-32" />
            <col className="w-20" />
            {champs.map((c) => <col key={c.cle} className="w-28" />)}
            {avecDelai && <col className="w-28" />}
            {colonneExtra && <col className="w-32" />}
            <col className="w-32" />
            <col className="w-20" />
          </colgroup>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="pl-4">Ville</TableHead>
              <TableHead>Nom arabe</TableHead>
              <TableHead>Zone</TableHead>
              {champs.map((c) => <TableHead key={c.cle} className="text-right">{c.label} MAD</TableHead>)}
              {avecDelai && <TableHead>Délai estimé</TableHead>}
              {colonneExtra && <TableHead>{colonneExtra.titre}</TableHead>}
              <TableHead>Statut</TableHead>
              <TableHead className="pr-4 text-right"><span className="sr-only">Actions</span></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {zones.map((z) => (
              <Fragment key={z.cle}>
                <TableRow className="bg-muted/40 hover:bg-muted/40">
                  <TableCell colSpan={nbColonnes - 1} className="pl-4">
                    <span className="flex items-center gap-2 font-semibold">
                      <MapPinned className="size-3.5 text-[#004c68] dark:text-[#ff8806]" />
                      {z.zone_nom}
                      {z.zone_code && <code className="rounded bg-muted px-1 font-mono text-[10px]">{z.zone_code}</code>}
                      <span className="font-normal text-muted-foreground">· {z.lignes.length} ville(s) sur cette page · {z.lignes.filter((l) => l.tarif).length} tarifée(s)</span>
                    </span>
                  </TableCell>
                  <TableCell className="pr-4 text-right">
                    {peutModifier && onAppliquerZone && z.zone_id !== null && (
                      <Button size="sm" variant="ghost" className="h-6 rounded-md px-2 text-[10px]" onClick={() => onAppliquerZone(z.zone_id as number)}
                        aria-label={`Appliquer un tarif à toute la zone ${z.zone_nom}`}>
                        <Layers className="size-3" /> Zone
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
                {z.lignes.map((l) => {
                  const modifiee = estModifiee(l);
                  const err = { ...erreursLocales(l), ...(erreursServeur[l.ville_id] ?? {}) };
                  return (
                    <TableRow key={l.ville_id} data-state={modifiee ? 'selected' : undefined}>
                      <TableCell className="truncate pl-4 font-medium" title={l.ville_nom}>{l.ville_nom}</TableCell>
                      <TableCell className="truncate text-muted-foreground" dir="rtl" title={l.ville_ar ?? ''}>{l.ville_ar ?? '—'}</TableCell>
                      <TableCell><code className="font-mono text-[10px] text-muted-foreground">{l.zone_code ?? '—'}</code></TableCell>
                      {champs.map((c) => (
                        <TableCell key={c.cle} className="text-right">
                          {peutModifier ? (
                            <div>
                              <Input inputMode="decimal" value={valeur(l, c.cle)} placeholder="—" aria-label={`${c.label} ${l.ville_nom}`}
                                aria-invalid={Boolean(err[c.cle])} onChange={(e) => saisir(l, c.cle, e.target.value)}
                                className="h-7 rounded-md text-right font-mono text-xs" />
                              {err[c.cle] && <p className="mt-0.5 text-[10px] text-destructive">{err[c.cle]}</p>}
                            </div>
                          ) : (
                            <span className="font-mono">{l.tarif ? String(l.tarif[c.cle]) : '—'}</span>
                          )}
                        </TableCell>
                      ))}
                      {avecDelai && (
                        <TableCell>
                          {peutModifier ? (
                            <div>
                              <Input value={valeur(l, 'delai')} placeholder="—" maxLength={50} aria-label={`Délai ${l.ville_nom}`}
                                aria-invalid={Boolean(err.delai)} onChange={(e) => saisir(l, 'delai', e.target.value)} className="h-7 rounded-md text-xs" />
                              {err.delai && <p className="mt-0.5 text-[10px] text-destructive">{err.delai}</p>}
                            </div>
                          ) : (l.tarif?.delai ?? '—')}
                        </TableCell>
                      )}
                      {colonneExtra && <TableCell>{colonneExtra.rendu(l)}</TableCell>}
                      <TableCell>
                        {modifiee ? (
                          <Badge variant="outline" className="rounded-md border-[#ff8806]/40 text-[10px] text-[#b45f00] dark:text-[#ffb366]">Modifiée</Badge>
                        ) : l.tarif ? (
                          <Badge variant="outline" className="rounded-md border-emerald-500/30 text-[10px] text-emerald-700 dark:text-emerald-400">{libelleActif}</Badge>
                        ) : (
                          <Badge variant="outline" className="rounded-md border-destructive/30 text-[10px] text-destructive">Sans tarif</Badge>
                        )}
                      </TableCell>
                      <TableCell className="pr-4 text-right">
                        {modifiee && (
                          <Button size="icon-xs" variant="ghost" className="rounded-md" aria-label={`Annuler la modification de ${l.ville_nom}`}
                            onClick={() => setBrouillons((b) => { const n = { ...b }; delete n[l.ville_id]; return n; })}>
                            <RotateCcw className="size-3.5" />
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </Fragment>
            ))}
            {lignes.length === 0 && (
              <TableRow>
                <TableCell colSpan={nbColonnes} className="py-10 text-center text-muted-foreground">Aucune ville ne correspond à ces filtres.</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
