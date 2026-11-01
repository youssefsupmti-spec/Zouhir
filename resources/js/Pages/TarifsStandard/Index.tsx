import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Coins, Layers, MapPin, MapPinOff, Plus, RotateCcw, Truck, CheckCircle2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { Button } from '@/components/ui/button';
import { BaremeVillesTable, type BaremeLigne } from '@/components/tarifs/BaremeVillesTable';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { AjouterTarifStandardDialog } from './AjouterTarifStandardDialog';

interface Filters {
  search: string;
  zone_id: string;
  statut: 'tous' | 'tarifees' | 'sans';
  per_page: number;
}

interface Props {
  villes: LaravelPaginationMeta & { data: BaremeLigne[] };
  stats: { villes: number; tarifees: number; sans_tarif: number; moyenne: string | null; gratuites: number };
  zones: { id: number; name: string; code: string | null }[];
  filters: Filters;
  can: { create: boolean; update: boolean };
}

export default function TarifsStandardIndex({ villes, stats, zones, filters, can }: Props) {
  const [dialog, setDialog] = useState<{ open: boolean; zoneId: number | null }>({ open: false, zoneId: null });
  const params = { ...filters };
  const appliquer = (name: keyof Filters, value: string) =>
    router.get('/tarifs-standard', { ...params, [name]: value === 'all' ? undefined : value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
  const filtre = Boolean(filters.search) || filters.zone_id !== 'all';

  return (
    <AuthenticatedLayout title="Tarifs de livraison">
      <Head title="Tarifs de livraison - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Tarifs de livraison"
          description="Gérez les frais de livraison standards par ville et zone."
          icon={Truck}
          actions={can.create ? (
            <div className="flex flex-wrap gap-2">
              <Button size="sm" variant="outline" className="h-8 rounded-md text-xs" onClick={() => setDialog({ open: true, zoneId: null })}>
                <Layers className="size-3.5" /> Appliquer à plusieurs villes
              </Button>
              <Button size="sm" className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]" onClick={() => setDialog({ open: true, zoneId: null })}>
                <Plus className="size-3.5" /> Ajouter un tarif standard
              </Button>
            </div>
          ) : undefined}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Villes actives" value={stats.villes} description="Référentiel national" icon={MapPin} />
          <StatsCard title="Villes tarifées" value={stats.tarifees} description="Avec un tarif standard" icon={CheckCircle2} />
          <StatsCard title="Villes sans tarif" value={stats.sans_tarif} description="Colis en anomalie « manquant » sans tarif négocié" icon={MapPinOff} iconColor="text-[#ff8806]" />
          <StatsCard title="Tarif moyen (MAD)" value={stats.moyenne ?? '—'} description={stats.gratuites > 0 ? `dont ${stats.gratuites} à 0.00 MAD` : 'Moyenne des villes tarifées'} icon={Coins} />
        </div>

        <TarifsTabs
          label="Statut des villes"
          value={filters.statut}
          onValueChange={(v) => appliquer('statut', v)}
          onglets={[
            { value: 'tous', label: 'Toutes les villes', count: stats.villes, icon: <MapPin className="size-3.5" /> },
            { value: 'tarifees', label: 'Tarifées', count: stats.tarifees, icon: <CheckCircle2 className="size-3.5" /> },
            { value: 'sans', label: 'Sans tarif', count: stats.sans_tarif, icon: <MapPinOff className="size-3.5" />, tone: 'danger' },
          ]}
        />

        <PageToolbar
          searchPlaceholder="Ville (français ou arabe)…"
          initialSearch={filters.search}
          currentParams={params}
          rightActions={filtre && (
            <Button variant="ghost" size="sm" className="h-8 rounded-md text-xs" onClick={() => router.get('/tarifs-standard', filters.statut === 'tous' ? {} : { statut: filters.statut }, { replace: true })}>
              <RotateCcw className="size-3.5" /> Réinitialiser
            </Button>
          )}
        >
          <FilterDropdown label="Zone" value={filters.zone_id} onChange={(v) => appliquer('zone_id', v)}
            options={[{ value: 'all', label: 'Toutes' }, ...zones.map((z) => ({ value: String(z.id), label: z.name }))]} />
        </PageToolbar>

        <BaremeVillesTable
          lignes={villes.data}
          saveUrl="/tarifs-standard"
          peutModifier={can.update}
          avecDelai
          libelleActif="Standard actif"
          onAppliquerZone={can.create ? (zoneId) => setDialog({ open: true, zoneId }) : undefined}
          champs={[{ cle: 'livraison', label: 'Livraison' }, { cle: 'refus', label: 'Refus' }, { cle: 'retour', label: 'Retour' }]}
        />

        {villes.total > 0 && <DataTablePagination meta={villes} currentParams={params} pageSizeOptions={[25, 50, 100, 200]} />}
      </div>

      <AjouterTarifStandardDialog open={dialog.open} zoneId={dialog.zoneId} onClose={() => setDialog({ open: false, zoneId: null })} />
    </AuthenticatedLayout>
  );
}
