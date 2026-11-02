import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2, Save, X } from 'lucide-react';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CodeZone } from './RegimeBadge';
import { REGIMES, type TypeZone, type VilleApercu } from './types';

export interface ZoneOption {
  id: number;
  code: string;
  name: string;
  type: TypeZone;
}

interface Props {
  villes: LaravelPaginationMeta & { data: VilleApercu[] };
  options: ZoneOption[];
  search: string;
  params: Record<string, unknown>;
  canManage: boolean;
}

/**
 * Onglet « Villes sans zone » : une liste déroulante par ville ; les choix s'accumulent
 * puis s'enregistrent en une fois (bouton « Enregistrer » avec le nombre de villes modifiées).
 */
export function VillesSansZoneTable({ villes, options, search, params, canManage }: Props) {
  const [choix, setChoix] = useState<Record<number, number>>({});
  const [busy, setBusy] = useState(false);
  const zonesParId = useMemo(() => new Map(options.map((z) => [z.id, z])), [options]);
  const nombre = Object.keys(choix).length;

  /** Résumé « TM +2 · CASA +1 » : villes ajoutées par zone. */
  const resume = useMemo(() => {
    const parZone = new Map<number, number>();
    Object.values(choix).forEach((z) => parZone.set(z, (parZone.get(z) ?? 0) + 1));
    return [...parZone.entries()].map(([id, n]) => ({ zone: zonesParId.get(id), n }));
  }, [choix, zonesParId]);

  const choisir = (villeId: number, valeur: string) =>
    setChoix((c) => {
      const suivant = { ...c };
      if (valeur === '') delete suivant[villeId];
      else suivant[villeId] = Number(valeur);
      return suivant;
    });

  const enregistrer = () =>
    router.post('/zones/affecter', {
      affectations: Object.entries(choix).map(([ville_id, zone_id]) => ({ ville_id: Number(ville_id), zone_id })),
    }, {
      preserveScroll: true,
      onStart: () => setBusy(true),
      onFinish: () => setBusy(false),
      onSuccess: () => setChoix({}),
    });

  const groupes = (['locale', 'nationale'] as TypeZone[]).map((t) => ({ t, zones: options.filter((z) => z.type === t) }));

  return (
    <div className="space-y-3">
      {canManage && nombre > 0 && (
        <div className="sticky top-2 z-10 flex flex-wrap items-center gap-2 rounded-md border border-[#ff8806]/40 bg-[#ff8806]/10 px-3 py-2 text-xs shadow-sm backdrop-blur">
          <span className="font-semibold">{nombre} ville(s) modifiée(s)</span>
          <span className="text-[11px] text-muted-foreground">Enregistrez avant de changer de page.</span>
          <span className="flex flex-wrap items-center gap-1">
            {resume.map(({ zone, n }) => zone && (
              <span key={zone.id} className="inline-flex items-center gap-1 rounded-full border border-border bg-background px-2 py-0.5 text-[10px]">
                <span className="font-mono font-bold">{zone.code}</span> +{n}
              </span>
            ))}
          </span>
          <span className="ml-auto flex gap-1.5">
            <Button type="button" size="sm" variant="outline" className="h-7 text-[11px]" disabled={busy} onClick={() => setChoix({})}>
              <X className="size-3.5" /> Annuler
            </Button>
            <Button type="button" size="sm" className="h-7 bg-[#004c68] text-[11px] text-white hover:bg-[#00384d]" disabled={busy} onClick={enregistrer}>
              {busy ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />} Enregistrer ({nombre})
            </Button>
          </span>
        </div>
      )}

      <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
        <Table className="min-w-[640px] text-xs">
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="pl-4">Ville</TableHead>
              <TableHead className="w-40">Nom arabe</TableHead>
              <TableHead className="w-80 pr-4">Zone</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {villes.data.map((v) => {
              const zoneId = choix[v.id];
              const zone = zoneId ? zonesParId.get(zoneId) : undefined;
              return (
                <TableRow key={v.id} className={zone ? 'bg-[#ff8806]/5' : undefined}>
                  <TableCell className="pl-4 font-medium">{v.nom}</TableCell>
                  <TableCell>{v.nom_ar ? <span dir="rtl" lang="ar">{v.nom_ar}</span> : <span className="text-muted-foreground">—</span>}</TableCell>
                  <TableCell className="pr-4">
                    <div className="flex items-center gap-2">
                      <select
                        aria-label={`Zone de ${v.nom}`}
                        value={zoneId ?? ''}
                        disabled={!canManage || busy}
                        onChange={(e) => choisir(v.id, e.target.value)}
                        className={`flex h-8 w-full rounded-md border bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30 ${zone ? 'border-[#ff8806]' : 'border-input'}`}
                      >
                        <option value="" className="dark:bg-slate-900">Sans zone</option>
                        {groupes.map(({ t, zones }) => zones.length > 0 && (
                          <optgroup key={t} label={REGIMES[t].label} className="dark:bg-slate-900">
                            {zones.map((z) => <option key={z.id} value={z.id} className="dark:bg-slate-900">{z.code} — {z.name}</option>)}
                          </optgroup>
                        ))}
                      </select>
                      {zone && <CodeZone code={zone.code} />}
                    </div>
                  </TableCell>
                </TableRow>
              );
            })}
            {villes.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={3} className="py-12 text-center text-muted-foreground">
                  {search ? 'Aucune ville sans zone ne correspond à la recherche.' : 'Toutes les villes ont une zone.'}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {villes.total > 0 && <DataTablePagination meta={villes} currentParams={params} pageSizeOptions={[10, 25, 50, 100]} />}
    </div>
  );
}
