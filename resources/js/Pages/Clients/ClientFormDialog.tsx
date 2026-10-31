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
import { Users, Loader2 } from 'lucide-react';

interface ClientItem {
  id?: number;
  nom_complet?: string;
  email?: string;
  telephone?: string;
  ville?: string;
  status?: string;
}

interface ClientFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  client?: ClientItem | null;
}

export function ClientFormDialog({
  open,
  onOpenChange,
  client,
}: ClientFormDialogProps) {
  const isEditing = !!client?.id;

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    nom_complet: client?.nom_complet || '',
    email: client?.email || '',
    telephone: client?.telephone || '',
    ville_name: client?.ville || '',
    store_name: '',
    status: client?.status || 'active',
    password: '',
  });

  useEffect(() => {
    if (client) {
      setData({
        nom_complet: client.nom_complet || '',
        email: client.email || '',
        telephone: client.telephone || '',
        ville_name: client.ville || '',
        store_name: '',
        status: client.status || 'active',
        password: '',
      });
    } else {
      reset();
    }
    clearErrors();
  }, [client, open]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing) {
      put(`/clients/${client.id}`, {
        onSuccess: () => onOpenChange(false),
      });
    } else {
      post('/clients', {
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
              <Users className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                {isEditing ? "Modifier le compte client" : "Nouveau Client Marchand"}
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                {isEditing
                  ? "Mettez à jour les coordonnées et le statut du marchand."
                  : "Création d'un nouveau compte vendeur avec son enseigne initiale."}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-3.5 mt-2">
          <div className="space-y-1.5">
            <Label htmlFor="nom_complet">Nom Complet / Raison Sociale *</Label>
            <Input
              id="nom_complet"
              placeholder="Ex: Société Marocaine de Commerce"
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
              <Label htmlFor="email">Email *</Label>
              <Input
                id="email"
                type="email"
                placeholder="contact@marchand.ma"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                required
              />
              {errors.email && (
                <p className="text-[11px] text-destructive font-medium">{errors.email}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="telephone">Téléphone Direct *</Label>
              <Input
                id="telephone"
                placeholder="0661234567"
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
              <Label htmlFor="ville_name">Ville de Rattachement</Label>
              <Input
                id="ville_name"
                placeholder="Casablanca, Rabat, Tanger..."
                value={data.ville_name}
                onChange={(e) => setData('ville_name', e.target.value)}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="status">Statut du compte</Label>
              <select
                id="status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
                className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
              >
                <option value="active" className="dark:bg-slate-900">Actif</option>
                <option value="suspended" className="dark:bg-slate-900">Suspendu</option>
                <option value="inactive" className="dark:bg-slate-900">Inactif</option>
              </select>
            </div>
          </div>

          {!isEditing && (
            <div className="space-y-1.5">
              <Label htmlFor="store_name">Nom de l'Enseigne Initiale (Optionnel)</Label>
              <Input
                id="store_name"
                placeholder="Laisser vide pour créer automatiquement au nom du client"
                value={data.store_name}
                onChange={(e) => setData('store_name', e.target.value)}
              />
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="password">
              {isEditing ? "Changer le mot de passe (Laisser vide pour conserver)" : "Mot de passe initial (Défaut: ouaiss@2026)"}
            </Label>
            <Input
              id="password"
              type="password"
              placeholder="••••••••"
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
              {isEditing ? "Enregistrer" : "Créer le client"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

