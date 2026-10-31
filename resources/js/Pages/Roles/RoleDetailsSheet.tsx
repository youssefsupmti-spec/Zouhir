import * as React from 'react';
import { router } from '@inertiajs/react';
import { AlertCircle, Loader2, UserMinus, UserPlus, Users } from 'lucide-react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { RolePermissionMatrix } from '@/components/admin/RolePermissionMatrix';
import type { PermissionCatalogData } from '@/components/admin/types';

interface RoleDetails {
  id: number;
  name: string;
  guard_name: string;
  is_system: boolean;
  created_at: string | null;
  permissions: string[];
  users_count: number;
  members: { id: number; name: string; email: string }[];
  can_manage_members: boolean;
}

interface RoleDetailsSheetProps {
  roleId: number | null;
  onOpenChange: (open: boolean) => void;
  catalog: PermissionCatalogData;
}

const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' });

export function RoleDetailsSheet({ roleId, onOpenChange, catalog }: RoleDetailsSheetProps) {
  const [details, setDetails] = React.useState<RoleDetails | null>(null);
  const [status, setStatus] = React.useState<'idle' | 'loading' | 'error'>('idle');
  const [email, setEmail] = React.useState('');
  const [memberError, setMemberError] = React.useState<string | null>(null);
  const [busy, setBusy] = React.useState(false);

  const load = React.useCallback(async (id: number, signal?: AbortSignal) => {
    setStatus('loading');
    try {
      const res = await fetch(`/roles/${id}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal,
      });
      if (!res.ok) throw new Error(String(res.status));
      setDetails(await res.json());
      setStatus('idle');
    } catch (e) {
      if ((e as Error).name !== 'AbortError') setStatus('error');
    }
  }, []);

  React.useEffect(() => {
    setDetails(null);
    setEmail('');
    setMemberError(null);
    if (roleId === null) return;
    const controller = new AbortController();
    load(roleId, controller.signal);
    return () => controller.abort();
  }, [roleId, load]);

  const mutate = (method: 'post' | 'delete', url: string, payload: Record<string, string> = {}) => {
    if (!details) return;
    setBusy(true);
    setMemberError(null);
    const options = {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setEmail('');
        load(details.id);
      },
      onError: (errors: Record<string, string>) => setMemberError(Object.values(errors)[0] ?? 'Action refusée.'),
      onFinish: () => setBusy(false),
    };
    if (method === 'post') {
      router.post(url, payload, options);
    } else {
      router.delete(url, options);
    }
  };

  return (
    <Sheet open={roleId !== null} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-xl data-[side=right]:sm:max-w-xl">
        <SheetHeader className="border-b border-border px-5 py-4">
          <SheetTitle className="flex items-center gap-2 text-base font-bold">
            {details?.name ?? 'Détails du rôle'}
            {details && (
              <Badge variant={details.is_system ? 'secondary' : 'outline'} className="rounded-md text-[10px]">
                {details.is_system ? 'Système' : 'Personnalisé'}
              </Badge>
            )}
          </SheetTitle>
          <SheetDescription className="text-xs">Permissions et utilisateurs associés.</SheetDescription>
        </SheetHeader>

        <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
          {status === 'loading' && !details && (
            <div className="space-y-3">
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-40 w-full" />
            </div>
          )}

          {status === 'error' && (
            <div role="alert" className="flex items-center gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive">
              <AlertCircle className="size-4" />
              Impossible de charger le rôle.
              <Button variant="outline" size="sm" className="ml-auto h-7 rounded-md text-xs" onClick={() => roleId && load(roleId)}>
                Réessayer
              </Button>
            </div>
          )}

          {details && (
            <>
              <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-md border border-border bg-border text-xs sm:grid-cols-4">
                {[
                  ['Guard', <code key="g" className="font-mono">{details.guard_name}</code>],
                  ['Permissions', details.permissions.length],
                  ['Utilisateurs', details.users_count],
                  ['Créé le', details.created_at ? dateFormat.format(new Date(details.created_at)) : '—'],
                ].map(([label, value]) => (
                  <div key={label as string} className="bg-card p-3">
                    <dt className="text-[10px] font-medium tracking-wider text-muted-foreground uppercase">{label}</dt>
                    <dd className="mt-1 font-semibold text-foreground">{value}</dd>
                  </div>
                ))}
              </dl>

              <section className="space-y-2">
                <h3 className="text-sm font-semibold">Permissions par module</h3>
                {details.permissions.length === 0 ? (
                  <p className="rounded-md border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                    Ce rôle ne possède aucune permission.
                  </p>
                ) : (
                  <RolePermissionMatrix catalog={catalog} selected={details.permissions} readOnly onlySelected />
                )}
              </section>

              <section className="space-y-2">
                <h3 className="flex items-center gap-1.5 text-sm font-semibold">
                  <Users className="size-4 text-muted-foreground" />
                  Utilisateurs affectés
                  {details.users_count > details.members.length && (
                    <span className="text-[11px] font-normal text-muted-foreground">
                      ({details.members.length} premiers sur {details.users_count})
                    </span>
                  )}
                </h3>

                {details.can_manage_members && (
                  <form
                    className="flex gap-2"
                    onSubmit={(e) => {
                      e.preventDefault();
                      mutate('post', `/roles/${details.id}/users`, { email });
                    }}
                  >
                    <Input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="Email d'un utilisateur existant"
                      className="h-8 rounded-md text-xs"
                      aria-label="Email de l'utilisateur à affecter"
                      required
                    />
                    <Button type="submit" size="sm" disabled={busy} className="h-8 shrink-0 rounded-md bg-[#004c68] text-xs text-white hover:bg-[#00384d]">
                      {busy ? <Loader2 className="size-3.5 animate-spin" /> : <UserPlus className="size-3.5" />}
                      Affecter
                    </Button>
                  </form>
                )}
                {memberError && <p role="alert" className="text-[11px] font-medium text-destructive">{memberError}</p>}

                {details.members.length === 0 ? (
                  <p className="rounded-md border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                    Aucun utilisateur n'a ce rôle.
                  </p>
                ) : (
                  <ul className="divide-y divide-border rounded-md border border-border">
                    {details.members.map((m) => (
                      <li key={m.id} className="flex items-center gap-3 px-3 py-2">
                        <div className="min-w-0 flex-1">
                          <div className="truncate text-xs font-medium text-foreground">{m.name || '—'}</div>
                          <div className="truncate text-[11px] text-muted-foreground">{m.email}</div>
                        </div>
                        {details.can_manage_members && (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            disabled={busy}
                            onClick={() => mutate('delete', `/roles/${details.id}/users/${m.id}`)}
                            aria-label={`Retirer le rôle à ${m.name}`}
                            className="rounded-md text-muted-foreground hover:text-destructive"
                          >
                            <UserMinus className="size-3.5" />
                          </Button>
                        )}
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            </>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
