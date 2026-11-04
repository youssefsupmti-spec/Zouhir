import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Boxes, Eye, FileStack, Package, PackageCheck, RotateCcw, Send, Trash2, Truck, Undo2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ChargementTableau, useChargementPage } from '@/components/common/ChargementTableau';
import { PageHeader } from '@/components/common/PageHeader';
import { PageToolbar } from '@/components/common/PageToolbar';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { ActionGroupee, BarreSelection, CaseACocher, useSelection } from '@/components/common/SelectionGroupee';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { BadgeStatutBon, montant, dateBon } from '@/components/bons/partage';

type TypeBon = 'livraison' | 'envoi' | 'ramassage' | 'retour';

interface Bon {
  id: number;
  code: string;
  status: string;
  sous_type: 'client' | 'livreur' | null;
  titulaire: string | null;
  total_colis: number;
  montant: number;
  anomalies: number;
  date: string | null;
  supprime: boolean;
}

interface Props {
  type: TypeBon;
  bons: LaravelPaginationMeta & { data: Bon[] };
  page: { url: string; titre: string; description: string; sous_type: 'client' | 'livreur' | null };
  stats: { total: number; par_statut: Record<string, number>; colis: number; corbeille: number };
  statuts: Record<string, string>;
  filters: { statut: string; search: string; per_page: number; corbeille: boolean };
}

const TYPES: Record<TypeBon, { label: string; court: string; icone: typeof Truck; titulaire: string; aide: string; couleur: string }> = {
  livraison: { label: 'Livraison', court: 'BL', icone: Truck, titulaire: 'Livreur', aide: 'Tournées des livreurs de l’agence', couleur: '#004c68' },
  envoi: { label: 'Envoi national', court: 'BE', icone: Send, titulaire: 'Partenaire / hub', aide: 'Remis au réseau partenaire', couleur: '#0EA5E9' },
  ramassage: { label: 'Ramassage', court: 'BRA', icone: PackageCheck, titulaire: 'Enseigne', aide: 'Colis entrés au hub', couleur: '#10B981' },
  retour: { label: 'Retour', court: 'BRC', icone: Undo2, titulaire: 'Enseigne / livreur', aide: 'Restitués ou déchargés', couleur: '#F97316' },
};

