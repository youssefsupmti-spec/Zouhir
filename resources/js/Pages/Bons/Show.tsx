import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Database, FileText, Package } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { BadgeStatutBon, dateBon, montant } from '@/components/bons/partage';

interface Ligne {
  id: number;
  sequence: number;
  command_id: number | null;
  legacy_command_id: number | null;
  code: string | null;
  destinataire: string | null;
  telephone: string | null;
  ville: string | null;
  montant: number;
  etat_actuel: string | null;
  groupe_actuel: string | null;
  colis_supprime: boolean;
  anomalie: string | null;
}

interface Props {
  type: string;
  libelleType: string;
  urlListe: string;
  bon: {
    id: number; code: string; source_bls_id: number | null; status: string; sous_type: string | null; titulaire: string | null;
    total_colis: number; montant: number; anomalies: number; date: string | null; supprime: boolean;
    legacy_brut: Record<string, unknown> | null; traite_sans_pointage_historique: boolean; deleted_at: string | null;
  };
  statuts: Record<string, string>;
  lignes: Ligne[];
  anomalies: { id: number; code: string; gravite: string; libelle: string; statut: string }[];
}

const GROUPE: Record<string, string> = {
  livre: 'text-emerald-700 dark:text-emerald-400', retour_agence: 'text-[#c26200] dark:text-[#ff8806]', retour_stock: 'text-amber-800 dark:text-amber-300', retour_client: 'text-purple-700 dark:text-purple-300', echec: 'text-destructive', planifie: 'text-violet-700 dark:text-violet-300', en_cours: 'text-sky-700 dark:text-sky-300', nouveau: 'text-muted-foreground',
};

