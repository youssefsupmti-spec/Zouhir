import * as React from 'react';
import { ChevronRightIcon, LockIcon, SearchIcon, ShieldAlertIcon, XIcon } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { CatalogPermission, PermissionCatalogData } from './types';

interface RolePermissionMatrixProps {
  catalog: PermissionCatalogData;
  selected: string[];
  onChange?: (next: string[]) => void;
  /** Lecture seule : fiche de détails. */
  readOnly?: boolean;
  /** Permissions qui ne peuvent pas être retirées (ex. critiques du rôle admin). */
  locked?: string[];
  /** En lecture seule, n'afficher que les permissions sélectionnées. */
  onlySelected?: boolean;
}

/**
 * Matrice de permissions groupée par module (catalogue centralisé backend).
 * Une permission non délégable par l'utilisateur connecté (`grantable === false`)
 * ne peut être ni cochée ni décochée : la règle est revérifiée côté serveur.
 */
export function RolePermissionMatrix({
  catalog,
  selected,
  onChange,
  readOnly = false,
  locked = [],
  onlySelected = false,
}: RolePermissionMatrixProps) {
  const [query, setQuery] = React.useState('');
  const selectedSet = React.useMemo(() => new Set(selected), [selected]);
  const lockedSet = React.useMemo(() => new Set(locked), [locked]);

  const normalized = query.trim().toLowerCase();
  const visible = React.useMemo(
    () =>
      catalog.permissions.filter((p) => {
        if (onlySelected && !selectedSet.has(p.name)) return false;
        if (!normalized) return true;
        return `${p.label} ${p.name} ${p.description ?? ''}`.toLowerCase().includes(normalized);
      }),
    [catalog.permissions, normalized, onlySelected, selectedSet]
  );

  const isEditable = (p: CatalogPermission) =>
    !readOnly && p.grantable !== false && !(lockedSet.has(p.name) && selectedSet.has(p.name));

  const apply = (names: string[], checked: boolean) => {
    if (!onChange) return;
    const next = new Set(selectedSet);
    names.forEach((n) => (checked ? next.add(n) : next.delete(n)));
    onChange(catalog.permissions.map((p) => p.name).filter((n) => next.has(n)));
  };

  const editableVisible = visible.filter(isEditable).map((p) => p.name);
  const groups = catalog.modules
    .map((module) => ({ module, items: visible.filter((p) => p.module === module.key) }))
    .filter((g) => g.items.length > 0);

  return (
    <div className="space-y-3">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <SearchIcon className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Rechercher une permission (nom, clé, description)…"
            className="h-8 rounded-md pr-8 pl-8 text-xs"
            aria-label="Rechercher une permission"
          />
          {query && (
            <button
              type="button"
              onClick={() => setQuery('')}
              className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              aria-label="Effacer la recherche"
            >
              <XIcon className="size-3.5" />
            </button>
          )}
        </div>
        {!readOnly && (
          <div className="flex shrink-0 gap-1.5">
            <Button type="button" variant="outline" size="sm" className="h-8 rounded-md text-xs"
              disabled={editableVisible.length === 0} onClick={() => apply(editableVisible, true)}>
              Tout sélectionner
            </Button>
            <Button type="button" variant="ghost" size="sm" className="h-8 rounded-md text-xs"
              disabled={editableVisible.length === 0} onClick={() => apply(editableVisible, false)}>
              Tout désélectionner
            </Button>
          </div>
        )}
      </div>

      <p className="text-[11px] text-muted-foreground">
        <span className="font-semibold text-foreground">{selected.length}</span> permission(s) sélectionnée(s) sur{' '}
        {catalog.permissions.length}
      </p>

      {groups.length === 0 && (
        <div className="rounded-md border border-dashed border-border p-6 text-center text-xs text-muted-foreground">
          Aucune permission ne correspond à cette recherche.
        </div>
      )}

      <div className="space-y-2">
        {groups.map(({ module, items }) => {
          const total = catalog.permissions.filter((p) => p.module === module.key).length;
          const count = catalog.permissions.filter((p) => p.module === module.key && selectedSet.has(p.name)).length;
          const editable = items.filter(isEditable).map((p) => p.name);
          const allChecked = editable.length > 0 && editable.every((n) => selectedSet.has(n));
          const someChecked = editable.some((n) => selectedSet.has(n));

          return (
            <Collapsible key={module.key} defaultOpen className="group/module rounded-md border border-border bg-card">
              <div className="flex items-center gap-2.5 px-3 py-2">
                {!readOnly && (
                  <Checkbox
                    checked={allChecked}
                    indeterminate={!allChecked && someChecked}
                    disabled={editable.length === 0}
                    onCheckedChange={(checked) => apply(editable, Boolean(checked))}
                    aria-label={`Sélectionner tout le module ${module.label}`}
                    className="rounded-[3px]"
                  />
                )}
                <CollapsibleTrigger className="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-left">
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-xs font-semibold text-foreground">{module.label}</div>
                    <div className="truncate text-[11px] text-muted-foreground">{module.description}</div>
                  </div>
                  <Badge variant="outline" className="rounded-md font-mono text-[10px]">
                    {count}/{total}
                  </Badge>
                  <ChevronRightIcon className="size-4 text-muted-foreground transition-transform group-data-open/module:rotate-90" />
                </CollapsibleTrigger>
              </div>

              <CollapsibleContent>
                <ul className="grid gap-px border-t border-border bg-border sm:grid-cols-2">
                  {items.map((p) => {
                    const checked = selectedSet.has(p.name);
                    const editableItem = isEditable(p);
                    const id = `perm-${p.name.replace(/[^a-zA-Z0-9_-]/g, '_')}`;
                    return (
                      <li key={p.name} className="bg-card">
                        <label
                          htmlFor={id}
                          className={cn(
                            'flex h-full items-start gap-2.5 px-3 py-2.5',
                            editableItem ? 'cursor-pointer hover:bg-muted/40' : 'cursor-default',
                            !checked && readOnly && 'opacity-60'
                          )}
                        >
                          {!readOnly && (
                            <Checkbox
                              id={id}
                              checked={checked}
                              disabled={!editableItem}
                              onCheckedChange={(v) => apply([p.name], Boolean(v))}
                              className="mt-0.5 rounded-[3px]"
                            />
                          )}
                          <span className="min-w-0 flex-1">
                            <span className="flex flex-wrap items-center gap-1.5">
                              <span className="text-xs font-medium text-foreground">{p.label}</span>
                              {p.critical && (
                                <ShieldAlertIcon className="size-3.5 text-[#ff8806]" aria-label="Permission critique" />
                              )}
                              {lockedSet.has(p.name) && checked && (
                                <LockIcon className="size-3 text-muted-foreground" aria-label="Verrouillée" />
                              )}
                              {!p.implemented && (
                                <Badge variant="outline" className="h-4 rounded-md px-1 text-[9px] text-muted-foreground">
                                  Non reliée
                                </Badge>
                              )}
                            </span>
                            <code className="block truncate font-mono text-[10px] text-muted-foreground">{p.name}</code>
                            {p.description && (
                              <span className="mt-0.5 block text-[11px] leading-snug text-muted-foreground">{p.description}</span>
                            )}
                            {!readOnly && p.grantable === false && (
                              <span className="mt-0.5 block text-[10px] text-destructive">Vous ne pouvez pas déléguer cette permission.</span>
                            )}
                          </span>
                        </label>
                      </li>
                    );
                  })}
                </ul>
              </CollapsibleContent>
            </Collapsible>
          );
        })}
      </div>
    </div>
  );
}