export default function BonsIndex({ type, page, bons, stats, statuts, filters }: Props) {
  const chargement = useChargementPage();
  const [confirmation, setConfirmation] = useState<{ action: 'corbeille' | 'restaurer'; ids: number[]; libelle: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const params = { ...filters, corbeille: filters.corbeille ? 1 : undefined };
  const naviguer = (changes: Record<string, unknown>) =>
    router.get(page.url, { ...params, ...changes, page: 1 }, { preserveState: true, preserveScroll: true, replace: true, only: ['bons', 'stats', 'filters'] });
  const T = TYPES[type];
  const titulaire = page.sous_type === 'livreur' ? 'Livreur' : page.sous_type === 'client' ? 'Enseigne' : T.titulaire;
  const onglet = filters.corbeille ? 'corbeille' : (filters.statut || 'tous');
  const selection = useSelection(bons.data.map((b) => b.id));

  const executer = () => {
    if (!confirmation) return;
    router.post(`/bons/${type}/groupe`, { action: confirmation.action, ids: confirmation.ids }, {
      preserveScroll: true,
      onStart: () => setBusy(true),
      onSuccess: () => selection.vider(),
      onFinish: () => { setBusy(false); setConfirmation(null); },
    });
  };

  return (
    <AuthenticatedLayout title={page.titre}>
      <Head title={`${page.titre} - OUAISS EXPRESS`} />

      <div className="space-y-6">
        <PageHeader title={page.titre} description={page.description} icon={T.icone} />

        <TarifsTabs
          label="Statut des bons"
          value={onglet}
          onValueChange={(v) => naviguer(v === 'corbeille' ? { corbeille: 1, statut: undefined } : { corbeille: undefined, statut: v === 'tous' ? undefined : v })}
          onglets={[
            { value: 'tous', label: 'Tous', count: stats.total, icon: <Boxes className="size-3.5" /> },
            ...Object.entries(statuts).map(([value, label]) => ({ value, label, count: stats.par_statut[value] ?? 0 })),
            { value: 'corbeille', label: 'Corbeille', count: stats.corbeille, icon: <Trash2 className="size-3.5" />, tone: 'danger' as const },
          ]}
        />

        <PageToolbar searchPlaceholder={`Code du bon, ${titulaire.toLowerCase()} ou code colis…`} initialSearch={filters.search} currentParams={params}>
          <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground"><Package className="size-3.5" /> {stats.colis.toLocaleString('fr-FR')} colis dans ces bons</span>
        </PageToolbar>

        <BarreSelection taille={selection.taille} onVider={selection.vider}>
          {filters.corbeille
            ? <ActionGroupee icone={<RotateCcw className="size-3.5" />} onClick={() => setConfirmation({ action: 'restaurer', ids: selection.ids, libelle: `${selection.taille} bon(s)` })}>Restaurer</ActionGroupee>
            : <ActionGroupee danger icone={<Trash2 className="size-3.5" />} onClick={() => setConfirmation({ action: 'corbeille', ids: selection.ids, libelle: `${selection.taille} bon(s)` })}>Mettre en corbeille</ActionGroupee>}
        </BarreSelection>

        <div className="relative overflow-x-auto rounded-md border border-border bg-card">
          <ChargementTableau actif={chargement} />
          <Table className="min-w-[900px] text-xs">
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="w-10 pl-4"><CaseACocher label="Tout sélectionner" coche={selection.tous} partiel={selection.partiel} onChange={selection.basculerTous} /></TableHead>
                <TableHead>Bon</TableHead>
                <TableHead>{titulaire}</TableHead>
                <TableHead className="w-28">Statut</TableHead>
                <TableHead className="w-20 text-right">Colis</TableHead>
                <TableHead className="w-32 text-right">Montant CRBT</TableHead>
                <TableHead className="w-36">Date</TableHead>
                <TableHead className="w-20 pr-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {bons.data.map((b) => (
                <TableRow key={b.id} data-state={selection.estChoisi(b.id) ? 'selected' : undefined}>
                  <TableCell className="pl-4"><CaseACocher label={`Sélectionner ${b.code}`} coche={selection.estChoisi(b.id)} onChange={() => selection.basculer(b.id)} /></TableCell>
                  <TableCell>
                    <Link href={`/bons/${type}/${b.id}`} className="font-mono font-semibold text-[#004c68] hover:underline dark:text-cyan-300">{b.code}</Link>
                    
                    {b.anomalies > 0 && <span className="ml-2 rounded bg-[#ff8806]/10 px-1.5 py-px text-[10px] font-semibold text-[#c26200]">{b.anomalies} colis introuvable(s)</span>}
                  </TableCell>
                  <TableCell className="font-medium">{b.titulaire ?? <span className="text-muted-foreground">—</span>}</TableCell>
                  <TableCell><BadgeStatutBon statut={b.status} libelle={statuts[b.status] ?? b.status} /></TableCell>
                  <TableCell className="text-right tabular-nums">{b.total_colis}</TableCell>
                  <TableCell className="text-right tabular-nums">{montant(b.montant)}</TableCell>
                  <TableCell className="text-muted-foreground">{dateBon(b.date)}</TableCell>
                  <TableCell className="pr-4 text-right">
                    <span className="inline-flex gap-0.5">
                      <Button size="icon" variant="ghost" className="size-7" title="Voir le bon" aria-label={`Voir ${b.code}`} onClick={() => router.visit(`/bons/${type}/${b.id}`)}><Eye className="size-3.5" /></Button>
                      {filters.corbeille
                        ? <Button size="icon" variant="ghost" className="size-7" title="Restaurer" aria-label={`Restaurer ${b.code}`} onClick={() => setConfirmation({ action: 'restaurer', ids: [b.id], libelle: b.code })}><RotateCcw className="size-3.5 text-emerald-600" /></Button>
                        : <Button size="icon" variant="ghost" className="size-7" title="Mettre en corbeille" aria-label={`Corbeille ${b.code}`} onClick={() => setConfirmation({ action: 'corbeille', ids: [b.id], libelle: b.code })}><Trash2 className="size-3.5 text-destructive" /></Button>}
                    </span>
                  </TableCell>
                </TableRow>
              ))}
              {bons.data.length === 0 && (
                <TableRow><TableCell colSpan={8} className="py-12 text-center text-muted-foreground">{filters.corbeille ? 'La corbeille est vide.' : 'Aucun bon pour ces filtres.'}</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        {bons.total > 0 && <DataTablePagination meta={bons} currentParams={params} pageSizeOptions={[25, 50, 100]} />}
      </div>

      <ConfirmDeleteDialog
        open={confirmation !== null}
        onOpenChange={(o) => !o && !busy && setConfirmation(null)}
        title={confirmation?.action === 'restaurer' ? 'Restaurer depuis la corbeille' : 'Mettre en corbeille'}
        description={confirmation?.action === 'restaurer'
          ? `Restaurer ${confirmation?.libelle} ?`
          : `Mettre ${confirmation?.libelle} en corbeille ? Les lignes du bon sont conservées ; le bon reste restaurable depuis l’onglet « Corbeille ».`}
        isLoading={busy}
        onConfirm={executer}
      />
    </AuthenticatedLayout>
  );
}
