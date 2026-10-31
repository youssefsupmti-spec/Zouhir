import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type ClientResume, type LigneTarif, erreurMontant, normaliserMontant } from './types';

type Champ = 'livraison' | 'refus' | 'retour';
const CHAMPS: { cle: Champ; label: string }[] = [
  { cle: 'livraison', label: 'Frais de livraison' },
  { cle: 'refus', label: 'Frais de refus' },
  { cle: 'retour', label: 'Frais de retour' },
];

interface Props {
  client: ClientResume | null;
  lignes: LigneTarif[];
  onClose: () => void;
  onSaved: () => void;
}

/**
 * Modification individuelle (1 ligne : les 3 montants) ou en lot (montants appliqués
 * uniquement aux champs cochés ; les autres gardent la valeur de chaque ligne).
 * Seules les lignes sélectionnées sont envoyées.
 */
export function ModifierTarifsDialog({ client, lignes, onClose, onSaved }: Props) {
  const enLot = lignes.length > 1;
  const [valeurs, setValeurs] = useState<Record<Champ, string>>({ livraison: '', refus: '', retour: '' });
  const [actifs, setActifs] = useState<Record<Champ, boolean>>({ livraison: true, refus: false, retour: false });
  const [processing, setProcessing] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  useEffect(() => {
    if (lignes.length === 0) return;
    const premiere = lignes[0];
    setValeurs({ livraison: premiere.livraison, refus: premiere.refus, retour: premiere.retour });
    setActifs(lignes.length > 1 ? { livraison: true, refus: false, retour: false } : { livraison: true, refus: true, retour: true });
    setServerError(null);
  }, [lignes]);

  const erreurs = Object.fromEntries(
    CHAMPS.map(({ cle }) => [cle, actifs[cle] ? erreurMontant(valeurs[cle]) : null]),
  ) as Record<Champ, string | null>;
  const invalide = CHAMPS.some(({ cle }) => erreurs[cle]) || !CHAMPS.some(({ cle }) => actifs[cle]);

  const soumettre = () => {
    if (!client || invalide) return;
    const payload = lignes.map((l) => ({
      id: l.id,
      livraison: actifs.livraison ? normaliserMontant(valeurs.livraison) : l.livraison,
      refus: actifs.refus ? normaliserMontant(valeurs.refus) : l.refus,
      retour: actifs.retour ? normaliserMontant(valeurs.retour) : l.retour,
    }));

    router.put('/tarifs-clients', { client_id: client.id, lignes: payload }, {
      preserveScroll: true,
      onStart: () => setProcessing(true),
      onFinish: () => setProcessing(false),
      onError: (errors) => setServerError(Object.values(errors)[0] ?? 'Modification refusée.'),
      onSuccess: () => {
        onSaved();
        onClose();
      },
    });
  };

  return (
    <Dialog open={client !== null && lignes.length > 0} onOpenChange={(o) => !o && !processing && onClose()}>
      <DialogContent className="max-w-md rounded-lg p-0">
        <DialogHeader className="border-b border-border px-5 py-4">
          <DialogTitle className="text-base font-bold">
            {enLot ? `Modifier ${lignes.length} tarifs` : `Modifier le tarif — ${lignes[0]?.ville_nom ?? ''}`}
          </DialogTitle>
          <DialogDescription className="text-xs">
            {client?.nom}. {enLot
              ? 'Cochez les montants à appliquer à toutes les villes sélectionnées ; les autres restent inchangés.'
              : `Standard de la ville : ${lignes[0]?.standard ?? 'indisponible'}.`}{' '}
            Les colis déjà créés conservent leur tarif figé.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-3 px-5 py-4">
          {enLot && (
            <p className="max-h-16 overflow-y-auto rounded-md bg-muted/40 px-2.5 py-1.5 text-[11px] text-muted-foreground">
              {lignes.map((l) => l.ville_nom).join(', ')}
            </p>
          )}
          {CHAMPS.map(({ cle, label }) => (
            <div key={cle} className="grid grid-cols-[1fr_8rem] items-center gap-3">
              <div className="flex items-center gap-2">
                {enLot && (
                  <Checkbox id={`appliquer-${cle}`} checked={actifs[cle]} onCheckedChange={(c) => setActifs((a) => ({ ...a, [cle]: Boolean(c) }))} />
                )}
                <Label htmlFor={`montant-${cle}`} className="text-xs">{label}</Label>
              </div>
              <div>
                <Input
                  id={`montant-${cle}`}
                  inputMode="decimal"
                  value={valeurs[cle]}
                  disabled={!actifs[cle]}
                  onChange={(e) => setValeurs((v) => ({ ...v, [cle]: e.target.value }))}
                  aria-invalid={Boolean(erreurs[cle])}
                  className="h-8 text-right font-mono text-xs"
                />
                {erreurs[cle] && <p className="mt-0.5 text-right text-[10px] text-destructive">{erreurs[cle]}</p>}
              </div>
            </div>
          ))}

          {serverError && <p role="alert" className="rounded-md bg-destructive/5 px-3 py-2 text-xs font-medium text-destructive">{serverError}</p>}
        </div>

        <DialogFooter className="border-t border-border px-5 py-3">
          <Button variant="outline" size="sm" className="h-8 text-xs" disabled={processing} onClick={onClose}>Annuler</Button>
          <Button size="sm" className="h-8 bg-[#004c68] text-xs text-white hover:bg-[#00384d]" disabled={processing || invalide} onClick={soumettre}>
            {processing && <Loader2 className="size-3.5 animate-spin" />}
            Enregistrer {enLot ? `(${lignes.length})` : ''}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
