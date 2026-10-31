import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Truck, CheckCircle2, RotateCcw, XCircle, Clock, Loader2 } from 'lucide-react';

interface LivreurOption {
  id: number;
  nom_complet: string;
}

interface ColisItem {
  id: number;
  code: string;
  state_group: string;
  livreur_id?: number;
  destinataire_nom: string;
  prix_brut: number | string;
}

interface ChangeStatutDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  colis: ColisItem | null;
  livreurs: LivreurOption[];
}

export function ChangeStatutDialog({
  open,
  onOpenChange,
  colis,
  livreurs,
}: ChangeStatutDialogProps) {
  if (!colis) return null;

  const { data, setData, patch, processing, errors, reset } = useForm({
    state_group: colis.state_group || 'nouveau',
    livreur_id: colis.livreur_id?.toString() || '',
    note: '',
  });

  useEffect(() => {
    if (colis) {
      setData({
        state_group: colis.state_group || 'nouveau',
        livreur_id: colis.livreur_id?.toString() || '',
        note: '',
      });
    }
  }, [colis, open]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    patch(`/colis/${colis.id}/state`, {
      onSuccess: () => onOpenChange(false),
    });
  };

  const statuts = [
    { id: 'nouveau', label: 'Nouveau Colis', icon: Clock, color: 'text-blue-600 bg-blue-50 dark:bg-blue-950/40 border-blue-200' },
    { id: 'en_cours', label: 'En cours de livraison', icon: Truck, color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40 border-amber-200' },
    { id: 'livre', label: 'Livré & Encaissé', icon: CheckCircle2, color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200' },
    { id: 'retour', label: 'Retour Expéditeur', icon: RotateCcw, color: 'text-orange-600 bg-orange-50 dark:bg-orange-950/40 border-orange-200' },
    { id: 'annule', label: 'Annulé', icon: XCircle, color: 'text-rose-600 bg-rose-50 dark:bg-rose-950/40 border-rose-200' },
  ];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md p-6 bg-card text-card-foreground border border-border rounded-lg shadow-xl">
        <DialogHeader className="gap-2">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Truck className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                Mettre à jour le statut du colis
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                Colis #{colis.code} &bull; Destinataire: {colis.destinataire_nom} ({colis.prix_brut} MAD)
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-2">
          <div className="space-y-2">
            <Label>Sélectionner le nouvel état logistique *</Label>
            <div className="grid grid-cols-1 gap-2">
              {statuts.map((s) => {
                const Icon = s.icon;
                const isSelected = data.state_group === s.id;
                return (
                  <button
                    key={s.id}
                    type="button"
                    onClick={() => setData('state_group', s.id)}
                    className={`flex items-center justify-between p-2.5 rounded-md border text-xs font-medium transition-all ${
                      isSelected
                        ? `${s.color} ring-2 ring-primary border-transparent`
                        : 'border-border hover:bg-muted/50 text-foreground'
                    }`}
                  >
                    <div className="flex items-center gap-2">
                      <Icon className="h-4 w-4" />
                      <span>{s.label}</span>
                    </div>
                    {isSelected && <span className="text-[11px] font-bold">Sélectionné</span>}
                  </button>
                );
              })}
            </div>
          </div>

          {data.state_group === 'en_cours' && (
            <div className="space-y-1.5 pt-1">
              <Label htmlFor="livreur_id">Affecter au coursier (DLM)</Label>
              <select
                id="livreur_id"
                value={data.livreur_id}
                onChange={(e) => setData('livreur_id', e.target.value)}
                className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
              >
                <option value="" className="dark:bg-slate-900">Sélectionnez le coursier</option>
                {livreurs.map((l) => (
                  <option key={l.id} value={l.id} className="dark:bg-slate-900">
                    {l.nom_complet}
                  </option>
                ))}
              </select>
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="note">Commentaire / Justificatif (Optionnel)</Label>
            <Textarea
              id="note"
              placeholder="Ex: Client absent au premier passage, reprogrammé..."
              value={data.note}
              onChange={(e) => setData('note', e.target.value)}
              rows={2}
            />
          </div>

          <DialogFooter className="mt-5 flex items-center justify-end gap-2">
            <DialogClose asChild>
              <Button type="button" variant="outline" size="sm" className="text-xs h-8">
                Annuler
              </Button>
            </DialogClose>
            <Button
              type="submit"
              size="sm"
              disabled={processing}
              className="text-xs h-8 bg-primary text-primary-foreground hover:bg-primary/90"
            >
              {processing && <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />}
              Confirmer le statut
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
