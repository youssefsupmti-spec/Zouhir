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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Truck, Loader2 } from 'lucide-react';

interface LivreurItem {
  id?: number;
  nom_complet?: string;
  email?: string;
  telephone?: string;
  ville?: string;
  status?: string;
}

interface LivreurFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  livreur?: LivreurItem | null;
}

export function LivreurFormDialog({
  open,
  onOpenChange,
  livreur,
}: LivreurFormDialogProps) {
  const isEditing = !!livreur?.id;

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    nom_complet: livreur?.nom_complet || '',
    email: livreur?.email || '',
    telephone: livreur?.telephone || '',
    ville_name: livreur?.ville || '',
    status: livreur?.status || 'active',
    password: '',
  });

  useEffect(() => {
    if (livreur) {
      setData({
        nom_complet: livreur.nom_complet || '',
        email: livreur.email || '',
        telephone: livreur.telephone || '',
        ville_name: livreur.ville || '',
        status: livreur.status || 'active',
        password: '',
      });
    } else {
      reset();
    }
    clearErrors();
  }, [livreur, open]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing) {
      put(`/livreurs/${livreur.id}`, {
        onSuccess: () => onOpenChange(false),
      });
    } else {
      post('/livreurs', {
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
              <Truck className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                {isEditing ? "Modifier le coursier" : "Nouveau Livreur / Coursier DLM"}
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                {isEditing
                  ? "Ajustez les informations de contact et le secteur de tournée."
                  : "Enregistrement d'un chauffeur livreur sur la flotte OUAISS EXPRESS."}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-3.5 mt-2">
          <div className="space-y-1.5">
            <Label htmlFor="nom_complet">Nom et Prénom du Coursier *</Label>
            <Input
              id="nom_complet"
              placeholder="Ex: Youssef El Amrani"
              value={data.nom_complet}
              onChange={(e) => setData('nom_complet', e.target.value)}
              required
            />
            {errors.nom_complet && (
              <p className="text-[11px] text-destructive font-medium">{errors.nom_complet}</p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="email">Email de Connexion *</Label>
              <Input
                id="email"
                type="email"
                placeholder="coursier@ouaissexpress.com"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                required
              />
              {errors.email && (
                <p className="text-[11px] text-destructive font-medium">{errors.email}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="telephone">Téléphone Mobile *</Label>
              <Input
                id="telephone"
                placeholder="0611223344"
                value={data.telephone}
                onChange={(e) => setData('telephone', e.target.value)}
                required
              />
              {errors.telephone && (
                <p className="text-[11px] text-destructive font-medium">{errors.telephone}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="ville_name">Secteur / Ville de Tournée</Label>
              <Input
                id="ville_name"
                placeholder="Rabat, Salé, Témara..."
                value={data.ville_name}
                onChange={(e) => setData('ville_name', e.target.value)}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="status">Statut Opérationnel</Label>
              <select
                id="status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
                className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
              >
                <option value="active" className="dark:bg-slate-900">Actif (En service)</option>
                <option value="suspended" className="dark:bg-slate-900">Suspendu</option>
                <option value="inactive" className="dark:bg-slate-900">Inactif (Indisponible)</option>
              </select>
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="password">
              {isEditing ? "Mot de passe (Laisser vide pour ne pas changer)" : "Mot de passe initial (obligatoire, 8 caractères minimum)"}
            </Label>
            <Input
              id="password"
              type="password"
              placeholder="••••••••"
              required={!isEditing}
              minLength={8}
              autoComplete="new-password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
            />
            {errors.password && (
              <p className="text-[11px] text-destructive font-medium">{errors.password}</p>
            )}
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
              {isEditing ? "Enregistrer" : "Créer le livreur"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

