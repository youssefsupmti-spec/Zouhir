import { useState, type FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Copy, KeyRound, Plug, RefreshCw, ShieldCheck, Trash2, XCircle } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Log { id: number; direction: string; endpoint: string; http_status: number | null; processed: boolean; signature_valide: boolean; error_message: string | null; ip: string | null; created_at: string; command_id: number | null }
interface Courant {
  id: number; code: string; name: string; is_active: boolean; api_endpoint: string | null; format_payload: string;
  jeton: boolean; secret: boolean; derniere_synchro: string | null;
  mappings: { id: number; partner_status: string; colis_status: string; statut_id: number | null; notes: string | null }[];
  logs: Log[];
  stats: { total: number | null; ok: number | null } | null;
}

interface Props {
  partenaires: { id: number; code: string; name: string; is_active: boolean }[];
  courant: Courant | null;
  formats: Record<string, string>;
  statutsStandard: Record<string, string>;
  statuts: { id: number; libelle: string }[];
  baseUrl: string;
  revele: { type: 'jeton' | 'secret'; valeur: string; partenaire: number } | null;
  can: { manage: boolean };
}

const SELECT = 'flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs';

const ROUTES = [
  ['POST', '/external/parcels/import', 'Import de colis (lot)', true],
  ['GET', '/external/parcels/{tracking}/track', 'Suivi d’un colis', false],
  ['POST', '/external/parcels/webhook/status', 'Changement de statut', true],
  ['POST', '/external/bons-envoi/{code}/receive', 'Réception d’un bon d’envoi', true],
  ['GET', '/external/referentiel/zones-cities', 'Référentiel zones et villes', false],
  ['GET', '/external/rates/estimate', 'Estimation du tarif de transit', false],
  ['POST', '/webhooks/{code}', 'Webhook de statut (format Zakaria2, signature seule)', true],
] as const;

function copier(texte: string) {
  void navigator.clipboard?.writeText(texte);
}

