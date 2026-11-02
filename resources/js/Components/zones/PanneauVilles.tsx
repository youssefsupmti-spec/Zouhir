import { useEffect, useState } from 'react';
import { ArrowLeftRight, ChevronLeft, ChevronRight, Loader2, Minus, Plus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type Paginee, type VillePanneau, zoneJson } from './types';

interface Props {
  zoneId: number;
  zoneCode: string;
  panneau: 'disponibles' | 'zone';
  /** Incrémenté après chaque transfert : les deux panneaux se rechargent. */
  version: number;
  onTransfert: (message: string) => void;
  onErreur: (message: string) => void;
}

/**
 * Un des deux panneaux de la page d'édition (WinningExpress : ZoneVillesDisponiblesTable /
 * ZoneVillesAffecteesTable) : recherche serveur, pagination, sélection multiple, transfert
 * immédiat sans recharger la page.
 */
export function PanneauVilles({ zoneId, zoneCode, panneau, version, onTransfert, onErreur }: Props) {
  const disponibles = panneau === 'disponibles';
  const [search, setSearch] = useState('');
  const [terme, setTerme] = useState('');
  const [sansZone, setSansZone] = useState(disponibles);
  const [page, setPage] = useState(1);
  const [donnees, setDonnees] = useState<Paginee<VillePanneau> | null>(null);
  const [chargement, setChargement] = useState(true);
  const [busy, setBusy] = useState(false);
  const [selection, setSelection] = useState<Set<number>>(new Set());

  useEffect(() => {
    const t = setTimeout(() => { setTerme(search.trim()); setPage(1); }, 300);
    return () => clearTimeout(t);
  }, [search]);

  useEffect(() => {
    let actif = true;
    setChargement(true);
    const params = new URLSearchParams({ panneau, page: String(page), per_page: '25' });
    if (terme) params.set('search', terme);
    if (disponibles && sansZone) params.set('sans_zone', '1');
    zoneJson<Paginee<VillePanneau>>(`/zones/${zoneId}/villes?${params}`)
      .then((d) => { if (actif) { setDonnees(d); setSelection(new Set()); } })
      .catch((e: Error) => actif && onErreur(e.message))
      .finally(() => actif && setChargement(false));
    return () => { actif = false; };
  }, [zoneId, panneau, page, terme, sansZone, version]);

  const lignes = donnees?.data ?? [];
  const ids = [...selection];
  const transferer = () => {
    setBusy(true);
    zoneJson<{ modifiees: number }>(`/zones/${zoneId}/villes`, disponibles ? 'POST' : 'DELETE', { ids })
      .then((r) => onTransfert(disponibles
        ? `${r.modifiees} ville(s) rattachée(s) à ${zoneCode}.`
        : `${r.modifiees} ville(s) retirée(s) de ${zoneCode} (désormais sans zone).`))
      .catch((e: Error) => onErreur(e.message))
      .finally(() => setBusy(false));
  };

  return (
    <section aria-label={disponibles ? 'Villes disponibles' : 'Villes de la zone'} className="flex min-w-0 flex-col rounded-md border border-border bg-card shadow-2xs">
      <header className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
        <div className="mr-auto">
          <h2 className="text-sm font-semibold">{disponibles ? 'Villes disponibles' : `Villes de la zone ${zoneCode}`}</h2>
          <p className="text-[11px] text-muted-foreground">
            {donnees ? `${donnees.total.toLocaleString('fr-FR')} ville(s)` : '…'}
            {disponibles && ' · une ville d’une autre zone y sera déplacée'}
          </p>
        </div>
        <Button size="sm" disabled={busy || ids.length === 0} onClick={transferer}
          className={`h-8 rounded-md text-xs ${disponibles ? 'bg-[#004c68] text-white hover:bg-[#00384d]' : ''}`}
          variant={disponibles ? 'default' : 'outline'}>
          {busy ? <Loader2 className="size-3.5 animate-spin" /> : disponibles ? <Plus className="size-3.5" /> : <Minus className="size-3.5" />}
          {disponibles ? 'Ajouter à la zone' : 'Retirer de la zone'}{ids.length > 0 && ` (${ids.length})`}
        </Button>
      </header>

      <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/30 px-4 py-2">
        <div className="relative min-w-48 flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Ville (français ou arabe), code de zone…"
            aria-label={disponibles ? 'Rechercher une ville disponible' : 'Rechercher une ville de la zone'} className="h-8 pl-8 text-xs" />
        </div>
        {disponibles && (
          <label className="flex cursor-pointer items-center gap-2 text-[11px] text-muted-foreground select-none">
            <Checkbox checked={sansZone} onCheckedChange={(c) => { setSansZone(Boolean(c)); setPage(1); }} />
            Sans zone uniquement
          </label>
        )}
      </div>

      <div className="relative min-h-64 overflow-x-auto">
        {chargement && (
          <div className="absolute inset-0 z-10 flex items-center justify-center bg-card/60">
            <Loader2 className="size-5 animate-spin text-muted-foreground" />
          </div>
        )}
        <Table className="text-xs">
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="w-10 pl-4">
                {lignes.length > 0 && (
                  <Checkbox aria-label="Tout sélectionner sur la page" checked={ids.length === lignes.length}
                    indeterminate={ids.length > 0 && ids.length < lignes.length}
                    onCheckedChange={(c) => setSelection(c ? new Set(lignes.map((l) => l.id)) : new Set())} />
                )}
              </TableHead>
              <TableHead>Ville</TableHead>
              {disponibles && <TableHead className="w-32 pr-4">Zone actuelle</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {lignes.map((v) => (
              <TableRow key={v.id} data-state={selection.has(v.id) ? 'selected' : undefined} className="cursor-pointer"
                onClick={() => setSelection((p) => { const n = new Set(p); if (n.has(v.id)) n.delete(v.id); else n.add(v.id); return n; })}>
                <TableCell className="pl-4" onClick={(e) => e.stopPropagation()}>
                  <Checkbox aria-label={`Sélectionner ${v.nom}`} checked={selection.has(v.id)}
                    onCheckedChange={(c) => setSelection((p) => { const n = new Set(p); if (c) n.add(v.id); else n.delete(v.id); return n; })} />
                </TableCell>
                <TableCell>
                  <span className="font-medium">{v.nom}</span>
                  {v.nom_ar && <span dir="rtl" lang="ar" className="ml-2 text-muted-foreground">{v.nom_ar}</span>}
                </TableCell>
                {disponibles && (
                  <TableCell className="pr-4">
                    {v.zone_code
                      ? <span className="inline-flex items-center gap-1 font-mono text-[10px] font-semibold text-muted-foreground" title={v.zone_nom ?? ''}><ArrowLeftRight className="size-3" />{v.zone_code}</span>
                      : <span className="rounded-full bg-[#ff8806]/10 px-2 py-0.5 text-[10px] font-semibold text-[#c26400] dark:text-[#ff8806]">Sans zone</span>}
                  </TableCell>
                )}
              </TableRow>
            ))}
            {!chargement && lignes.length === 0 && (
              <TableRow><TableCell colSpan={disponibles ? 3 : 2} className="py-10 text-center text-muted-foreground">
                {terme ? 'Aucune ville ne correspond.' : disponibles ? 'Aucune ville disponible.' : 'Aucune ville dans cette zone.'}
              </TableCell></TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {donnees && donnees.last_page > 1 && (
        <footer className="mt-auto flex items-center justify-between border-t border-border px-4 py-2 text-[11px] text-muted-foreground">
          <span>{donnees.from}–{donnees.to} sur {donnees.total.toLocaleString('fr-FR')}</span>
          <span className="flex items-center gap-1">
            <Button size="icon" variant="ghost" className="size-7" aria-label="Page précédente" disabled={page <= 1} onClick={() => setPage(page - 1)}><ChevronLeft className="size-3.5" /></Button>
            {donnees.current_page} / {donnees.last_page}
            <Button size="icon" variant="ghost" className="size-7" aria-label="Page suivante" disabled={page >= donnees.last_page} onClick={() => setPage(page + 1)}><ChevronRight className="size-3.5" /></Button>
          </span>
        </footer>
      )}
    </section>
  );
}
