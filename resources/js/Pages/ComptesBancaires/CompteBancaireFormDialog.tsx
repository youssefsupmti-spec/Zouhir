import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Landmark, Loader2 } from 'lucide-react';

interface BankOption {
  id: number;
  name: string;
  code_etablissement?: string | null;
  status?: string;
}

interface OwnerOption {
  id: number;
  nom_complet: string;
  role?: string | null;
  business?: string | null;
}

interface CompteItem {
  id?: number;
  owner_id?: number;
  owner_type?: string | null;
  titulaire_nom?: string;
  bank_id?: number | null;
  rib?: string;
  is_default?: boolean;
}

interface CompteBancaireFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  compte?: CompteItem | null;
  banks: BankOption[];
  owners: OwnerOption[];
}

export function CompteBancaireFormDialog({
  open,
  onOpenChange,
  compte,
  banks,
  owners,
}: CompteBancaireFormDialogProps) {
  const isEditing = !!compte?.id;
  // Une banque désactivée reste proposée seulement au compte qui l'utilise déjà.
  const actives = banks.filter((b) => !b.status || b.status === 'active');

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    owner_id: compte?.owner_id?.toString() || (owners[0]?.id?.toString() ?? ''),
    owner_type: compte?.owner_type || 'client',
    titulaire_nom: compte?.titulaire_nom || '',
    bank_id: compte?.bank_id?.toString() || (actives[0]?.id?.toString() ?? ''),
    rib: compte?.rib || '',
    is_default: compte?.is_default ?? true,
  });

  useEffect(() => {
    if (compte) {
      setData({
        owner_id: compte.owner_id?.toString() || (owners[0]?.id?.toString() ?? ''),
        owner_type: compte.owner_type || 'client',
        titulaire_nom: compte.titulaire_nom || '',
        bank_id: compte.bank_id?.toString() || (actives[0]?.id?.toString() ?? ''),
        rib: compte.rib || '',
        is_default: compte.is_default ?? true,
      });
    } else {
      reset();
      if (owners.length > 0) setData('owner_id', owners[0].id.toString());
      if (actives.length > 0) setData('bank_id', actives[0].id.toString());
    }
    clearErrors();
  }, [compte, open]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing) {
      put(`/comptes-bancaires/${compte.id}`, {
        onSuccess: () => onOpenChange(false),
      });
    } else {
      post('/comptes-bancaires', {
        onSuccess: () => onOpenChange(false),
      });
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md p-6 bg-card text-card-foreground border border-border rounded-lg shadow-xl">
        <DialogHeader className="gap-2">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Landmark className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                {isEditing ? "Modifier le compte bancaire" : "Nouveau compte bancaire (RIB)"}
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                {isEditing
                  ? "Ajustez les coordonnées bancaires pour les virements de reversement."
                  : "Associez un RIB à un business (marchand) ou à un livreur."}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-3.5 mt-2">
          {!isEditing && (
            <div className="space-y-1.5">
              <Label htmlFor="owner_id">Business / titulaire *</Label>
              <select
                id="owner_id"
                value={data.owner_id}
                onChange={(e) => {
                  const oId = e.target.value;
                  const found = owners.find((o) => o.id.toString() === oId);
                  setData((prev) => ({
                    ...prev,
                    owner_id: oId,
                    titulaire_nom: prev.titulaire_nom || found?.nom_complet || '',
                    owner_type: (found?.role as string) || 'client',
                  }));
                }}
                className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
                required
              >
                <option value="" disabled className="dark:bg-slate-900">Sélectionnez le bénéficiaire</option>
                {owners.map((o) => (
                  <option key={o.id} value={o.id} className="dark:bg-slate-900">
                    {o.business ? `${o.business} — ${o.nom_complet}` : o.nom_complet} ({o.role || 'client'})
                  </option>
                ))}
              </select>
              {errors.owner_id && (
                <p className="text-[11px] text-destructive font-medium">{errors.owner_id}</p>
              )}
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="titulaire_nom">Nom Intitulé du Titulaire *</Label>
            <Input
              id="titulaire_nom"
              placeholder="Ex: SARL ATLAS DISTRIBUTION"
              value={data.titulaire_nom}
              onChange={(e) => setData('titulaire_nom', e.target.value)}
              required
            />
            {errors.titulaire_nom && (
              <p className="text-[11px] text-destructive font-medium">{errors.titulaire_nom}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="bank_id">Banque *</Label>
            <select
              id="bank_id"
              value={data.bank_id}
              onChange={(e) => setData('bank_id', e.target.value)}
              className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
              required
            >
              <option value="" disabled className="dark:bg-slate-900">Sélectionnez la banque</option>
              {banks.filter((b) => actives.includes(b) || b.id === compte?.bank_id).map((b) => (
                <option key={b.id} value={b.id} className="dark:bg-slate-900">
                  {b.name}{b.code_etablissement ? ` (code ${b.code_etablissement})` : ''}{actives.includes(b) ? '' : ' — désactivée'}
                </option>
              ))}
            </select>
            {errors.bank_id && (
              <p className="text-[11px] text-destructive font-medium">{errors.bank_id}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="rib">RIB complet (24 chiffres){isEditing ? '' : ' *'}</Label>
            <Input
              id="rib"
              placeholder={isEditing ? 'Laisser vide pour garder le RIB actuel' : '230 810 1234567890123456 78'}
              value={data.rib}
              onChange={(e) => setData('rib', e.target.value)}
              required={!isEditing}
              className="font-mono text-xs tracking-wider"
            />
            {errors.rib && (
              <p className="text-[11px] text-destructive font-medium">{errors.rib}</p>
            )}
          </div>

          <div className="flex items-center gap-2 pt-1">
            <input
              type="checkbox"
              id="is_default"
              checked={data.is_default}
              onChange={(e) => setData('is_default', e.target.checked)}
              className="h-4 w-4 rounded border-input text-primary focus:ring-primary"
            />
            <Label htmlFor="is_default" className="cursor-pointer font-normal text-xs">
              Définir comme compte principal par défaut pour les reversements CRBT
            </Label>
          </div>

          <DialogFooter className="mt-5 flex items-center justify-end gap-2">
            <Button type="button" variant="outline" size="sm" className="text-xs h-8" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={processing}
              className="text-xs h-8 bg-primary text-primary-foreground hover:bg-primary/90"
            >
              {processing && <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />}
              {isEditing ? "Enregistrer" : "Créer le compte"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

