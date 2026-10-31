import { useEffect, useMemo, useState } from 'react';
import { ChevronRightIcon, Loader2, MapPinned, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type Capacites, type ClientResume, type LigneTarif, type ZoneGrille, formatMad, jsonFetch } from './types';

interface Props {
  client: ClientResume;
  can: Capacites;
  /** Incrémenté après chaque écriture : une grille ouverte se recharge. */
  version: number;
  onAjouter: (client: ClientResume) => void;
  onModifier: (client: ClientResume, lignes: LigneTarif[]) => void;
  onSupprimer: (client: ClientResume, lignes: LigneTarif[]) => void;
}

/** Badge discret de comparaison au standard (calculée côté serveur). */
function BadgeTarif({ ligne }: { ligne: LigneTarif }) {
  if (ligne.gratuit) {
    return <Badge variant="outline" className="rounded-md border-sky-500/30 text-[10px] text-sky-700 dark:text-sky-300">Gratuit</Badge>;
  }
  switch (ligne.comparaison) {
    case 'superieur':
      return <Badge variant="outline" className="rounded-md border-[#ff8806]/40 text-[10px] text-[#b45f00] dark:text-[#ffb366]">Au-dessus du standard</Badge>;
    case 'inferieur':
      return <Badge variant="outline" className="rounded-md border-emerald-500/30 text-[10px] text-emerald-700 dark:text-emerald-400">En dessous du standard</Badge>;
    case 'egal':
      return <Badge variant="outline" className="rounded-md text-[10px] text-muted-foreground">= standard</Badge>;
    default:
      return <Badge variant="outline" className="rounded-md border-dashed text-[10px] text-muted-foreground">Standard indisponible</Badge>;
  }
}