function PageApi({ partenaires, courant, formats, statutsStandard, statuts, baseUrl, revele, can }: Props) {
  const cfg = useForm({ api_endpoint: courant?.api_endpoint ?? '', format_payload: courant?.format_payload ?? 'json_standard' });
  const map = useForm({ partner_status: '', statut_id: '' as string | number, notes: '' });
  const [confirmer, setConfirmer] = useState<'jeton' | 'secret' | null>(null);

  if (!courant) {
    return (
      <AuthenticatedLayout title="Passerelle API">
        <Head title="Passerelle API - OUAISS EXPRESS" />
        <PageHeader title="Passerelle API" description="Échanges automatisés avec les transporteurs partenaires." icon={Plug} />
        <div className="mt-6 rounded-xl border border-dashed border-border bg-card p-12 text-center text-sm text-muted-foreground">
          Aucun partenaire : créez-en un dans « Transporteurs partenaires » pour lui ouvrir l’API.
        </div>
      </AuthenticatedLayout>
    );
  }

  const regenerer = (type: 'jeton' | 'secret') => {
    router.post(`/partenaires/${courant.id}/cle`, { type }, { preserveScroll: true, onFinish: () => setConfirmer(null) });
  };
  const enregistrerCfg = (e: FormEvent) => { e.preventDefault(); cfg.put(`/partenaires/${courant.id}/api`, { preserveScroll: true }); };
  const ajouterMap = (e: FormEvent) => { e.preventDefault(); map.post(`/partenaires/${courant.id}/correspondances`, { preserveScroll: true, onSuccess: () => map.reset() }); };
  const total = Number(courant.stats?.total ?? 0);
  const ok = Number(courant.stats?.ok ?? 0);
  const cle = revele && revele.partenaire === courant.id ? revele : null;

  return (
    <AuthenticatedLayout title="Passerelle API">
      <Head title="Passerelle API - OUAISS EXPRESS" />
      <div className="space-y-6">
        <PageHeader
          title="Passerelle API"
          description="Jeton Bearer (stocké haché) + signature HMAC-SHA256 du corps de chaque écriture (en-tête X-Signature-SHA256)."
          icon={Plug}
          actions={
            <select aria-label="Partenaire" className={`${SELECT} w-64`} value={courant.id} onChange={(e) => router.get('/partenaires/api', { partenaire: e.target.value })}>
              {partenaires.map((p) => <option key={p.id} value={p.id}>{p.name} ({p.code}){p.is_active ? '' : ' — inactif'}</option>)}
            </select>
          }
        />

        {cle && (
          <div className="rounded-xl border border-[#f78304] bg-[#f78304]/10 p-4">
            <p className="flex items-center gap-1.5 text-sm font-semibold text-[#c26200] dark:text-[#ff8806]">
              <AlertTriangle className="size-4" /> {cle.type === 'jeton' ? 'Nouveau jeton d’API' : 'Nouveau secret de signature'} — affiché une seule fois
            </p>
            <p className="mt-1 text-xs text-muted-foreground">Transmettez-le au partenaire par un canal sûr. Il ne sera plus jamais visible ici.</p>
            <div className="mt-3 flex items-center gap-2">
              <code className="min-w-0 flex-1 truncate rounded bg-card px-2 py-1.5 font-mono text-xs">{cle.valeur}</code>
              <Button size="sm" variant="outline" className="cursor-pointer" onClick={() => copier(cle.valeur)}><Copy className="size-3.5" /> Copier</Button>
            </div>
          </div>
        )}

        <div className="grid gap-4 lg:grid-cols-3">
          <section className="rounded-xl border border-border bg-card p-5 shadow-2xs">
            <h2 className="flex items-center gap-1.5 text-sm font-bold"><KeyRound className="size-4 text-[#004c68]" /> Clés d’accès</h2>
            <ul className="mt-3 space-y-2 text-xs">
              {(['jeton', 'secret'] as const).map((t) => {
                const present = t === 'jeton' ? courant.jeton : courant.secret;
                return (
                  <li key={t} className="flex items-center justify-between gap-2 rounded-lg bg-muted/40 px-3 py-2">
                    <span className="flex items-center gap-1.5">
                      {present ? <CheckCircle2 className="size-3.5 text-emerald-600" /> : <XCircle className="size-3.5 text-muted-foreground" />}
                      {t === 'jeton' ? 'Jeton Bearer' : 'Secret HMAC'}
                    </span>
                    {can.manage && (confirmer === t ? (
                      <span className="flex gap-1">
                        <Button size="sm" className="h-6 cursor-pointer bg-[#004c68] px-2 text-[11px] text-white" onClick={() => regenerer(t)}>Confirmer</Button>
                        <Button size="sm" variant="ghost" className="h-6 cursor-pointer px-2 text-[11px]" onClick={() => setConfirmer(null)}>Annuler</Button>
                      </span>
                    ) : (
                      <Button size="sm" variant="outline" className="h-6 cursor-pointer px-2 text-[11px]" onClick={() => (present ? setConfirmer(t) : regenerer(t))}>
                        <RefreshCw className="size-3" /> {present ? 'Régénérer' : 'Générer'}
                      </Button>
                    ))}
                  </li>
                );
              })}
            </ul>
            {confirmer && <p className="mt-2 text-[11px] text-destructive">L’ancienne valeur cessera immédiatement de fonctionner.</p>}
            <div className="mt-4 grid grid-cols-2 gap-2 text-center">
              <div className="rounded-lg bg-muted/50 p-2"><p className="text-lg font-bold tabular-nums">{total}</p><p className="text-[10px] text-muted-foreground">appels 24 h</p></div>
              <div className="rounded-lg bg-muted/50 p-2"><p className="text-lg font-bold tabular-nums">{total > 0 ? Math.round((ok * 100) / total) : 0}%</p><p className="text-[10px] text-muted-foreground">traités</p></div>
            </div>
            <p className="mt-2 text-[11px] text-muted-foreground">Dernière synchronisation : {courant.derniere_synchro ?? 'jamais'}</p>
          </section>

          <section className="rounded-xl border border-border bg-card p-5 shadow-2xs">
            <h2 className="flex items-center gap-1.5 text-sm font-bold"><Plug className="size-4 text-[#004c68]" /> Configuration</h2>
            <form onSubmit={enregistrerCfg} className="mt-3 space-y-3">
              <div className="space-y-1.5">
                <Label htmlFor="cfg-url">URL de rappel du partenaire</Label>
                <Input id="cfg-url" placeholder="https://…" disabled={!can.manage} value={cfg.data.api_endpoint} onChange={(e) => cfg.setData('api_endpoint', e.target.value)} />
                {cfg.errors.api_endpoint && <p className="text-[11px] text-destructive">{cfg.errors.api_endpoint}</p>}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="cfg-format">Format des messages</Label>
                <select id="cfg-format" className={SELECT} disabled={!can.manage} value={cfg.data.format_payload} onChange={(e) => cfg.setData('format_payload', e.target.value)}>
                  {Object.entries(formats).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>
              {can.manage && <Button type="submit" size="sm" disabled={cfg.processing} className="cursor-pointer bg-[#004c68] text-white hover:bg-[#00384d]">Enregistrer</Button>}
            </form>
          </section>

          <section className="rounded-xl border border-border bg-card p-5 shadow-2xs">
            <h2 className="flex items-center gap-1.5 text-sm font-bold"><ShieldCheck className="size-4 text-[#004c68]" /> Points d’accès</h2>
            <p className="mt-1 break-all font-mono text-[10px] text-muted-foreground">{baseUrl}</p>
            <ul className="mt-3 space-y-1.5 text-[11px]">
              {ROUTES.map(([m, u, l, signe]) => (
                <li key={u} className="flex items-start gap-1.5">
                  <span className={`w-9 shrink-0 rounded px-1 text-center font-mono text-[9px] font-bold ${m === 'GET' ? 'bg-[#004c68]/10 text-[#004c68] dark:text-sky-300' : 'bg-[#f78304]/15 text-[#c26200] dark:text-[#ff8806]'}`}>{m}</span>
                  <span className="min-w-0"><span className="block break-all font-mono">{u.replace('{code}', courant.code)}</span><span className="text-muted-foreground">{l}{signe ? ' · signé' : ''}</span></span>
                </li>
              ))}
            </ul>
          </section>
        </div>

        <section className="rounded-xl border border-border bg-card p-5 shadow-2xs">
          <h2 className="text-sm font-bold">Correspondances de statuts</h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Les codes propres au partenaire sont traduits en premier ; sinon la table standard s’applique ({Object.keys(statutsStandard).join(', ')}).
          </p>
          {can.manage && (
            <form onSubmit={ajouterMap} className="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
              <Input aria-label="Statut partenaire" placeholder="Code partenaire (ex. REFUS_CLIENT)" value={map.data.partner_status} onChange={(e) => map.setData('partner_status', e.target.value)} />
              <select aria-label="Statut OUAISS" className={SELECT} value={map.data.statut_id} onChange={(e) => map.setData('statut_id', e.target.value)}>
                <option value="">Statut OUAISS EXPRESS…</option>
                {statuts.map((s) => <option key={s.id} value={s.id}>{s.libelle}</option>)}
              </select>
              <Input aria-label="Notes" placeholder="Notes" value={map.data.notes} onChange={(e) => map.setData('notes', e.target.value)} />
              <Button type="submit" disabled={map.processing} className="cursor-pointer bg-[#004c68] text-white hover:bg-[#00384d]">Ajouter</Button>
              {(map.errors.partner_status || map.errors.statut_id) && <p className="text-[11px] text-destructive sm:col-span-4">{map.errors.partner_status ?? map.errors.statut_id}</p>}
            </form>
          )}
          <div className="mt-3 overflow-x-auto rounded-md border border-border">
            <Table className="text-xs">
              <TableHeader><TableRow className="hover:bg-transparent"><TableHead className="pl-4">Code partenaire</TableHead><TableHead>Statut OUAISS EXPRESS</TableHead><TableHead>Notes</TableHead><TableHead className="pr-4" /></TableRow></TableHeader>
              <TableBody>
                {courant.mappings.map((m) => (
                  <TableRow key={m.id}>
                    <TableCell className="pl-4 font-mono">{m.partner_status}</TableCell>
                    <TableCell className="font-semibold">{m.colis_status}</TableCell>
                    <TableCell className="text-muted-foreground">{m.notes}</TableCell>
                    <TableCell className="pr-4 text-right">
                      {can.manage && <Button size="icon" variant="ghost" className="size-7 cursor-pointer" aria-label="Retirer" onClick={() => router.delete(`/partenaires/correspondances/${m.id}`, { preserveScroll: true })}><Trash2 className="size-3.5 text-destructive" /></Button>}
                    </TableCell>
                  </TableRow>
                ))}
                {courant.mappings.length === 0 && <TableRow><TableCell colSpan={4} className="py-6 text-center text-muted-foreground">Aucune correspondance propre : la table standard s’applique.</TableCell></TableRow>}
              </TableBody>
            </Table>
          </div>
        </section>

        <section className="rounded-xl border border-border bg-card p-5 shadow-2xs">
          <h2 className="text-sm font-bold">Journal des échanges <span className="font-normal text-muted-foreground">(50 derniers)</span></h2>
          <div className="mt-3 overflow-x-auto rounded-md border border-border">
            <Table className="min-w-[760px] text-xs">
              <TableHeader><TableRow className="hover:bg-transparent"><TableHead className="pl-4">Date</TableHead><TableHead>Point d’accès</TableHead><TableHead>HTTP</TableHead><TableHead>Signature</TableHead><TableHead>Résultat</TableHead><TableHead className="pr-4">IP</TableHead></TableRow></TableHeader>
              <TableBody>
                {courant.logs.map((l) => (
                  <TableRow key={l.id}>
                    <TableCell className="pl-4 whitespace-nowrap text-muted-foreground">{l.created_at?.replace('T', ' ').slice(0, 19)}</TableCell>
                    <TableCell className="font-mono">{l.endpoint}</TableCell>
                    <TableCell className={`font-mono font-semibold ${l.http_status && l.http_status < 300 ? 'text-emerald-600' : 'text-destructive'}`}>{l.http_status ?? '—'}</TableCell>
                    <TableCell>{l.signature_valide ? <CheckCircle2 className="size-3.5 text-emerald-600" /> : <XCircle className="size-3.5 text-destructive" />}</TableCell>
                    <TableCell className="max-w-72 truncate" title={l.error_message ?? ''}>{l.processed ? 'Traité' : (l.error_message ?? 'Non traité')}</TableCell>
                    <TableCell className="pr-4 font-mono text-muted-foreground">{l.ip}</TableCell>
                  </TableRow>
                ))}
                {courant.logs.length === 0 && <TableRow><TableCell colSpan={6} className="py-6 text-center text-muted-foreground">Aucun échange enregistré.</TableCell></TableRow>}
              </TableBody>
            </Table>
          </div>
        </section>
      </div>
    </AuthenticatedLayout>
  );
}

/** Une instance par partenaire : les formulaires repartent de ses valeurs à chaque changement. */
export default function PartenairesApi(props: Props) {
  return <PageApi key={props.courant?.id ?? 0} {...props} />;
}
