import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Layers, Loader2, Map as MapIcon, MapPin, MapPinOff, Pencil, Plus, Trash2, Truck, Undo2, Warehouse } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TarifsTabs } from '@/components/tarifs/TarifsTabs';
import { CodeZone, RegimeBadge } from '@/components/zones/RegimeBadge';
import { ZoneFormFields, type ZoneFormData } from '@/components/zones/ZoneFormFields';
import { nomVille, type Paginee, type VillePanneau, type ZoneLigne, zoneJson } from '@/components/zones/types';

type Onglet = 'toutes' | 'locale' | 'nationale' | 'vides' | 'corbeille';

interface Props {
  zones: LaravelPaginationMeta & { data: ZoneLigne[] };
  stats: { zones: number; locales: number; nationales: number; vides: number; corbeille: number; villes: number; villes_couvertes: number; sans_zone: number };
  filters: { onglet: Onglet; search: string; per_page: number };
  can: { manage: boolean };
}

export default function ZonesIndex({ zones, stats, filters, can }: Props) {
  const [creation, setCreation] = useState(false);
  const [apercu, setApercu] = useState<ZoneLigne | null>(null);
  const [suppression, setSuppression] = useState<ZoneLigne | null>(null);
  const [busy, setBusy] = useState(false);
  const corbeille = filters.onglet === 'corbeille';
  const params = { ...filters };

  const onglet = (v: string) =>
    router.get('/zones', { ...params, onglet: v === 'toutes' ? undefined : v, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
  const options = (apres?: () => void) => ({
    preserveScroll: true,
    onStart: () => setBusy(true),
    onFinish: () => setBusy(false),
    onSuccess: () => apres?.(),
  });

  return (
    <AuthenticatedLayout title="Zones logistiques">
      <Head title="Zones logistiques - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Zones logistiques"
          description="Regroupez les villes en zones : local direct (Agence Témara, BL) ou national partenaire (BE)."
          icon={MapIcon}
          actions={can.manage ? (
            <Button size="sm" className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]" onClick={() => setCreation(true)}>
              <Plus className="size-3.5" /> Nouvelle zone
            </Button>
          ) : undefined}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Zones actives" value={stats.zones} description={`${stats.locales} locales · ${stats.nationales} nationales`} icon={Layers} />
          <StatsCard title="Villes couvertes" value={stats.villes_couvertes.toLocaleString('fr-FR')} description={`sur ${stats.villes.toLocaleString('fr-FR')} villes actives`} icon={MapPin} />
          <StatsCard title="Villes sans zone" value={stats.sans_zone.toLocaleString('fr-FR')} description="À classer depuis la page d’une zone" icon={MapPinOff} iconColor="text-[#ff8806]" />
          <StatsCard title="Zones vides" value={stats.vides} description="Aucune ville rattachée" icon={Warehouse} />
        </div>

        <TarifsTabs
          label="Filtrer les zones"
          value={filters.onglet}
          onValueChange={onglet}
          onglets={[
            { value: 'toutes', label: 'Toutes', count: stats.zones, icon: <Layers className="size-3.5" /> },
            { value: 'locale', label: 'Local direct · BL', count: stats.locales, icon: <Truck className="size-3.5" /> },
            { value: 'nationale', label: 'National · BE', count: stats.nationales, icon: <MapIcon className="size-3.5" /> },
            { value: 'vides', label: 'Vides', count: stats.vides, icon: <Warehouse className="size-3.5" />, tone: 'muted' },
            { value: 'corbeille', label: 'Corbeille', count: stats.corbeille, icon: <Trash2 className="size-3.5" />, tone: 'danger' },
          ]}
        />

        <PageToolbar searchPlaceholder="Zone (nom ou code) ou ville rattachée…" initialSearch={filters.search} currentParams={params} />

        <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
          <Table className="min-w-[860px] text-xs">
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="pl-4">Zone</TableHead>
                <TableHead className="w-44">Régime</TableHead>
                <TableHead className="w-24 text-center">Villes</TableHead>
                <TableHead>Aperçu des villes</TableHead>
                <TableHead className="w-32 pr-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {zones.data.map((z) => (
                <TableRow key={z.id}>
                  <TableCell className="pl-4">
                    <div className="flex items-center gap-2.5">
                      <CodeZone code={z.code} />
                      <div className="min-w-0">
                        <div className="truncate font-semibold">{z.name}</div>
                        {z.description && <div className="max-w-80 truncate text-[11px] text-muted-foreground">{z.description}</div>}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell><RegimeBadge type={z.type} /></TableCell>
                  <TableCell className="text-center">
                    <span className={`inline-flex min-w-8 justify-center rounded-full px-2 py-0.5 font-mono text-[11px] font-semibold ${z.villes_count > 0
                      ? 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'}`}>
                      {z.villes_count.toLocaleString('fr-FR')}
                    </span>
                  </TableCell>
                  <TableCell>
                    {z.apercu.length === 0
                      ? <span className="text-muted-foreground">—</span>
                      : (
                        <div className="flex flex-wrap items-center gap-1">
                          {z.apercu.map((v) => (
                            <span key={v.id} className="rounded-full border border-border bg-muted/40 px-2 py-0.5 text-[10px]">{nomVille(v)}</span>
                          ))}
                          {z.villes_count > z.apercu.length && (
                            <span className="text-[10px] text-muted-foreground">+ {(z.villes_count - z.apercu.length).toLocaleString('fr-FR')} autres</span>
                          )}
                        </div>
                      )}
                  </TableCell>
                  <TableCell className="pr-4 text-right">
                    <span className="inline-flex gap-0.5">
                      {corbeille ? (
                        can.manage && (
                          <Button size="sm" variant="ghost" className="h-7 rounded-md px-2 text-[11px]" disabled={busy} aria-label={`Restaurer ${z.code}`}
                            onClick={() => router.post(`/zones/${z.id}/restaurer`, {}, options())}>
                            <Undo2 className="size-3.5" /> Restaurer
                          </Button>
                        )
                      ) : (
                        <>
                          <Button size="icon" variant="ghost" className="size-7" aria-label={`Voir les villes de ${z.code}`} title="Voir les villes" onClick={() => setApercu(z)}>
                            <Eye className="size-3.5 text-sky-600" />
                          </Button>
                          {can.manage && (
                            <>
                              <Button size="icon" variant="ghost" className="size-7" aria-label={`Modifier ${z.code}`} title="Modifier" render={<Link href={`/zones/${z.id}/edit`} />}>
                                <Pencil className="size-3.5 text-[#ff8806]" />
                              </Button>
                              <Button size="icon" variant="ghost" className="size-7" aria-label={`Mettre ${z.code} à la corbeille`} title="Mettre à la corbeille" onClick={() => setSuppression(z)}>
                                <Trash2 className="size-3.5 text-destructive" />
                              </Button>
                            </>
                          )}
                        </>
                      )}
                    </span>
                  </TableCell>
                </TableRow>
              ))}
              {zones.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="py-12 text-center text-muted-foreground">
                    {filters.search ? 'Aucune zone ne correspond à la recherche.' : corbeille ? 'La corbeille est vide.' : 'Aucune zone. Créez la première pour regrouper vos villes.'}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        {zones.total > 0 && <DataTablePagination meta={zones} currentParams={params} pageSizeOptions={[10, 25, 50, 100]} />}
      </div>

      <CreationZoneDialog open={creation} onClose={() => setCreation(false)} />
      <VillesZoneDialog zone={apercu} onClose={() => setApercu(null)} />
      <ConfirmDeleteDialog
        open={suppression !== null}
        onOpenChange={(o) => !o && !busy && setSuppression(null)}
        title={`Mettre la zone ${suppression?.code ?? ''} à la corbeille`}
        description={`Ses ${suppression?.villes_count ?? 0} ville(s) ne sont pas supprimées : elles sont détachées et deviennent « sans zone ». Les colis existants gardent leur code de zone figé.`}
        isLoading={busy}
        onConfirm={() => suppression && router.delete(`/zones/${suppression.id}`, options(() => setSuppression(null)))}
      />
    </AuthenticatedLayout>
  );
}

function CreationZoneDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const form = useForm<ZoneFormData>({ name: '', code: '', type: 'nationale', description: '' });
  const fermer = () => { form.reset(); form.clearErrors(); onClose(); };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && !form.processing && fermer()}>
      <DialogContent className="sm:max-w-xl">
        <form onSubmit={(e) => { e.preventDefault(); form.post('/zones', { onSuccess: () => form.reset() }); }} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Nouvelle zone</DialogTitle>
            <DialogDescription>Le nom et le code doivent être uniques. Vous rattacherez ensuite les villes depuis la page de la zone.</DialogDescription>
          </DialogHeader>
          <ZoneFormFields data={form.data} errors={form.errors} onChange={(c, v) => form.setData((d) => ({ ...d, [c]: v }))} />
          <DialogFooter>
            <Button type="button" variant="outline" size="sm" onClick={fermer} disabled={form.processing}>Annuler</Button>
            <Button type="submit" size="sm" className="bg-[#004c68] text-white hover:bg-[#00384d]" disabled={form.processing}>
              {form.processing && <Loader2 className="size-3.5 animate-spin" />} Créer et rattacher des villes
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/** « Voir villes » (WinningExpress) : toutes les villes de la zone, sans quitter la liste. */
function VillesZoneDialog({ zone, onClose }: { zone: ZoneLigne | null; onClose: () => void }) {
  const [villes, setVilles] = useState<VillePanneau[] | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [zoneChargee, setZoneChargee] = useState<number | null>(null);

  if (zone && zoneChargee !== zone.id) {
    setZoneChargee(zone.id);
    setVilles(null);
    setErreur(null);
    zoneJson<Paginee<VillePanneau>>(`/zones/${zone.id}/villes?panneau=zone&per_page=100`)
      .then((d) => setVilles(d.data))
      .catch((e: Error) => setErreur(e.message));
  }

  return (
    <Dialog open={zone !== null} onOpenChange={(o) => { if (!o) { onClose(); setZoneChargee(null); } }}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">{zone && <CodeZone code={zone.code} />} {zone?.name}</DialogTitle>
          <DialogDescription>{zone ? `${zone.villes_count.toLocaleString('fr-FR')} ville(s) rattachée(s), par ordre alphabétique.` : ''}</DialogDescription>
        </DialogHeader>
        <div className="max-h-[60vh] overflow-y-auto">
          {erreur && <p role="alert" className="text-xs text-destructive">{erreur}</p>}
          {!villes && !erreur && <div className="flex justify-center py-8"><Loader2 className="size-5 animate-spin text-muted-foreground" /></div>}
          {villes && villes.length === 0 && <p className="py-8 text-center text-xs text-muted-foreground">Aucune ville dans cette zone.</p>}
          {villes && villes.length > 0 && (
            <div className="grid gap-1.5 sm:grid-cols-2 md:grid-cols-3">
              {villes.map((v) => (
                <div key={v.id} className="flex items-center justify-between gap-2 rounded-md border border-border px-2.5 py-1.5 text-xs">
                  <span className="truncate font-medium">{v.nom}</span>
                  {v.nom_ar && <span dir="rtl" lang="ar" className="shrink-0 text-muted-foreground">{v.nom_ar}</span>}
                </div>
              ))}
            </div>
          )}
          {zone && villes && zone.villes_count > villes.length && (
            <p className="mt-3 text-[11px] text-muted-foreground">
              {villes.length} premières affichées — <Link href={`/zones/${zone.id}/edit`} className="underline">ouvrir la zone</Link> pour la liste complète.
            </p>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
