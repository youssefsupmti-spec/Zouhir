import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { Loader2, ShieldCheck } from 'lucide-react';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RolePermissionMatrix } from '@/components/admin/RolePermissionMatrix';
import type { PermissionCatalogData } from '@/components/admin/types';

export interface EditableRole {
  id: number;
  name: string;
  guard_name: string;
  is_system: boolean;
  permissions: string[];
}

interface RoleFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  role: EditableRole | null;
  catalog: PermissionCatalogData;
  /** Permissions critiques verrouillées (rôle administrateur système). */
  locked?: string[];
}

export function RoleFormDialog({ open, onOpenChange, role, catalog, locked = [] }: RoleFormDialogProps) {
  const isEditing = role !== null;
  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<{
    name: string;
    permissions: string[];
  }>({ name: '', permissions: [] });

  React.useEffect(() => {
    if (!open) return;
    clearErrors();
    if (role) {
      setData({ name: role.name, permissions: role.permissions });
    } else {
      reset();
    }
  }, [open, role]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
    if (role) {
      put(`/roles/${role.id}`, options);
    } else {
      post('/roles', options);
    }
  };

  const errorList = Object.entries(errors).filter(([key]) => key !== 'name');

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[90svh] w-[calc(100%-2rem)] max-w-3xl flex-col gap-0 overflow-hidden rounded-lg border border-border bg-card p-0 sm:max-w-3xl">
        <DialogHeader className="gap-1 border-b border-border px-5 py-4">
          <div className="flex items-center gap-2.5">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#004c68]/10 text-[#004c68] dark:text-[#ff8806]">
              <ShieldCheck className="size-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold">
                {isEditing ? `Modifier le rôle « ${role.name} »` : 'Nouveau rôle'}
              </DialogTitle>
              <DialogDescription className="text-xs">
                Les permissions s'appliquent immédiatement à tous les utilisateurs du rôle.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
            <section className="grid gap-3 sm:grid-cols-[1fr_9rem]">
              <div className="space-y-1.5">
                <Label htmlFor="role-name">Nom du rôle *</Label>
                <Input
                  id="role-name"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="Ex. Responsable Facturation"
                  disabled={role?.is_system}
                  maxLength={120}
                  required
                  autoFocus={!isEditing}
                />
                {role?.is_system && (
                  <p className="text-[11px] text-muted-foreground">Le nom d'un rôle système ne peut pas être modifié.</p>
                )}
                {errors.name && <p className="text-[11px] font-medium text-destructive">{errors.name}</p>}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="role-guard">Guard</Label>
                <Input id="role-guard" value={role?.guard_name ?? 'web'} disabled readOnly className="font-mono" />
                <p className="text-[11px] text-muted-foreground">Imposé par le serveur.</p>
              </div>
            </section>

            <section className="space-y-2">
              <div>
                <h3 className="text-sm font-semibold text-foreground">Permissions</h3>
                <p className="text-[11px] text-muted-foreground">
                  Les permissions « Périmètres de données » déterminent les données visibles ; les autres, les actions autorisées.
                </p>
              </div>
              <RolePermissionMatrix
                catalog={catalog}
                selected={data.permissions}
                onChange={(next) => setData('permissions', next)}
                locked={locked}
              />
            </section>

            {errorList.length > 0 && (
              <div role="alert" className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-3">
                {errorList.map(([key, message]) => (
                  <p key={key} className="text-[11px] font-medium text-destructive">{message}</p>
                ))}
              </div>
            )}
          </div>

          <DialogFooter className="m-0 flex-row items-center justify-between gap-2 rounded-none border-t border-border bg-muted/30 px-5 py-3">
            <span className="text-[11px] text-muted-foreground">
              {data.permissions.length} permission(s) sélectionnée(s)
            </span>
            <div className="flex gap-2">
              <DialogClose render={<Button type="button" variant="outline" size="sm" className="h-8 rounded-md text-xs" />}>
                Annuler
              </DialogClose>
              <Button
                type="submit"
                size="sm"
                disabled={processing}
                className="h-8 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]"
              >
                {processing && <Loader2 className="size-3.5 animate-spin" />}
                {isEditing ? 'Enregistrer' : 'Créer le rôle'}
              </Button>
            </div>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
