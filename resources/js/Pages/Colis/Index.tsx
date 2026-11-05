import { useState, type ReactNode } from 'react';
import { Head, router } from '@inertiajs/react';
import {
  ArrowDown, ArrowUp, ArrowUpDown, Ban, CalendarClock, CheckCircle2, Clock, Edit, History, MapPin, Package, PackageOpen,
  Layers, MessageSquareText, Phone, Plus, Printer, RefreshCw, Repeat, RotateCcw, Store, Trash2, Truck, Undo2, Wallet, Warehouse, XCircle,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { StatsCard } from '@/components/common/StatsCard';
import { PageToolbar } from '@/components/common/PageToolbar';
import { FilterDropdown } from '@/components/common/FilterDropdown';
import { ConfirmDeleteDialog } from '@/components/common/ConfirmDeleteDialog';
import { DataTablePagination, type LaravelPaginationMeta } from '@/components/common/DataTablePagination';
import { ActionGroupee, BarreSelection, CaseACocher, useSelection } from '@/components/common/SelectionGroupee';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CartesOnglets } from '@/components/common/CartesOnglets';
import { ChargementTableau, useChargementPage } from '@/components/common/ChargementTableau';
import { Copiable } from '@/components/common/Copiable';
import { ColisFormDialog } from './ColisFormDialog';
import { NotesColisPanel } from './NotesColisPanel';
import { ApercuImpression } from '@/components/bons/ApercuImpression';
import { ChangeStatutDialog } from './ChangeStatutDialog';
import { SuiviColisDialog } from './SuiviColisDialog';

type Onglet = string;

interface ClientOption { id: number; nom_complet: string; telephone?: string; businesses?: Array<{ id: number; name: string; user_id: number }> }
interface VilleOption { id: number; name_fr: string; code_zone?: string }
interface LivreurOption { id: number; nom_complet: string; telephone?: string }

interface ColisItem {
  /** Capacités calculées côté serveur par CommandPolicy. */
  can?: { update: boolean; updateState: boolean; delete: boolean; note?: boolean };
  id: number;
  code: string;
  produit?: string;
  quantite: number;
  destinataire_nom: string;
  destinataire_telephone: string;
  destinataire_adresse?: string;
  destinataire_ville: string;
  destinataire_ville_id?: number;
  destinataire_zone_code?: string;
  prix_brut: number;
  frais_livraison_client: number;
  montant_supplementaire?: number;
  montant_emballage?: number;
  type_tarif_client?: string | null;
  state: string;
  state_group: string;
  client_id: number;
  business_id?: number;
  business_name?: string;
  nom_cliente?: string;
  livreur_id?: number;
  livreur_nom?: string;
  livreur_telephone?: string;
  ouverture_autorisee: boolean;
  echange: boolean;
  note?: string;
  created_at: string;
  updated_at?: string;
  livre_at?: string;
  deleted_at?: string | null;
  notes_count?: number;
  derniere_note?: string | null;
  derniere_note_auteur?: string | null;
  client?: { id: number; nom_complet: string; email: string; telephone?: string };
  business?: { id: number; name: string };
  livreur?: { id: number; nom_complet: string; telephone?: string };
  ville?: { id: number; name_fr: string; code_zone?: string };
}

interface Props {
  canCreate?: boolean;
  canSetManualFee?: boolean;
  canBulk?: boolean;
  colis: LaravelPaginationMeta & { data: ColisItem[] };
  clients: ClientOption[];
  villes: VilleOption[];
  livreurs: LivreurOption[];
  onglets: Record<string, number>;
  groupes: { code: string; nom: string; couleur: string | null; description: string | null }[];
  stats: { total: number; livres: number; en_cours: number; retours: number; taux_livraison: number; total_crbt: number };
  filters: { search?: string; onglet: Onglet; ville_id?: string; client_id?: string; livreur_id?: string; sort: string; direction: 'asc' | 'desc'; per_page: number };
}

