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
import { MapPin, Loader2 } from 'lucide-react';

interface ZoneItem {
  id: number;
  name: string;
  code: string;
}

interface VilleItem {
  id?: number;
  name_fr?: string;
  name_ar?: string;
  zone_id?: number;
  code_zone?: string;
}

interface VilleFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  ville?: VilleItem | null;
  zones: ZoneItem[];
}

export function VilleFormDialog({
  open,
  onOpenChange,
  ville,
  zones,
}: VilleFormDialogProps) {
  const isEditing = !!ville?.id;

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    name_fr: ville?.name_fr || '',
    name_ar: ville?.name_ar || '',
    zone_id: ville?.zone_id?.toString() || (zones[0]?.id?.toString() ?? ''),
    code_zone: ville?.code_zone || '',
  });

  useEffect(() => {
    if (ville) {
      setData({
        name_fr: ville.name_fr || '',
        name_ar: ville.name_ar || '',
        zone_id: ville.zone_id?.toString() || (zones[0]?.id?.toString() ?? ''),
        code_zone: ville.code_zone || '',
      });
    } else {
      reset();
      if (zones.length > 0) {
        setData('zone_id', zones[0].id.toString());
      }
    }
    clearErrors();
  }, [ville, open]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing) {
      put(`/villes/${ville.id}`, {
        onSuccess: () => onOpenChange(false),
      });
    } else {
      post('/villes', {
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
              <MapPin className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                {isEditing ? "Modifier la ville" : "Ajouter une Ville au Référentiel"}
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                {isEditing
                  ? "Mettez à jour les libellés et la zone de desserte logistique."
                  : "Enregistrement d'une nouvelle destination desservie par le réseau."}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-3.5 mt-2">
          <div className="space-y-1.5">
            <Label htmlFor="name_fr">Nom de la Ville (Français) *</Label>
            <Input
              id="name_fr"
              placeholder="Ex: Témara, Casablanca, Agadir..."
              value={data.name_fr}
              onChange={(e) => setData('name_fr', e.target.value)}
              required
            />
            {errors.name_fr && (
              <p className="text-[11px] text-destructive font-medium">{errors.name_fr}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="name_ar">Nom en Arabe (Optionnel)</Label>
            <Input
              id="name_ar"
              dir="rtl"
              placeholder="تمارة، الدار البيضاء..."
              value={data.name_ar}
              onChange={(e) => setData('name_ar', e.target.value)}
            />
            {errors.name_ar && (
              <p className="text-[11px] text-destructive font-medium">{errors.name_ar}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="zone_id">Zone Logistique de Rattachement *</Label>
            <select
              id="zone_id"
              value={data.zone_id}
              onChange={(e) => {
                const zId = e.target.value;
                const found = zones.find((z) => z.id.toString() === zId);
                setData((prev) => ({
                  ...prev,
                  zone_id: zId,
                  code_zone: found?.code || '',
                }));
              }}
              className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
              required
            >
              <option value="" disabled className="dark:bg-slate-900">Sélectionnez une zone</option>
              {zones.map((z) => (
                <option key={z.id} value={z.id} className="dark:bg-slate-900">
                  {z.name} ({z.code})
                </option>
              ))}
            </select>
            {errors.zone_id && (
              <p className="text-[11px] text-destructive font-medium">{errors.zone_id}</p>
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
              {isEditing ? "Enregistrer" : "Créer la ville"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
