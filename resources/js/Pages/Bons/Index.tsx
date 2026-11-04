import { Head, Link, router } from '@inertiajs/react';
import { ArchiveRestore, Boxes, FileStack, Package, PackageCheck, Send, Trash2, Truck, Undo2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
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
  compteurs: Record<TypeBon, number>;
  stats: { total: number; par_statut: Record<string, number>; colis: number; corbeille: number };
  statuts: Record<string, string>;
  filters: { statut: string; search: string; per_page: number; corbeille: boolean; sous_type: string };
}

export const TYPES: Record<TypeBon, { label: string; court: string; icone: typeof Truck; titulaire: string; aide: string }> = {
  livraison: { label: 'Livraison', court: 'BL', icone: Truck, titulaire: 'Livreur', aide: 'Tournées des livreurs de l’agence (dernier kilomètre).' },
  envoi: { label: 'Envoi national', court: 'BE', icone: Send, titulaire: 'Partenaire / hub', aide: 'Colis remis au réseau partenaire pour les autres villes du Royaume.' },
  ramassage: { label: 'Ramassage', court: 'BRA', icone: PackageCheck, titulaire: 'Enseigne', aide: 'Colis d’un marchand entrés au hub.' },
  retour: { label: 'Retour', court: 'BRC', icone: Undo2, titulaire: 'Enseigne / livreur', aide: 'Colis restitués au marchand, ou déchargés par un livreur.' },
};

export default function BonsIndex({ type, bons, compteurs, stats, statuts, filters }: Props) {
  const params = { ...filters, type, corbeille: filters.corbeille ? 1 : undefined };
  const naviguer = (changes: Record<string, unknown>) =>
    router.get('/bons', { ...params, ...changes, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
  const T = TYPES[type];
  const statutsListe = Object.entries(statuts);

  return (
    <AuthenticatedLayout title="Bons">
      <Head title="Bons - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader title="Bons" description="Bordereaux du circuit colis : livraison, envoi national, ramassage et retour." icon={FileStack} />

        <TarifsTabs
          label="Familles de bons"
          value={type}
          onValueChange={(v) => router.get('/bons', { type: v }, { preserveScroll: true })}
          onglets={(Object.keys(TYPES) as TypeBon[]).map((t) => {
            const I = TYPES[t].icone;
            return { value: t, label: `${TYPES[t].label} · ${TYPES[t].court}`, count: compteurs[t] ?? 0, icon: <I className="size-3.5" /> };
          })}
        />
        <p className="-mt-3 text-xs text-muted-foreground">{T.aide}</p>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title={`Bons ${T.court}`} value={stats.total.toLocaleString('fr-FR')} description={`${stats.corbeille} en corbeille`} icon={T.icone} />
          <StatsCard title="Colis transportés" value={stats.colis.toLocaleString('fr-FR')} description="Lignes de bons" icon={Package} />
          {statutsListe.slice(0, 2).map(([cle, libelle]) => (
            <StatsCard key={cle} title={libelle} value={(stats.par_statut[cle] ?? 0).toLocaleString('fr-FR')} description={`sur ${stats.total.toLocaleString('fr-FR')}`} icon={Boxes} />
          ))}
        </div>

        <PageToolbar searchPlaceholder={`Code du bon, ${T.titulaire.toLowerCase()} ou code colis…`} initialSearch={filters.search} currentParams={params}>
          <FilterDropdown
            label="Statut"
            value={filters.statut || 'all'}
            options={[{ value: 'all', label: 'Tous' }, ...statutsListe.map(([value, label]) => ({ value, label: `${label} (${stats.par_statut[value] ?? 0})` }))]}
            onChange={(v) => naviguer({ statut: v === 'all' ? undefined : v })}
          />
          {type === 'retour' && (
            <FilterDropdown
              label="Type"
              value={filters.sous_type || 'all'}
              options={[{ value: 'all', label: 'Tous' }, { value: 'client', label: 'Retour marchand' }, { value: 'livreur', label: 'Décharge livreur' }]}
              onChange={(v) => naviguer({ sous_type: v === 'all' ? undefined : v })}
            />
          )}
          <button
            type="button"
            onClick={() => naviguer({ corbeille: filters.corbeille ? undefined : 1 })}
            className={`inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs ${filters.corbeille ? 'border-destructive/40 bg-destructive/10 text-destructive' : 'border-border text-muted-foreground hover:text-foreground'}`}
          >
            {filters.corbeille ? <ArchiveRestore className="size-3.5" /> : <Trash2 className="size-3.5" />} {filters.corbeille ? 'Quitter la corbeille' : `Corbeille (${stats.corbeille})`}
          </button>
        </PageToolbar>

        <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
          <Table className="min-w-[860px] text-xs">
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="pl-4">Bon</TableHead>
                <TableHead>{T.titulaire}</TableHead>
                <TableHead className="w-28">Statut</TableHead>
                <TableHead className="w-20 text-right">Colis</TableHead>
                <TableHead className="w-32 text-right">Montant CRBT</TableHead>
                <TableHead className="w-36 pr-4">Date</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {bons.data.map((b) => (
                <TableRow key={b.id} className="cursor-pointer" onClick={() => router.visit(`/bons/${type}/${b.id}`)}>
                  <TableCell className="pl-4">
                    <Link href={`/bons/${type}/${b.id}`} className="font-mono font-semibold text-[#004c68] hover:underline dark:text-cyan-300" onClick={(e) => e.stopPropagation()}>
                      {b.code}
                    </Link>
                    {b.sous_type === 'livreur' && <span className="ml-2 rounded bg-sky-500/10 px-1.5 py-px text-[10px] text-sky-700 dark:text-sky-300">Décharge livreur</span>}
                    {b.anomalies > 0 && <span className="ml-2 rounded bg-[#ff8806]/10 px-1.5 py-px text-[10px] font-semibold text-[#c26200]">{b.anomalies} colis introuvable(s)</span>}
                  </TableCell>
                  <TableCell className="font-medium">{b.titulaire ?? <span className="text-muted-foreground">—</span>}</TableCell>
                  <TableCell><BadgeStatutBon statut={b.status} libelle={statuts[b.status] ?? b.status} /></TableCell>
                  <TableCell className="text-right tabular-nums">{b.total_colis}</TableCell>
                  <TableCell className="text-right tabular-nums">{montant(b.montant)}</TableCell>
                  <TableCell className="pr-4 text-muted-foreground">{dateBon(b.date)}</TableCell>
                </TableRow>
              ))}
              {bons.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="py-12 text-center text-muted-foreground">Aucun bon pour ces filtres.</TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        {bons.total > 0 && <DataTablePagination meta={bons} currentParams={params} pageSizeOptions={[25, 50, 100]} />}
      </div>
    </AuthenticatedLayout>
  );
}
