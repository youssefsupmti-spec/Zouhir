import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { FileStack, History, Loader2, MessageSquareText } from 'lucide-react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { dateBon } from '@/components/bons/partage';

interface Suivi {
  colis: {
    id: number; code: string; state: string; state_group: string; note_brute: string | null;
    jalons: Record<'ramasse_at' | 'expedie_at' | 'livre_at' | 'retour_agence_at' | 'retour_recu_client_at', string | null>;
  };
  historique: { id: number; de: string | null; vers: string; par: string | null; type: string | null; source: string; date: string | null }[];
  notes: { id: number; role: string; auteur: string | null; message: string; source: string; date: string | null; date_exacte: boolean }[];
  bons: { type: string; id: number; code: string; status: string; date: string | null }[];
}

const JALONS: [keyof Suivi['colis']['jalons'], string][] = [
  ['ramasse_at', 'Ramassé'], ['expedie_at', 'Mis en distribution'], ['livre_at', 'Livré'], ['retour_agence_at', 'Retour agence'], ['retour_recu_client_at', 'Retour reçu par le client'],
];
const ROLE: Record<string, string> = { livreur: 'Livreur', moderateur: 'Modérateur', client: 'Client', externe: 'Partenaire', inconnu: 'Auteur inconnu' };
const BON: Record<string, string> = { livraison: 'BL', envoi: 'BE', ramassage: 'BRA', retour: 'Retour' };

export function SuiviColisDialog({ colisId, onClose }: { colisId: number | null; onClose: () => void }) {
  const [suivi, setSuivi] = useState<Suivi | null>(null);
  const [erreur, setErreur] = useState(false);

  useEffect(() => {
    if (colisId === null) return;
    setSuivi(null);
    setErreur(false);
    fetch(`/colis/${colisId}/suivi`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then(setSuivi)
      .catch(() => setErreur(true));
  }, [colisId]);

  return (
    <Sheet open={colisId !== null} onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
        <SheetHeader className="border-b border-border px-5 py-4">
          <SheetTitle className="font-mono text-sm">{suivi?.colis.code ?? 'Suivi du colis'}</SheetTitle>
          <SheetDescription className="text-xs">{suivi ? `État actuel : ${suivi.colis.state}` : 'Historique, notes et bons du colis.'}</SheetDescription>
        </SheetHeader>
        <div className="flex-1 overflow-y-auto px-5 py-4">

        {!suivi && !erreur && <div className="flex justify-center py-10"><Loader2 className="size-5 animate-spin text-muted-foreground" /></div>}
        {erreur && <p className="py-6 text-center text-sm text-destructive">Suivi indisponible.</p>}

        {suivi && (
          <div className="space-y-5 text-xs">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {JALONS.map(([k, l]) => (
                <div key={k} className={`rounded-md border p-2 ${suivi.colis.jalons[k] ? 'border-[#004c68]/25 bg-[#004c68]/5' : 'border-dashed border-border text-muted-foreground'}`}>
                  <p className="text-[10px]">{l}</p>
                  <p className="font-semibold">{suivi.colis.jalons[k] ? dateBon(suivi.colis.jalons[k]) : '—'}</p>
                </div>
              ))}
            </div>

            {suivi.bons.length > 0 && (
              <section>
                <h3 className="mb-2 flex items-center gap-1.5 font-semibold"><FileStack className="size-3.5 text-[#ff8806]" /> Bons ({suivi.bons.length})</h3>
                <div className="flex flex-wrap gap-1.5">
                  {suivi.bons.map((b) => (
                    <Link key={`${b.type}-${b.id}`} href={`/bons/${b.type}/${b.id}`} className="rounded-md border border-border px-2 py-1 font-mono text-[11px] hover:border-[#004c68]">
                      <span className="mr-1 font-sans font-semibold text-[#004c68] dark:text-cyan-300">{BON[b.type]}</span>{b.code}
                    </Link>
                  ))}
                </div>
              </section>
            )}

            <section>
              <h3 className="mb-2 flex items-center gap-1.5 font-semibold"><History className="size-3.5 text-[#ff8806]" /> Historique des statuts ({suivi.historique.length})</h3>
              <ol className="relative space-y-2 border-l border-border pl-4">
                {suivi.historique.map((h) => (
                  <li key={h.id} className="relative">
                    <span className="absolute -left-[21px] top-1 size-2.5 rounded-full border-2 border-card bg-[#004c68] dark:bg-cyan-400" />
                    <p><span className="font-semibold">{h.vers}</span>{h.de && <span className="text-muted-foreground"> (après « {h.de} »)</span>}</p>
                    <p className="text-[10px] text-muted-foreground">{dateBon(h.date)} · {h.par ?? 'auteur inconnu'}{h.source === 'legacy' ? ' · ancien site' : ''}</p>
                  </li>
                ))}
                {suivi.historique.length === 0 && <li className="text-muted-foreground">Aucun historique.</li>}
              </ol>
            </section>

            <section>
              <h3 className="mb-2 flex items-center gap-1.5 font-semibold"><MessageSquareText className="size-3.5 text-[#ff8806]" /> Notes ({suivi.notes.length})</h3>
              <ul className="space-y-1.5">
                {suivi.notes.map((n) => (
                  <li key={n.id} className="rounded-md bg-muted/50 px-2.5 py-1.5">
                    <p className="text-[10px] font-semibold text-muted-foreground">{n.auteur ?? ROLE[n.role] ?? n.role}{n.auteur && ROLE[n.role] ? ` · ${ROLE[n.role]}` : ''}{n.date ? ` · ${dateBon(n.date)}` : ' · date non indiquée'}</p>
                    <p className="whitespace-pre-line">{n.message}</p>
                  </li>
                ))}
                {suivi.notes.length === 0 && <li className="text-muted-foreground">Aucune note.</li>}
              </ul>
            </section>
          </div>
        )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
