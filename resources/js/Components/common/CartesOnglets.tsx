import type { ReactNode } from 'react';

export interface CarteOnglet {
  value: string;
  label: string;
  count: number;
  description?: string | null;
  /** Couleur du groupe (#RRGGBB) : liseré, icône et compteur. */
  couleur?: string | null;
  icon?: ReactNode;
}

/**
 * Onglets en cartes (inspirés des « CardTabs » de Tous les colis, WinningExpress) : une carte par
 * groupe, avec son compteur et sa couleur. Filtre SERVEUR : chaque carte est un lien de navigation.
 */
export function CartesOnglets({ value, onglets, onChange, label, colonnes = 'sm:grid-cols-4 lg:grid-cols-8' }: { value: string; onglets: CarteOnglet[]; onChange: (v: string) => void; label: string; colonnes?: string }) {
  return (
    <div role="tablist" aria-label={label} className={`grid grid-cols-2 gap-2 ${colonnes}`}>
      {onglets.map((o) => {
        const actif = o.value === value;
        const couleur = o.couleur ?? '#004c68';
        return (
          <button
            key={o.value}
            type="button"
            role="tab"
            aria-selected={actif}
            onClick={() => onChange(o.value)}
            style={{ ['--c' as string]: couleur }}
            className={`group relative overflow-hidden rounded-lg border p-3 text-left transition ${actif ? 'border-[var(--c)] bg-card shadow-md ring-1 ring-[var(--c)]' : 'border-border bg-card/70 hover:border-[var(--c)]/60 hover:bg-card'}`}
          >
            <span className="absolute inset-x-0 top-0 h-1 bg-[var(--c)]" style={{ opacity: actif ? 1 : 0.45 }} aria-hidden />
            <div className="flex items-center justify-between gap-2">
              <span className="text-[var(--c)] [&_svg]:size-4">{o.icon}</span>
              <span className={`rounded-full px-1.5 text-[10px] font-bold tabular-nums ${actif ? 'bg-[var(--c)] text-white' : 'bg-muted text-muted-foreground'}`}>
                {o.count.toLocaleString('fr-FR')}
              </span>
            </div>
            <p className={`mt-2 truncate text-xs font-semibold ${actif ? 'text-foreground' : 'text-muted-foreground group-hover:text-foreground'}`}>{o.label}</p>
            {o.description && <p className="truncate text-[10px] text-muted-foreground" title={o.description}>{o.description}</p>}
          </button>
        );
      })}
    </div>
  );
}