export function ClientTarifsCard({ client, can, version, onAjouter, onModifier, onSupprimer }: Props) {
  const [open, setOpen] = useState(false);
  const [zones, setZones] = useState<ZoneGrille[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selection, setSelection] = useState<Set<number>>(new Set());

  // Chargement paresseux : la grille n'est demandée qu'à l'ouverture (et rechargée après écriture).
  useEffect(() => {
    if (!open) return;
    let annule = false;
    setLoading(true);
    setError(null);
    jsonFetch<{ zones: ZoneGrille[] }>(`/tarifs-clients/${client.id}/grille`)
      .then((data) => {
        if (annule) return;
        setZones(data.zones);
        // La sélection ne garde que les lignes encore présentes.
        const ids = new Set(data.zones.flatMap((z) => z.lignes.map((l) => l.id)));
        setSelection((prev) => new Set([...prev].filter((id) => ids.has(id))));
      })
      .catch((e: Error) => !annule && setError(e.message))
      .finally(() => !annule && setLoading(false));
    return () => {
      annule = true;
    };
  }, [open, version, client.id]);

  const toutes = useMemo(() => zones?.flatMap((z) => z.lignes) ?? [], [zones]);
  const selectionnees = toutes.filter((l) => selection.has(l.id));
  const peutSelectionner = can.update || can.delete;

  const basculer = (ids: number[], coche: boolean) =>
    setSelection((prev) => {
      const next = new Set(prev);
      ids.forEach((id) => (coche ? next.add(id) : next.delete(id)));
      return next;
    });

  return (
    <Collapsible open={open} onOpenChange={setOpen} className="group/client rounded-md border border-border bg-card shadow-2xs">
      <div className="flex flex-wrap items-center gap-3 px-4 py-3">
        <CollapsibleTrigger className="flex min-w-0 flex-1 cursor-pointer items-center gap-3 text-left">
          <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground transition-transform group-data-open/client:rotate-90" />
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-foreground">{client.nom}</p>
            <p className="truncate text-[11px] text-muted-foreground">
              {client.enseignes.length > 0 ? client.enseignes.join(' · ') : client.email ?? 'Compte marchand'}
            </p>
          </div>
        </CollapsibleTrigger>
        <div className="flex shrink-0 flex-wrap items-center gap-1.5">
          <Badge variant="secondary" className="rounded-md text-[10px]">{client.nb_tarifs} ville(s) négociée(s)</Badge>
          <Badge variant="outline" className="hidden rounded-md text-[10px] sm:inline-flex">{client.nb_zones} zone(s)</Badge>
          <Badge variant="outline" className="rounded-md font-mono text-[10px]">
            {client.livraison_min === client.livraison_max ? formatMad(client.livraison_min) : `${client.livraison_min} – ${client.livraison_max} MAD`}
          </Badge>
          {can.create && (
            <Button variant="outline" size="sm" className="h-7 rounded-md text-[11px]" onClick={() => onAjouter(client)}>
              <Plus className="size-3.5" />
              Ajouter des villes
            </Button>
          )}
        </div>
      </div>

      <CollapsibleContent>
        <div className="border-t border-border">
          {loading && zones === null && (
            <div className="flex items-center gap-2 px-4 py-6 text-xs text-muted-foreground">
              <Loader2 className="size-4 animate-spin" /> Chargement de la grille…
            </div>
          )}
          {error && (
            <div role="alert" className="px-4 py-3 text-xs font-medium text-destructive">{error}</div>
          )}

          {zones !== null && (
            <>
              {peutSelectionner && (
                <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/30 px-4 py-2 text-[11px]">
                  <span className="text-muted-foreground">
                    {selectionnees.length > 0 ? `${selectionnees.length} tarif(s) sélectionné(s)` : 'Cochez des villes pour agir en lot'}
                  </span>
                  {loading && <Loader2 className="size-3.5 animate-spin text-muted-foreground" />}
                  <div className="ml-auto flex gap-1.5">
                    {can.update && (
                      <Button size="sm" variant="outline" className="h-7 rounded-md text-[11px]" disabled={selectionnees.length === 0}
                        onClick={() => onModifier(client, selectionnees)}>
                        <Pencil className="size-3.5" /> Modifier la sélection
                      </Button>
                    )}
                    {can.delete && (
                      <Button size="sm" variant="outline" className="h-7 rounded-md text-[11px] text-destructive hover:text-destructive" disabled={selectionnees.length === 0}
                        onClick={() => onSupprimer(client, selectionnees)}>
                        <Trash2 className="size-3.5" /> Supprimer la sélection
                      </Button>
                    )}
                  </div>
                </div>
              )}

              {zones.length === 0 && (
                <p className="px-4 py-6 text-center text-xs text-muted-foreground">Aucun tarif actif pour ce client.</p>
              )}

              {zones.map((zone) => {
                const ids = zone.lignes.map((l) => l.id);
                const cochees = ids.filter((id) => selection.has(id)).length;
                return (
                  <Collapsible key={zone.zone_id ?? 'sans-zone'} defaultOpen className="group/zone border-b border-border last:border-b-0">
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center gap-2 px-4 py-2 text-left hover:bg-muted/30">
                      <ChevronRightIcon className="size-3.5 text-muted-foreground transition-transform group-data-open/zone:rotate-90" />
                      <MapPinned className="size-3.5 text-[#004c68] dark:text-[#ff8806]" />
                      <span className="text-xs font-semibold">{zone.zone_nom}</span>
                      {zone.zone_code && <code className="rounded bg-muted px-1 font-mono text-[10px]">{zone.zone_code}</code>}
                      <span className="ml-auto text-[11px] text-muted-foreground">{zone.lignes.length} ville(s)</span>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <div className="overflow-x-auto">
                        <Table className="text-xs">
                          <TableHeader>
                            <TableRow className="hover:bg-transparent">
                              {peutSelectionner && (
                                <TableHead className="w-8 pl-4">
                                  <Checkbox
                                    aria-label={`Sélectionner toute la zone ${zone.zone_nom}`}
                                    checked={cochees === ids.length && ids.length > 0}
                                    indeterminate={cochees > 0 && cochees < ids.length}
                                    onCheckedChange={(c) => basculer(ids, Boolean(c))}
                                  />
                                </TableHead>
                              )}
                              <TableHead className={peutSelectionner ? '' : 'pl-4'}>Ville</TableHead>
                              <TableHead className="text-right">Standard</TableHead>
                              <TableHead className="text-right">Livraison négociée</TableHead>
                              <TableHead className="text-right">Refus</TableHead>
                              <TableHead className="text-right">Retour</TableHead>
                              <TableHead className="hidden md:table-cell">Repère</TableHead>
                              {can.update && <TableHead className="w-10 pr-4 text-right"><span className="sr-only">Actions</span></TableHead>}
                            </TableRow>
                          </TableHeader>
                          <TableBody>
                            {zone.lignes.map((l) => (
                              <TableRow key={l.id} data-state={selection.has(l.id) ? 'selected' : undefined}>
                                {peutSelectionner && (
                                  <TableCell className="pl-4">
                                    <Checkbox aria-label={`Sélectionner ${l.ville_nom}`} checked={selection.has(l.id)}
                                      onCheckedChange={(c) => basculer([l.id], Boolean(c))} />
                                  </TableCell>
                                )}
                                <TableCell className={`font-medium ${peutSelectionner ? '' : 'pl-4'}`}>{l.ville_nom}</TableCell>
                                <TableCell className="text-right font-mono text-muted-foreground">{l.standard === null ? '—' : l.standard}</TableCell>
                                <TableCell className="text-right font-mono font-semibold text-[#004c68] dark:text-[#ff8806]">{l.livraison}</TableCell>
                                <TableCell className="text-right font-mono">{l.refus}</TableCell>
                                <TableCell className="text-right font-mono">{l.retour}</TableCell>
                                <TableCell className="hidden md:table-cell"><BadgeTarif ligne={l} /></TableCell>
                                {can.update && (
                                  <TableCell className="pr-4 text-right">
                                    <Button variant="ghost" size="icon-xs" className="rounded-md" aria-label={`Modifier ${l.ville_nom}`}
                                      onClick={() => onModifier(client, [l])}>
                                      <Pencil className="size-3.5" />
                                    </Button>
                                  </TableCell>
                                )}
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      </div>
                    </CollapsibleContent>
                  </Collapsible>
                );
              })}
            </>
          )}
        </div>
      </CollapsibleContent>
    </Collapsible>
  );
}
