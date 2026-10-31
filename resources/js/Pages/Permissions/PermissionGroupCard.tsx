import { ChevronRightIcon, ShieldAlertIcon, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import type { CatalogPermission } from '@/components/admin/types';

export interface PermissionRow extends CatalogPermission {
  id: number;
  guard_name: string;
  roles_count: number;
  direct_users_count: number;
  roles: string[];
  created_at: string | null;
  can_delete: boolean;
}

export interface PermissionGroup {
  key: string;
  label: string;
  description: string;
  permissions: PermissionRow[];
  roles_using: number;
}

/** Statut à trois niveaux (cahier des charges §6) : implémentée / non reliée / hors catalogue. */
export function PermissionStatusBadge({ permission }: { permission: PermissionRow }) {
  if (!permission.catalogued) {
    return <Badge variant="destructive" className="rounded-md text-[10px]">Hors catalogue</Badge>;
  }
  if (!permission.implemented) {
    return <Badge variant="outline" className="rounded-md text-[10px] text-muted-foreground">Non reliée à une règle</Badge>;
  }
  return (
    <Badge variant="outline" className="rounded-md border-emerald-500/30 text-[10px] text-emerald-700 dark:text-emerald-400">
      Implémentée
    </Badge>
  );
}

interface PermissionGroupCardProps {
  group: PermissionGroup;
  onSelect: (permission: PermissionRow) => void;
  onDelete: (permission: PermissionRow) => void;
}

export function PermissionGroupCard({ group, onSelect, onDelete }: PermissionGroupCardProps) {
  return (
    <Collapsible defaultOpen className="group/perm-group rounded-md border border-border bg-card shadow-2xs">
      <CollapsibleTrigger className="flex w-full cursor-pointer items-center gap-3 px-4 py-3 text-left">
        <div className="min-w-0 flex-1">
          <h2 className="text-sm font-semibold text-foreground">{group.label}</h2>
          <p className="truncate text-[11px] text-muted-foreground">{group.description}</p>
        </div>
        <div className="hidden shrink-0 items-center gap-1.5 sm:flex">
          <Badge variant="secondary" className="rounded-md text-[10px]">{group.permissions.length} permission(s)</Badge>
          <Badge variant="outline" className="rounded-md text-[10px]">{group.roles_using} rôle(s)</Badge>
        </div>
        <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground transition-transform group-data-open/perm-group:rotate-90" />
      </CollapsibleTrigger>

      <CollapsibleContent>
        <ul className="divide-y divide-border border-t border-border">
          {group.permissions.map((p) => (
            <li key={p.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
              <button type="button" onClick={() => onSelect(p)} className="min-w-0 flex-1 cursor-pointer text-left">
                <span className="flex flex-wrap items-center gap-1.5">
                  <span className="text-xs font-semibold text-foreground hover:underline">{p.label}</span>
                  {p.critical && <ShieldAlertIcon className="size-3.5 text-[#ff8806]" aria-label="Permission critique" />}
                </span>
                <code className="block truncate font-mono text-[10px] text-muted-foreground">{p.name}</code>
                {p.description && <span className="mt-0.5 block text-[11px] text-muted-foreground">{p.description}</span>}
              </button>
              <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">{p.guard_name}</code>
                <Badge variant={p.roles_count > 0 ? 'secondary' : 'outline'} className="rounded-md text-[10px]">
                  {p.roles_count > 0 ? `${p.roles_count} rôle(s)` : 'Non attribuée'}
                </Badge>
                <PermissionStatusBadge permission={p} />
                {p.can_delete && (
                  <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={() => onDelete(p)}
                    aria-label={`Supprimer la permission ${p.name}`}
                    className="rounded-md text-muted-foreground hover:text-destructive"
                  >
                    <Trash2 className="size-3.5" />
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      </CollapsibleContent>
    </Collapsible>
  );
}