/** Habillage des groupes d'état (onglets et badges). */
const GROUPE: Record<string, { icone: typeof Clock; badge: string }> = {
  nouveau: { icone: Clock, badge: 'border-slate-400/30 bg-slate-500/10 text-slate-700 dark:text-slate-300' },
  en_cours: { icone: Truck, badge: 'border-sky-600/25 bg-sky-600/10 text-sky-700 dark:text-sky-300' },
  planifie: { icone: CalendarClock, badge: 'border-violet-600/25 bg-violet-600/10 text-violet-700 dark:text-violet-300' },
  livre: { icone: CheckCircle2, badge: 'border-emerald-600/25 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400' },
  retour_agence: { icone: Undo2, badge: 'border-[#ff8806]/30 bg-[#ff8806]/10 text-[#c26200] dark:text-[#ff8806]' },
  retour_stock: { icone: Warehouse, badge: 'border-amber-700/30 bg-amber-700/10 text-amber-800 dark:text-amber-300' },
  retour_client: { icone: Store, badge: 'border-purple-600/25 bg-purple-600/10 text-purple-700 dark:text-purple-300' },
  echec: { icone: XCircle, badge: 'border-destructive/25 bg-destructive/10 text-destructive' },
};

const dh = (v: number | string | null | undefined) => `${Number(v ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

function dateCourte(d?: string | null) {
  if (!d) return '—';
  const x = new Date(d);
  if (Number.isNaN(x.getTime())) return d;
  return `${x.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' })} ${x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
}

