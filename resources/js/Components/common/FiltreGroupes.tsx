import { Trash2 } from 'lucide-react';

export interface Groupe {
  value: string;
  label: string;
  count: number;
  couleur?: string | null;
  description?: string | null;
}

/**
 * Filtre par groupe d'état, propre à OUAISS EXPRESS :
 *  · une barre de répartition (chaque segment = la part réelle du groupe, cliquable) ;
 *  · une rangée de pastilles « point de couleur · libellé · compteur » ;
 *  · la corbeille à part, à droite.
 * Sans ombre ni effet : l'état actif est un contour et un fond légers de la couleur du groupe.
 */
export function FiltreGroupes({ value, tous, groupes, corbeille, onChange }: {
  value: string;
  tous: number;
  groupes: Groupe[];
  corbeille: number;
  onChange: (v: string) => void;
}) {
  const total = groupes.reduce((n, g) => n + g.count, 0);

  const pastille = (v: string, label: string, count: number, couleur: string, titre?: string | null) => {
    const actif = value === v;
    return (
      <button
        key={v}
        type="button"
        role="tab"
        aria-selected={actif}
        title={titre ?? undefined}
        onClick={() => onChange(v)}
        style={{ ['--c' as string]: couleur }}
        className={`inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-xs transition-colors ${actif
          ? 'border-[var(--c)] bg-[color-mix(in_srgb,var(--c)_10%,transparent)] font-semibold text-foreground'
          : 'border-border bg-card text-muted-foreground hover:border-[color-mix(in_srgb,var(--c)_45%,transparent)] hover:text-foreground'}`}
      >
        <span className="size-2 rounded-full bg-[var(--c)]" aria-hidden />
        {label}
        <span className={`tabular-nums ${actif ? 'text-[var(--c)]' : ''}`}>{count.toLocaleString('fr-FR')}</span>
      </button>
    );
  };

  return (
    <div className="space-y-2.5">
      {total > 0 && (
        <div className="flex h-2 w-full overflow-hidden rounded-full bg-muted" aria-hidden>
          {groupes.filter((g) => g.count > 0).map((g) => (
            <button
              key={g.value}
              type="button"
              tabIndex={-1}
              title={`${g.label} : ${g.count.toLocaleString('fr-FR')} (${Math.round((g.count / total) * 1000) / 10} %)`}
              onClick={() => onChange(g.value)}
              className={`h-full transition-opacity ${value === 'tous' || value === g.value ? 'opacity-100' : 'opacity-35'}`}
              style={{ width: `${(g.count / total) * 100}%`, background: g.couleur ?? '#94a3b8', minWidth: 3 }}
            />
          ))}
        </div>
      )}

      <div className="flex items-center gap-2">
        <div role="tablist" aria-label="Groupes de colis" className="-mx-1 flex min-w-0 flex-1 gap-1.5 overflow-x-auto px-1 pb-1">
          {pastille('tous', 'Tous', tous, '#004c68', 'Tous les colis hors corbeille')}
          {groupes.map((g) => pastille(g.value, g.label, g.count, g.couleur ?? '#94a3b8', g.description))}
        </div>
        <button
          type="button"
          role="tab"
          aria-selected={value === 'corbeille'}
          onClick={() => onChange('corbeille')}
          className={`mb-1 inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs ${value === 'corbeille'
            ? 'border-destructive bg-destructive/10 font-semibold text-destructive'
            : 'border-dashed border-border text-muted-foreground hover:text-foreground'}`}
        >
          <Trash2 className="size-3.5" /> Corbeille <span className="tabular-nums">{corbeille.toLocaleString('fr-FR')}</span>
        </button>
      </div>
    </div>
  );
}
