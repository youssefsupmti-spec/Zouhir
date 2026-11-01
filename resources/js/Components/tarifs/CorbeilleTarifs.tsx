import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Trash2, Undo2 } from 'lucide-react';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export interface LigneCorbeille {
  id: number;
  titulaire: string;
  titulaire_archive: boolean;
  ville_nom: string;
  zone_nom: string;
  /** Montants dans l'ordre des colonnes `montants` (chaînes exactes « 12.00 »). */
  montants: string[];
}

interface Props {
  lignes: LigneCorbeille[];
  peutAgir: boolean;
  titulaire: string;
  montants: string[];
  restaurerUrl: string;
  purgerUrl: string;
}

/**
 * Corbeille du domaine Tarifs (tarifs clients, commissions livreurs) : lignes supprimées
 * logiquement, restaurables ou supprimables définitivement (jamais une ligne active : le
 * serveur ne cible que la corbeille). Les compteurs des onglets se rafraîchissent avec la page.
 */
export function CorbeilleTarifs({ lignes, peutAgir, titulaire, montants, restaurerUrl, purgerUrl }: Props) {
  const [selection, setSelection] = useState<Set<number>>(new Set());
  const [purge, setPurge] = useState<number[] | null>(null);
  const [busy, setBusy] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  useEffect(() => setSelection(new Set()), [lignes]);

  const options = (apres?: () => void) => ({
    preserveScroll: true,
    onStart: () => setBusy(true),
    onFinish: () => setBusy(false),
    onError: (e: Record<string, string>) => setErreur(Object.values(e)[0] ?? 'Action refusée.'),
    onSuccess: () => { setErreur(null); apres?.(); },
  });
  const restaurer = (ids: number[]) => router.post(restaurerUrl, { ids }, options());
  const ids = [...selection];
  const colonnes = 5 + montants.length;

  return (
    <div className="rounded-md border border-border bg-card shadow-2xs">
      {erreur && <div role="alert" className="border-b border-destructive/30 bg-destructive/5 px-4 py-2 text-xs text-destructive">{erreur}</div>}
      {peutAgir && lignes.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/30 px-4 py-2 text-[11px]">
          <span className="text-muted-foreground">{ids.length > 0 ? `${ids.length} sélectionné(s)` : `${lignes.length} élément(s) dans la corbeille`}</span>
          <div className="ml-auto flex gap-1.5">
            <Button size="sm" variant="outline" className="h-7 rounded-md text-[11px]" disabled={busy || ids.length === 0} onClick={() => restaurer(ids)}>
              <Undo2 className="size-3.5" /> Restaurer
            </Button>
            <Button size="sm" variant="outline" className="h-7 rounded-md text-[11px] text-destructive hover:text-destructive" disabled={busy || ids.length === 0} onClick={() => setPurge(ids)}>
              <Trash2 className="size-3.5" /> Supprimer définitivement
            </Button>
          </div>
        </div>
      )}
      <div className="overflow-x-auto">
        <Table className="min-w-[760px] table-fixed text-xs">
          <colgroup>
            <col className="w-10" />
            <col />
            <col className="w-40" />
            <col className="w-40" />
            {montants.map((m) => <col key={m} className="w-24" />)}
            <col className="w-44" />
          </colgroup>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="pl-4">
                {peutAgir && lignes.length > 0 && (
                  <Checkbox aria-label="Tout sélectionner" checked={ids.length === lignes.length} indeterminate={ids.length > 0 && ids.length < lignes.length}
                    onCheckedChange={(c) => setSelection(c ? new Set(lignes.map((l) => l.id)) : new Set())} />
                )}
              </TableHead>
              <TableHead>{titulaire}</TableHead>
              <TableHead>Ville</TableHead>
              <TableHead>Zone</TableHead>
              {montants.map((m) => <TableHead key={m} className="text-right">{m}</TableHead>)}
              <TableHead className="pr-4 text-right">Action</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {lignes.map((l) => (
              <TableRow key={l.id} data-state={selection.has(l.id) ? 'selected' : undefined}>
                <TableCell className="pl-4">
                  {peutAgir && <Checkbox aria-label={`Sélectionner ${l.titulaire} ${l.ville_nom}`} checked={selection.has(l.id)}
                    onCheckedChange={(c) => setSelection((p) => { const n = new Set(p); if (c) n.add(l.id); else n.delete(l.id); return n; })} />}
                </TableCell>
                <TableCell className="truncate font-medium">{l.titulaire}{l.titulaire_archive && <span className="ml-1 text-[10px] text-muted-foreground">(archivé)</span>}</TableCell>
                <TableCell className="truncate">{l.ville_nom}</TableCell>
                <TableCell className="truncate text-muted-foreground">{l.zone_nom}</TableCell>
                {l.montants.map((m, i) => <TableCell key={i} className="text-right font-mono">{m} DH</TableCell>)}
                <TableCell className="pr-4 text-right">
                  {peutAgir && (
                    <span className="inline-flex gap-1">
                      <Button size="sm" variant="ghost" className="h-6 rounded-md px-1.5 text-[10px]" aria-label={`Restaurer ${l.titulaire} ${l.ville_nom}`} disabled={busy} onClick={() => restaurer([l.id])}>
                        <Undo2 className="size-3" /> Restaurer
                      </Button>
                      <Button size="sm" variant="ghost" className="h-6 rounded-md px-1.5 text-[10px] text-destructive hover:text-destructive" aria-label={`Supprimer définitivement ${l.titulaire} ${l.ville_nom}`} disabled={busy} onClick={() => setPurge([l.id])}>
                        <Trash2 className="size-3" />
                      </Button>
                    </span>
                  )}
                </TableCell>
              </TableRow>
            ))}
            {lignes.length === 0 && (
              <TableRow><TableCell colSpan={colonnes} className="py-10 text-center text-muted-foreground">La corbeille est vide.</TableCell></TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <ConfirmDeleteDialog
        open={purge !== null}
        onOpenChange={(o) => !o && !busy && setPurge(null)}
        title={`Supprimer définitivement ${purge?.length ?? 0} élément(s)`}
        description="Ces lignes seront effacées de la corbeille sans retour possible. Les colis existants conservent leurs montants figés."
        isLoading={busy}
        onConfirm={() => { if (purge) router.delete(purgerUrl, { data: { ids: purge }, ...options(() => setPurge(null)) }); }}
      />
    </div>
  );
}