export default function BonShow({ libelleType, urlListe, bon, statuts, lignes, anomalies }: Props) {
  const absents = lignes.filter((l) => l.command_id === null).length;
  const parGroupe = lignes.reduce<Record<string, number>>((acc, l) => {
    if (l.groupe_actuel) acc[l.groupe_actuel] = (acc[l.groupe_actuel] ?? 0) + 1;
    return acc;
  }, {});

  return (
    <AuthenticatedLayout title={bon.code}>
      <Head title={`${bon.code} - OUAISS EXPRESS`} />

      <div className="space-y-6">
        <Link href={urlListe} className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-3.5" /> {libelleType}
        </Link>

        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-2xs">
          <div className="flex flex-wrap items-start justify-between gap-4 bg-gradient-to-r from-[#004c68] to-[#0a2533] p-5 text-white">
            <div>
              <p className="text-[11px] uppercase tracking-wider text-white/60">{libelleType}{bon.sous_type === 'livreur' ? ' · décharge livreur' : ''}</p>
              <h1 className="mt-1 font-mono text-2xl font-bold">{bon.code}</h1>
              <p className="mt-1 text-sm text-white/80">{bon.titulaire ?? '—'}</p>
            </div>
            <div className="flex flex-col items-end gap-2">
              <BadgeStatutBon statut={bon.status} libelle={statuts[bon.status] ?? bon.status} />
              {bon.supprime && <span className="rounded bg-white/15 px-2 py-0.5 text-[10px]">En corbeille depuis le {dateBon(bon.deleted_at)}</span>}
            </div>
          </div>
          <dl className="grid divide-y divide-border sm:grid-cols-4 sm:divide-x sm:divide-y-0">
            {[
              ['Date du bon', dateBon(bon.date)],
              ['Colis', String(bon.total_colis)],
              ['Montant CRBT', montant(bon.montant)],
              ['Pointage', bon.traite_sans_pointage_historique ? 'Traité sans pointage (ancien site)' : 'Aucun pointage'],
            ].map(([k, v]) => (
              <div key={k} className="p-4">
                <dt className="text-[11px] text-muted-foreground">{k}</dt>
                <dd className="mt-0.5 text-sm font-semibold">{v}</dd>
              </div>
            ))}
          </dl>
        </section>

        <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
          <section className="space-y-3">
            <h2 className="flex items-center gap-2 text-sm font-semibold"><Package className="size-4 text-[#ff8806]" /> Colis du bon</h2>
            <div className="overflow-x-auto rounded-md border border-border bg-card">
              <Table className="min-w-[720px] text-xs">
                <TableHeader>
                  <TableRow className="hover:bg-transparent">
                    <TableHead className="w-10 pl-4">#</TableHead>
                    <TableHead>Colis</TableHead>
                    <TableHead>Destinataire</TableHead>
                    <TableHead>Ville</TableHead>
                    <TableHead className="text-right">Montant</TableHead>
                    <TableHead className="pr-4">État actuel</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {lignes.map((l) => (
                    <TableRow key={l.id} className={l.command_id === null ? 'bg-[#ff8806]/5' : undefined}>
                      <TableCell className="pl-4 tabular-nums text-muted-foreground">{l.sequence}</TableCell>
                      <TableCell>
                        {l.command_id !== null
                          ? <span className="font-mono font-semibold">{l.code}</span>
                          : <span className="inline-flex items-center gap-1 font-medium text-[#c26200]"><AlertTriangle className="size-3" /> Colis #{l.legacy_command_id} introuvable</span>}
                        {l.colis_supprime && <span className="ml-1 text-[10px] text-muted-foreground">(corbeille)</span>}
                      </TableCell>
                      <TableCell>{l.destinataire ?? '—'}<div className="text-[10px] text-muted-foreground">{l.telephone}</div></TableCell>
                      <TableCell>{l.ville ?? '—'}</TableCell>
                      <TableCell className="text-right tabular-nums">{l.command_id !== null ? montant(l.montant) : '—'}</TableCell>
                      <TableCell className={`pr-4 font-medium ${GROUPE[l.groupe_actuel ?? ''] ?? ''}`}>{l.etat_actuel ?? l.anomalie ?? '—'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </section>

          <aside className="space-y-4">
            <div className="rounded-lg border border-border bg-card p-4">
              <h3 className="text-xs font-semibold">Où en sont ces colis ?</h3>
              <ul className="mt-2 space-y-1 text-xs">
                {Object.entries(parGroupe).map(([g, n]) => (
                  <li key={g} className="flex justify-between"><span className={GROUPE[g] ?? ''}>{g.replace('_', ' ')}</span><span className="tabular-nums font-semibold">{n}</span></li>
                ))}
                {absents > 0 && <li className="flex justify-between text-[#c26200]"><span>introuvables</span><span className="font-semibold">{absents}</span></li>}
              </ul>
            </div>

            {anomalies.length > 0 && (
              <div className="rounded-lg border border-[#ff8806]/30 bg-[#ff8806]/5 p-4">
                <h3 className="flex items-center gap-1.5 text-xs font-semibold text-[#c26200]"><AlertTriangle className="size-3.5" /> Anomalies de reprise ({anomalies.length})</h3>
                <ul className="mt-2 space-y-1.5 text-[11px]">
                  {anomalies.map((a) => <li key={a.id}>{a.libelle}{a.statut !== 'ouverte' && <em className="text-muted-foreground"> — {a.statut}</em>}</li>)}
                </ul>
                <Link href="/reprise" className="mt-2 inline-block text-[11px] font-medium text-[#004c68] hover:underline dark:text-cyan-300">Ouvrir la reprise des données →</Link>
              </div>
            )}

            {bon.legacy_brut && (
              <details className="rounded-lg border border-border bg-card p-4 text-xs">
                <summary className="flex cursor-pointer items-center gap-1.5 font-semibold"><Database className="size-3.5" /> Ligne d’origine (ancien site, bls #{bon.source_bls_id})</summary>
                <dl className="mt-2 space-y-1">
                  {Object.entries(bon.legacy_brut).map(([k, v]) => (
                    <div key={k} className="grid grid-cols-[70px_1fr] gap-2"><dt className="text-muted-foreground">{k}</dt><dd className="break-all font-mono">{String(v)}</dd></div>
                  ))}
                </dl>
              </details>
            )}
            <p className="flex items-start gap-1.5 text-[11px] text-muted-foreground"><FileText className="mt-0.5 size-3" /> Les informations des colis (destinataire, ville, montant) sont celles du colis au moment de la reprise : l’ancien site ne gardait pas de copie figée par bon.</p>
          </aside>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
