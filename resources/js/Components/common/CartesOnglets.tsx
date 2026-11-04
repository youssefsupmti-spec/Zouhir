import type { ReactNode } from 'react';

export interface CarteOnglet {
  value: string;
  label: string;
  count: number;
  description?: string | null;
  /** Couleur du groupe (#RRGGBB) : pastille et soulignement de l'onglet actif. */
  couleur?: string | null;
  icon?: ReactNode;
}

/**
 * Onglets-cartes sobres : une case par groupe (pastille de couleur, libellé, compteur), séparées
 * par un filet. Pas d'ombre ni d'effet : l'onglet actif est marqué par un soulignement de sa couleur.
 * Filtre SERVEUR : chaque case déclenche une navigation.
 */
export function CartesOnglets({ value, onglets, onChange, label, colonnes = 'sm:grid-cols-4 lg:grid-cols-8' }: { value: string; onglets: CarteOnglet[]; onChange: (v: string) => void; label: string; colonnes?: string }) {
  return (
    <div role="tablist" aria-label={label} className={`grid grid-cols-2 overflow-hidden rounded-lg border border-border bg-card ${colonnes} divide-x divide-y divide-border sm:divide-y-0`}>
      {onglets.map((o) => {
        const actif = o.value === value;
        const couleur = o.couleur ?? '#004c68';
        return (
          <button
            key={o.value}
            type="button"
            role="tab"
            aria-selected={actif}
            title={o.description ?? undefined}
            onClick={() => onChange(o.value)}
            style={{ ['--c' as string]: couleur }}
            className={`relative px-3 pb-2.5 pt-2 text-left outline-none focus-visible:bg-muted/60 ${actif ? 'bg-muted/40' : ''}`}
          >
            <span className="flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
              <span className="size-2 shrink-0 rounded-full bg-[var(--c)]" aria-hidden />
              <span className="truncate">{o.label}</span>
            </span>
            <span className={`mt-0.5 block text-lg font-semibold tabular-nums leading-tight ${actif ? 'text-foreground' : 'text-foreground/80'}`}>
              {o.count.toLocaleString('fr-FR')}
            </span>
            <span className={`absolute inset-x-0 bottom-0 h-0.5 ${actif ? 'bg-[var(--c)]' : 'bg-transparent'}`} aria-hidden />
          </button>
        );
      })}
    </div>
  );
}
