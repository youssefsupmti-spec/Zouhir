import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { BadgePercent, Coins, MapPin, Plus, RotateCcw, Search, Store, Users } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ClientTarifsCard } from './ClientTarifsCard';
import { AjouterTarifsDialog } from './AjouterTarifsDialog';
import { ModifierTarifsDialog } from './ModifierTarifsDialog';
import type { Capacites, ClientResume, LigneTarif, ZoneRef } from './types';

interface Filters {
  search: string;
  ville: string;
  zone_id: string;
  client_id: string;
  per_page: number;
}

interface Props {
  clients: LaravelPaginationMeta & { data: ClientResume[] };
  stats: {
    clients: number;
    tarifs: number;
    villes: number;
    livraison_min: string | null;
    livraison_max: string | null;
    livraison_moyenne: string | null;
    gratuits: number;
  };
  zones: ZoneRef[];
  filters: Filters;
  can: Capacites;
}

export default function TarifsClientsIndex({ clients, stats, zones, filters, can }: Props) {
  const [version, setVersion] = useState(0);
  const [ajout, setAjout] = useState<{ open: boolean; clientId: number | null }>({ open: false, clientId: null });
  const [edition, setEdition] = useState<{ client: ClientResume | null; lignes: LigneTarif[] }>({ client: null, lignes: [] });
  const [suppression, setSuppression] = useState<{ client: ClientResume | null; lignes: LigneTarif[] }>({ client: null, lignes: [] });
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ville, setVille] = useState(filters.ville ?? '');

  const params = { ...filters };
  const appliquer = (name: keyof Filters, value: string) =>
    router.get('/tarifs-clients', { ...params, [name]: value === 'all' ? undefined : value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });

  // Recherche par ville (debounce, côté serveur).
  useEffect(() => {
    if (ville === (filters.ville ?? '')) return;
    const t = setTimeout(() => appliquer('ville', ville), 350);
    return () => clearTimeout(t);
  }, [ville]);

  const filtre = Boolean(filters.search || filters.ville) || filters.zone_id !== 'all' || filters.client_id !== 'all';
  const rafraichir = () => setVersion((v) => v + 1);

  const confirmerSuppression = () => {
    if (!suppression.client) return;
    router.delete('/tarifs-clients', {
      data: { client_id: suppression.client.id, ids: suppression.lignes.map((l) => l.id) },
      preserveScroll: true,
      onStart: () => setDeleting(true),
      onFinish: () => setDeleting(false),
      onError: (errors) => setError(Object.values(errors)[0] ?? 'Suppression refusée.'),
      onSuccess: () => {
        rafraichir();
        setSuppression({ client: null, lignes: [] });
      },
    });
  };

  const fourchette = stats.livraison_min === null ? '—' : `${stats.livraison_min} – ${stats.livraison_max}`;

  return (
    <AuthenticatedLayout title="Tarifs Clients">
      <Head title="Tarifs Clients - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Tarifs Clients"
          description="Gérez les tarifs négociés par client, zone et ville."
          icon={BadgePercent}
          actions={
            can.create ? (
              <Button size="sm" onClick={() => setAjout({ open: true, clientId: null })} className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]">
                <Plus className="size-3.5" />
                Négocier de nouveaux tarifs
              </Button>
            ) : undefined
          }
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Clients avec tarifs" value={stats.clients} description="Au moins une règle active" icon={Users} />
          <StatsCard title="Règles actives" value={stats.tarifs} description={stats.gratuits > 0 ? `dont ${stats.gratuits} à 0.00 MAD` : 'Couples client × ville'} icon={Store} />
          <StatsCard title="Villes couvertes" value={stats.villes} description="Villes distinctes négociées" icon={MapPin} />
          <StatsCard
            title="Livraison négociée (MAD)"
            value={fourchette}
            description={stats.livraison_moyenne === null ? 'Aucune règle' : `Moyenne ${stats.livraison_moyenne} (par règle)`}
            icon={Coins}
            iconColor="text-[#ff8806]"
          />
        </div>

        {error && (
          <div role="alert" className="flex items-center justify-between gap-3 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-xs font-medium text-destructive">
            {error}
            <button type="button" className="underline" onClick={() => setError(null)}>Fermer</button>
          </div>
        )}

        <PageToolbar
          searchPlaceholder="Client, email ou enseigne…"
          initialSearch={filters.search}
          currentParams={params}
          rightActions={
            filtre && (
              <Button variant="ghost" size="sm" className="h-8 rounded-md text-xs"
                onClick={() => { setVille(''); router.get('/tarifs-clients', {}, { replace: true }); }}>
                <RotateCcw className="size-3.5" />
                Réinitialiser
              </Button>
            )
          }
        >
          <div className="relative w-full sm:w-48">
            <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input value={ville} onChange={(e) => setVille(e.target.value)} placeholder="Ville (ex. Temara)" className="h-8 rounded-md bg-background pl-8 text-xs" aria-label="Rechercher par ville" />
          </div>
          <FilterDropdown
            label="Zone"
            value={filters.zone_id}
            onChange={(v) => appliquer('zone_id', v)}
            options={[{ value: 'all', label: 'Toutes' }, ...zones.map((z) => ({ value: String(z.id), label: z.name }))]}
          />
          {filters.client_id !== 'all' && (
            <Button variant="outline" size="sm" className="h-8 rounded-md text-xs" onClick={() => appliquer('client_id', 'all')}>
              Client #{filters.client_id} ✕
            </Button>
          )}
        </PageToolbar>

        {clients.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-border bg-card p-10 text-center text-xs text-muted-foreground">
            {filtre ? 'Aucun client ne correspond à ces filtres.' : 'Aucun tarif négocié pour le moment. Les colis utilisent le tarif standard de leur ville.'}
          </div>
        ) : (
          <div className="space-y-2.5">
            {clients.data.map((c) => (
              <ClientTarifsCard
                key={c.id}
                client={c}
                can={can}
                version={version}
                onAjouter={(client) => setAjout({ open: true, clientId: client.id })}
                onModifier={(client, lignes) => setEdition({ client, lignes })}
                onSupprimer={(client, lignes) => setSuppression({ client, lignes })}
              />
            ))}
          </div>
        )}

        {clients.total > 0 && (
          <DataTablePagination meta={clients} currentParams={params} pageSizeOptions={[10, 15, 25, 50]} />
        )}
      </div>

      <AjouterTarifsDialog
        open={ajout.open}
        clientId={ajout.clientId}
        onClose={() => setAjout({ open: false, clientId: null })}
        onSaved={rafraichir}
      />

      <ModifierTarifsDialog
        client={edition.client}
        lignes={edition.lignes}
        onClose={() => setEdition({ client: null, lignes: [] })}
        onSaved={rafraichir}
      />

      <ConfirmDeleteDialog
        open={suppression.client !== null}
        onOpenChange={(open) => !open && !deleting && setSuppression({ client: null, lignes: [] })}
        title={`Supprimer ${suppression.lignes.length} tarif(s) négocié(s)`}
        description={`${suppression.client?.nom ?? ''} : ${suppression.lignes.map((l) => l.ville_nom).slice(0, 8).join(', ')}${suppression.lignes.length > 8 ? '…' : ''}. Ces villes repasseront au tarif standard pour les prochains colis. Les colis existants, factures et paiements ne sont pas modifiés.`}
        onConfirm={confirmerSuppression}
        isLoading={deleting}
      />
    </AuthenticatedLayout>
  );
}
