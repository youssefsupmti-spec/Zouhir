import React, { useState, useEffect } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { Store, Loader2 } from 'lucide-react';

interface BusinessItem {
  id?: number;
  user_id?: number;
  name?: string;
  phone?: string;
  address?: string;
  status?: string;
}

interface ClientOption {
  id: number;
  nom_complet: string;
}

interface BusinessFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  business?: BusinessItem | null;
  clients: ClientOption[];
}

export function BusinessFormDialog({
  open,
  onOpenChange,
  business,
  clients,
}: BusinessFormDialogProps) {
  const isEditing = !!business?.id;

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    user_id: business?.user_id?.toString() || (clients[0]?.id?.toString() ?? ''),
    name: business?.name || '',
    phone: business?.phone || '',
    address: business?.address || '',
    status: business?.status || 'active',
  });

  useEffect(() => {
    if (business) {
      setData({
        user_id: business.user_id?.toString() || (clients[0]?.id?.toString() ?? ''),
        name: business.name || '',
        phone: business.phone || '',
        address: business.address || '',
        status: business.status || 'active',
      });
    } else {
      reset();
      if (clients.length > 0) {
        setData('user_id', clients[0].id.toString());
      }
    }
    clearErrors();
  }, [business, open]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing) {
      put(`/businesses/${business.id}`, {
        onSuccess: () => onOpenChange(false),
      });
    } else {
      post('/businesses', {
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
              <Store className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                {isEditing ? "Modifier l'enseigne" : "Créer une nouvelle enseigne"}
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                {isEditing
                  ? "Ajustez les informations commerciales de l'enseigne."
                  : "Associez une nouvelle boutique marchande à un client."}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-2">
          <div className="space-y-1.5">
            <Label htmlFor="user_id">Client Marchand Propriétaire *</Label>
            <select
              id="user_id"
              value={data.user_id}
              onChange={(e) => setData('user_id', e.target.value)}
              className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
              required
            >
              <option value="" disabled className="dark:bg-slate-900">Sélectionnez un marchand</option>
              {clients.map((c) => (
                <option key={c.id} value={c.id} className="dark:bg-slate-900">
                  {c.nom_complet} (#{c.id})
                </option>
              ))}
            </select>
            {errors.user_id && (
              <p className="text-[11px] text-destructive font-medium">{errors.user_id}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="name">Nom de l'Enseigne / Marque *</Label>
            <Input
              id="name"
              placeholder="Ex: Boutique Élégance Casablanca"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              required
            />
            {errors.name && (
              <p className="text-[11px] text-destructive font-medium">{errors.name}</p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="phone">Téléphone Support</Label>
              <Input
                id="phone"
                placeholder="0612345678"
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
              />
              {errors.phone && (
                <p className="text-[11px] text-destructive font-medium">{errors.phone}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="status">Statut de l'enseigne</Label>
              <select
                id="status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
                className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
              >
                <option value="active" className="dark:bg-slate-900">Actif (Ouverte)</option>
                <option value="suspended" className="dark:bg-slate-900">Suspendu</option>
                <option value="inactive" className="dark:bg-slate-900">Inactif (Fermée)</option>
              </select>
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="address">Adresse de Ramassage / Siège</Label>
            <Textarea
              id="address"
              placeholder="Adresse d'enlèvement des colis..."
              value={data.address}
              onChange={(e) => setData('address', e.target.value)}
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
              {isEditing ? "Enregistrer les modifications" : "Créer l'enseigne"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
