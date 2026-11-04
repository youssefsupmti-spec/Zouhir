import { useEffect, useRef, useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Loader2, MessageSquareText, SendHorizontal } from 'lucide-react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';

interface Note { id: number; role: string; auteur: string | null; message: string; source: string; date: string | null; date_exacte: boolean; moi: boolean }
interface Fil { colis: { id: number; code: string; destinataire: string; state: string }; notes: Note[] }

const ROLE: Record<string, { libelle: string; pastille: string }> = {
  moderateur: { libelle: 'Agence', pastille: 'bg-[#004c68] text-white' },
  livreur: { libelle: 'Livreur', pastille: 'bg-sky-600 text-white' },
  client: { libelle: 'Marchand', pastille: 'bg-[#ff8806] text-white' },
  externe: { libelle: 'Partenaire', pastille: 'bg-violet-600 text-white' },
  inconnu: { libelle: 'Ancien site', pastille: 'bg-muted-foreground/70 text-white' },
};

const initiales = (nom: string | null, role: string) =>
  (nom ?? ROLE[role]?.libelle ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((m) => m[0]).join('').toUpperCase();

function quand(d: string | null) {
  if (!d) return null;
  const x = new Date(d);
  return `${x.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })} · ${x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
}

function jetonCsrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Fil des notes d'un colis, en panneau latéral : messages de l'ancien site puis de la plateforme,
 * zone d'écriture en bas (Ctrl + Entrée pour envoyer). Après envoi, seule la liste est rechargée.
 */
export function NotesColisPanel({ colisId, onClose }: { colisId: number | null; onClose: () => void }) {
  const [fil, setFil] = useState<Fil | null>(null);
  const [message, setMessage] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  const bas = useRef<HTMLDivElement>(null);

  const charger = (id: number) =>
    fetch(`/colis/${id}/notes`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then((f: Fil) => { setFil(f); setTimeout(() => bas.current?.scrollIntoView({ block: 'end' }), 0); })
      .catch(() => setErreur('Notes indisponibles.'));

  useEffect(() => {
    setFil(null); setMessage(''); setErreur(null);
    if (colisId !== null) charger(colisId);
  }, [colisId]); // eslint-disable-line react-hooks/exhaustive-deps

  const envoyer = async (e?: FormEvent) => {
    e?.preventDefault();
    if (colisId === null || message.trim() === '' || envoi) return;
    setEnvoi(true); setErreur(null);
    const r = await fetch(`/colis/${colisId}/notes`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': jetonCsrf() },
      body: JSON.stringify({ message }),
    });
    setEnvoi(false);
    if (!r.ok) {
      setErreur(r.status === 422 ? 'Écrivez la note (2 000 caractères au plus).' : 'Envoi impossible.');
      return;
    }
    setMessage('');
    await charger(colisId);
    router.reload({ only: ['colis'] });
  };

  return (
    <Sheet open={colisId !== null} onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-md">
        <SheetHeader className="border-b border-border px-5 py-4">
          <SheetTitle className="flex items-center gap-2 text-sm">
            <MessageSquareText className="size-4 text-[#004c68] dark:text-[#ff8806]" /> Notes du colis <span className="font-mono">{fil?.colis.code}</span>
          </SheetTitle>
          <SheetDescription className="text-xs">{fil ? `${fil.colis.destinataire} · ${fil.colis.state}` : 'Chargement…'}</SheetDescription>
        </SheetHeader>

        <div className="flex-1 space-y-3 overflow-y-auto px-5 py-4">
          {!fil && !erreur && <div className="flex justify-center py-10"><Loader2 className="size-5 animate-spin text-muted-foreground" /></div>}
          {fil?.notes.length === 0 && <p className="py-10 text-center text-xs text-muted-foreground">Aucune note pour ce colis.</p>}
          {fil?.notes.map((n) => {
            const r = ROLE[n.role] ?? ROLE.inconnu;
            return (
              <div key={n.id} className={`flex gap-2.5 ${n.moi ? 'flex-row-reverse' : ''}`}>
                <span className={`mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${r.pastille}`}>{initiales(n.auteur, n.role)}</span>
                <div className={`max-w-[80%] ${n.moi ? 'text-right' : ''}`}>
                  <p className="text-[10px] text-muted-foreground">
                    <span className="font-semibold text-foreground">{n.auteur ?? r.libelle}</span> · {r.libelle}
                    {quand(n.date) ? ` · ${quand(n.date)}` : n.source === 'legacy' ? ' · date non indiquée' : ''}
                  </p>
                  <p className={`mt-0.5 inline-block whitespace-pre-line rounded-lg px-3 py-1.5 text-left text-xs leading-relaxed ${n.moi ? 'bg-[#004c68] text-white' : 'border border-border bg-muted/40'}`}>{n.message}</p>
                </div>
              </div>
            );
          })}
          <div ref={bas} />
        </div>

        <form onSubmit={envoyer} className="border-t border-border p-4">
          <Textarea
            rows={3}
            value={message}
            maxLength={2000}
            onChange={(e) => setMessage(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) envoyer(); }}
            placeholder="Écrire une note pour l’agence, le livreur ou le marchand…"
            className="resize-none text-xs"
          />
          {erreur && <p className="mt-1 text-[11px] text-destructive">{erreur}</p>}
          <div className="mt-2 flex items-center justify-between">
            <span className="text-[10px] text-muted-foreground">Ctrl + Entrée pour envoyer</span>
            <Button type="submit" size="sm" disabled={envoi || message.trim() === ''} className="h-8 bg-[#004c68] text-xs text-white hover:bg-[#00384d]">
              {envoi ? <Loader2 className="size-3.5 animate-spin" /> : <SendHorizontal className="size-3.5" />} Envoyer
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