/** « il y a 5 min », « hier 14:20 », sinon date courte. */
function depuis(d?: string | null) {
  if (!d) return '—';
  const x = new Date(d);
  const s = (Date.now() - x.getTime()) / 1000;
  if (s < 60) return 'à l’instant';
  if (s < 3600) return `il y a ${Math.floor(s / 60)} min`;
  if (s < 86400 && new Date().getDate() === x.getDate()) return `aujourd’hui ${x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
  if (s < 172800) return `hier ${x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
  return dateCourte(d);
}

export default function ColisIndex({ colis, clients = [], villes = [], livreurs = [], onglets, groupes, stats, filters, canCreate = false, canSetManualFee = false, canBulk = false }: Props) {
  const [formOpen, setFormOpen] = useState(false);
  const [editingColis, setEditingColis] = useState<ColisItem | null>(null);
  const [statutColis, setStatutColis] = useState<ColisItem | null>(null);
  const [suiviId, setSuiviId] = useState<number | null>(null);
  const [notesId, setNotesId] = useState<number | null>(null);
  const [etiquettes, setEtiquettes] = useState<{ ids: number[]; titre: string } | null>(null);
  const chargement = useChargementPage();
  const [confirmation, setConfirmation] = useState<{ action: 'corbeille' | 'restaurer'; ids: number[]; libelle: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const corbeille = filters.onglet === 'corbeille';
  const params: Record<string, unknown> = { ...filters };
  const selection = useSelection(colis.data.map((c) => c.id));

  const naviguer = (changes: Record<string, unknown>) =>
    router.get('/colis', { ...params, ...changes, page: 1 }, { preserveState: true, preserveScroll: true, replace: true, only: ['colis', 'onglets', 'stats', 'filters'] });

  const trier = (col: string) =>
    naviguer({ sort: col, direction: filters.sort === col && filters.direction === 'desc' ? 'asc' : 'desc' });

  const executer = () => {
    if (!confirmation) return;
    router.post('/colis/groupe', { action: confirmation.action, ids: confirmation.ids }, {
      preserveScroll: true,
      only: ['colis', 'onglets', 'stats', 'flash'],
      onStart: () => setBusy(true),
      onSuccess: () => selection.vider(),
      onFinish: () => { setBusy(false); setConfirmation(null); },
    });
  };

  const Tri = ({ col, children }: { col: string; children: ReactNode }) => (
    <button type="button" onClick={() => trier(col)} className="inline-flex items-center gap-1 hover:text-foreground">
      {children}
      {filters.sort === col ? (filters.direction === 'desc' ? <ArrowDown className="size-3" /> : <ArrowUp className="size-3" />) : <ArrowUpDown className="size-3 opacity-40" />}
    </button>
  );

  return (
    <AuthenticatedLayout title="Colis">
      <Head title="Colis - OUAISS EXPRESS" />

      <div className="space-y-6">
        <PageHeader
          title="Colis"
          description="Tous les colis, de l’enregistrement à la livraison ou au retour, avec le détail du CRBT."
          icon={Package}
          actions={canCreate && (
            <Button onClick={() => { setEditingColis(null); setFormOpen(true); }} size="sm" className="h-8 bg-[#004c68] text-xs text-white hover:bg-[#00384d]">
              <Plus className="size-3.5" /> Nouveau colis
            </Button>
          )}
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatsCard title="Colis" value={stats.total.toLocaleString('fr-FR')} description={`${(onglets.corbeille ?? 0).toLocaleString('fr-FR')} en corbeille`} icon={Package} />
          <StatsCard title="Taux de livraison" value={`${stats.taux_livraison} %`} description={`${stats.livres.toLocaleString('fr-FR')} colis livrés`} icon={CheckCircle2} iconColor="text-emerald-600 dark:text-emerald-400" />
          <StatsCard title="En cours & planifiés" value={stats.en_cours.toLocaleString('fr-FR')} description={`${stats.retours.toLocaleString('fr-FR')} en retour ou en échec`} icon={Truck} iconColor="text-[#ff8806]" />
          <StatsCard title="CRBT des colis livrés" value={`${dh(stats.total_crbt)} DH`} description="Montant encaissé auprès des destinataires" icon={Wallet} />
        </div>

        <CartesOnglets
          label="Groupes de colis"
          colonnes="sm:grid-cols-5 2xl:grid-cols-10"
          value={filters.onglet}
          onChange={(v) => naviguer({ onglet: v })}
          onglets={[
            { value: 'tous', label: 'Tous les colis', count: onglets.tous ?? 0, couleur: '#004c68', icon: <Package />, description: 'Hors corbeille' },
            ...groupes.map((g) => {
              const I = GROUPE[g.code]?.icone ?? Layers;
              return { value: g.code, label: g.nom, count: onglets[g.code] ?? 0, couleur: g.couleur, description: g.description, icon: <I /> };
            }),
            { value: 'corbeille', label: 'Corbeille', count: onglets.corbeille ?? 0, couleur: '#64748b', icon: <Trash2 />, description: 'Supprimés, restaurables' },
          ]}
        />

        <PageToolbar searchPlaceholder="Code, destinataire, téléphone, ville, produit, enseigne…" initialSearch={filters.search ?? ''} currentParams={params}>
          {clients.length > 1 && (
            <FilterDropdown
              label="Expéditeur"
              value={filters.client_id ?? 'all'}
              options={[{ value: 'all', label: 'Tous' }, ...clients.map((c) => ({ value: String(c.id), label: c.businesses?.[0]?.name ? `${c.businesses[0].name} — ${c.nom_complet}` : c.nom_complet }))]}
              onChange={(v) => naviguer({ client_id: v === 'all' ? undefined : v })}
            />
          )}
          {livreurs.length > 0 && (
            <FilterDropdown
              label="Livreur"
              value={filters.livreur_id ?? 'all'}
              options={[{ value: 'all', label: 'Tous' }, ...livreurs.map((l) => ({ value: String(l.id), label: l.nom_complet }))]}
              onChange={(v) => naviguer({ livreur_id: v === 'all' ? undefined : v })}
            />
          )}
        </PageToolbar>

        {canBulk && (
          <BarreSelection taille={selection.taille} onVider={selection.vider}>
            <ActionGroupee icone={<Printer className="size-3.5" />} onClick={() => setEtiquettes({ ids: selection.ids, titre: `${selection.taille} étiquette(s) colis` })}>Imprimer les étiquettes</ActionGroupee>
            {corbeille
              ? <ActionGroupee icone={<RotateCcw className="size-3.5" />} onClick={() => setConfirmation({ action: 'restaurer', ids: selection.ids, libelle: `${selection.taille} colis` })}>Restaurer</ActionGroupee>
              : <ActionGroupee danger icone={<Trash2 className="size-3.5" />} onClick={() => setConfirmation({ action: 'corbeille', ids: selection.ids, libelle: `${selection.taille} colis` })}>Mettre en corbeille</ActionGroupee>}
          </BarreSelection>
        )}

        <div className="relative overflow-x-auto rounded-lg border border-border bg-card">
          <ChargementTableau actif={chargement} />
          <Table className="min-w-[1240px] text-xs">
            <TableHeader className="bg-muted/40">
              <TableRow className="hover:bg-transparent">
                {canBulk && (
                  <TableHead className="w-10 pl-4">
                    <CaseACocher label="Tout sélectionner" coche={selection.tous} partiel={selection.partiel} onChange={selection.basculerTous} />
                  </TableHead>
                )}
                <TableHead className={`w-40 ${canBulk ? '' : 'pl-4'}`}><Tri col="code">Référence</Tri></TableHead>
                <TableHead className="w-44">Expéditeur</TableHead>
                <TableHead><Tri col="destinataire_nom">Destinataire</Tri></TableHead>
                <TableHead className="w-28 text-right"><Tri col="prix_brut">CRBT</Tri></TableHead>
                <TableHead className="w-48">Statut · livreur</TableHead>
                <TableHead className="w-56">Notes</TableHead>
                <TableHead className="w-32"><Tri col="updated_at">Mise à jour</Tri></TableHead>
                <TableHead className="w-32 pr-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {colis.data.map((c) => {
                const g = GROUPE[c.state_group] ?? { icone: Layers, badge: 'border-border bg-muted text-muted-foreground' };
                const enseigne = c.business?.name ?? c.business_name;
                const frais = Number(c.frais_livraison_client ?? 0);
                const extra = Number(c.montant_supplementaire ?? 0);
                const emballage = Number(c.montant_emballage ?? 0);
                const livreur = c.livreur?.nom_complet ?? c.livreur_nom;
                return (
                  <TableRow key={c.id} data-state={selection.estChoisi(c.id) ? 'selected' : undefined} className="align-top">
                    {canBulk && (
                      <TableCell className="pl-4 pt-3.5">
                        <CaseACocher label={`Sélectionner ${c.code}`} coche={selection.estChoisi(c.id)} onChange={() => selection.basculer(c.id)} />
                      </TableCell>
                    )}

                    {/* Référence : code copiable, produit, options */}
                    <TableCell className={canBulk ? '' : 'pl-4'}>
                      <Copiable valeur={c.code} className="font-mono text-[12.5px] font-bold text-[#004c68] dark:text-cyan-300" />
                      <p className="mt-1 line-clamp-1 text-[11px] text-muted-foreground" title={c.produit}>{c.produit || 'Produit non renseigné'} <span className="text-foreground/70">×{c.quantite}</span></p>
                      {(c.echange || c.ouverture_autorisee) && (
                        <div className="mt-1 flex flex-wrap gap-1">
                          {c.echange && <span className="inline-flex items-center gap-0.5 text-[10px] font-medium text-violet-700 dark:text-violet-300"><Repeat className="size-2.5" /> Échange</span>}
                          {c.ouverture_autorisee && <span className="inline-flex items-center gap-0.5 text-[10px] font-medium text-sky-700 dark:text-sky-300"><PackageOpen className="size-2.5" /> Ouverture</span>}
                        </div>
                      )}
                    </TableCell>

                    {/* Expéditeur */}
                    <TableCell className="max-w-[176px]">
                      <p className="flex items-center gap-1 truncate font-semibold"><Store className="size-3 shrink-0 text-[#ff8806]" /><span className="truncate">{enseigne ?? '—'}</span></p>
                      <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{c.client?.nom_complet ?? c.nom_cliente ?? 'Sans expéditeur'}</p>
                    </TableCell>

                    {/* Destinataire */}
                    <TableCell className="max-w-[240px]">
                      <p className="truncate font-semibold">{c.destinataire_nom || '—'}</p>
                      <div className="mt-0.5 flex items-center gap-2 text-[11px] text-muted-foreground">
                        {c.destinataire_telephone
                          ? <Copiable valeur={c.destinataire_telephone} className="font-mono"><span className="inline-flex items-center gap-1"><Phone className="size-3" />{c.destinataire_telephone}</span></Copiable>
                          : '—'}
                      </div>
                      <p className="mt-0.5 flex items-center gap-1 text-[11px]">
                        <MapPin className="size-3 shrink-0 text-muted-foreground" />
                        <span className="font-medium">{c.ville?.name_fr ?? c.destinataire_ville ?? '—'}</span>
                        {c.destinataire_zone_code && <span className="rounded-sm border border-border px-1 text-[9px] font-semibold text-muted-foreground">{c.destinataire_zone_code}</span>}
                      </p>
                      {c.destinataire_adresse && <p className="truncate text-[10px] text-muted-foreground" title={c.destinataire_adresse}>{c.destinataire_adresse}</p>}
                    </TableCell>

                    {/* CRBT détaillé */}
                    <TableCell className="text-right tabular-nums">
                      <p className="text-[13px] font-bold">{dh(c.prix_brut)}</p>
                      <p className="text-[10px] text-muted-foreground" title={`Frais ${dh(frais)} · Supplément ${dh(extra)} · Emballage ${dh(emballage)}`}>
                        − {dh(frais + extra + emballage)} frais
                      </p>
                      <p className="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">{dh(Number(c.prix_brut) - frais - extra - emballage)} net</p>
                    </TableCell>

                    {/* Statut + livreur */}
                    <TableCell>
                      <span className={`inline-flex max-w-full items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10.5px] font-semibold ${g.badge}`}>
                        <g.icone className="size-3 shrink-0" /><span className="truncate">{c.state}</span>
                      </span>
                      <p className="mt-1 flex items-center gap-1 truncate text-[11px] text-muted-foreground">
                        <Truck className="size-3 shrink-0" />{livreur ? <span className="truncate text-foreground/80">{livreur}</span> : 'Non assigné'}
                      </p>
                      {c.livre_at && <p className="text-[10px] text-emerald-700 dark:text-emerald-400">Livré {dateCourte(c.livre_at)}</p>}
                    </TableCell>

                    {/* Notes : dernier message + ouverture du fil */}
                    <TableCell className="max-w-[224px]">
                      <button type="button" onClick={() => setNotesId(c.id)} className="group/note flex w-full items-start gap-2 rounded-md p-1 text-left hover:bg-muted/50">
                        <span className={`relative mt-0.5 shrink-0 ${(c.notes_count ?? 0) > 0 ? 'text-[#004c68] dark:text-cyan-300' : 'text-muted-foreground/60'}`}>
                          <MessageSquareText className="size-4" />
                          {(c.notes_count ?? 0) > 0 && <span className="absolute -right-1.5 -top-1.5 rounded-full bg-[#ff8806] px-1 text-[9px] font-bold leading-[14px] text-white">{c.notes_count}</span>}
                        </span>
                        {c.derniere_note
                          ? <span className="min-w-0"><span className="line-clamp-2 text-[11px]">{c.derniere_note}</span><span className="block truncate text-[10px] text-muted-foreground">{c.derniere_note_auteur}</span></span>
                          : <span className="text-[11px] text-muted-foreground group-hover/note:text-foreground">Ajouter une note</span>}
                      </button>
                    </TableCell>

                    {/* Dates */}
                    <TableCell className="whitespace-nowrap text-[11px]">
                      <p className="font-medium" title={dateCourte(c.updated_at)}>{depuis(c.updated_at)}</p>
                      <p className="text-[10px] text-muted-foreground">Ajouté {dateCourte(c.created_at)}</p>
                      {c.deleted_at && <p className="text-[10px] text-destructive">Corbeille {dateCourte(c.deleted_at)}</p>}
                    </TableCell>

                    <TableCell className="pr-4 text-right">
                      <span className="inline-flex gap-0.5">
                        <Button size="icon" variant="ghost" className="size-7" title="Imprimer l’étiquette" aria-label={`Étiquette de ${c.code}`} onClick={() => setEtiquettes({ ids: [c.id], titre: `Étiquette ${c.code}` })}><Printer className="size-3.5 text-[#004c68] dark:text-cyan-300" /></Button>
                        <Button size="icon" variant="ghost" className="size-7" title="Suivi & historique" aria-label={`Suivi de ${c.code}`} onClick={() => setSuiviId(c.id)}><History className="size-3.5" /></Button>
                        {c.can?.updateState && <Button size="icon" variant="ghost" className="size-7" title="Changer le statut" aria-label={`Statut de ${c.code}`} onClick={() => setStatutColis(c)}><RefreshCw className="size-3.5 text-[#004c68] dark:text-cyan-300" /></Button>}
                        {c.can?.update && <Button size="icon" variant="ghost" className="size-7" title="Modifier" aria-label={`Modifier ${c.code}`} onClick={() => { setEditingColis(c); setFormOpen(true); }}><Edit className="size-3.5 text-[#ff8806]" /></Button>}
                        {c.can?.delete && (corbeille
                          ? <Button size="icon" variant="ghost" className="size-7" title="Restaurer" aria-label={`Restaurer ${c.code}`} onClick={() => setConfirmation({ action: 'restaurer', ids: [c.id], libelle: c.code })}><RotateCcw className="size-3.5 text-emerald-600" /></Button>
                          : <Button size="icon" variant="ghost" className="size-7" title="Mettre en corbeille" aria-label={`Corbeille ${c.code}`} onClick={() => setConfirmation({ action: 'corbeille', ids: [c.id], libelle: c.code })}><Trash2 className="size-3.5 text-destructive" /></Button>)}
                      </span>
                    </TableCell>
                  </TableRow>
                );
              })}
              {colis.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={canBulk ? 9 : 8} className="py-14 text-center text-muted-foreground">
                    {corbeille ? <><Ban className="mx-auto mb-2 size-5 opacity-50" />La corbeille est vide.</> : 'Aucun colis pour ces filtres.'}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        {colis.total > 0 && <DataTablePagination meta={colis} currentParams={params} pageSizeOptions={[25, 50, 100]} />}
      </div>

      <ColisFormDialog open={formOpen} onOpenChange={setFormOpen} colis={editingColis} clients={clients} villes={villes} livreurs={livreurs} canSetManualFee={canSetManualFee} />
      <ChangeStatutDialog open={statutColis !== null} onOpenChange={(o) => !o && setStatutColis(null)} colis={statutColis} livreurs={livreurs} />
      <SuiviColisDialog colisId={suiviId} onClose={() => setSuiviId(null)} />
      <NotesColisPanel colisId={notesId} onClose={() => setNotesId(null)} />
      <ApercuImpression
        url={etiquettes ? `/colis/etiquettes?ids=${etiquettes.ids.join(',')}` : null}
        titre={etiquettes?.titre ?? ''}
        format="étiquette 100 × 150 mm"
        onClose={() => setEtiquettes(null)}
      />
      <ConfirmDeleteDialog
        open={confirmation !== null}
        onOpenChange={(o) => !o && !busy && setConfirmation(null)}
        title={confirmation?.action === 'restaurer' ? 'Restaurer depuis la corbeille' : 'Mettre en corbeille'}
        description={confirmation?.action === 'restaurer'
          ? `Restaurer ${confirmation?.libelle} ? Ils reviennent dans leur onglet d’origine.`
          : `Mettre ${confirmation?.libelle} en corbeille ? Rien n’est effacé : ils restent consultables et restaurables depuis l’onglet « Corbeille ».`}
        isLoading={busy}
        onConfirm={executer}
      />
    </AuthenticatedLayout>
  );
}
