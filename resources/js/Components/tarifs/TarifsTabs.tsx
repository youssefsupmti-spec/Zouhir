import type { ReactNode } from 'react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

export interface OngletTarif {
  value: string;
  label: string;
  /** Compteur calculé côté serveur : rafraîchi à chaque rechargement Inertia (après toute action). */
  count: number;
  icon?: ReactNode;
  /** Ton du badge (ex. destructif pour la corbeille). */
  tone?: 'default' | 'danger' | 'muted';
}

interface Props {
  value: string;
  onValueChange: (value: string) => void;
  onglets: OngletTarif[];
  label: string;
}

/*
 * Badge de compteur : discret au repos, plein quand l'onglet est actif.
 * `group-data-active/onglet` lit l'état actif posé par Base UI sur le TabsTrigger.
 */
const BADGE: Record<NonNullable<OngletTarif['tone']>, string> = {
  default:
    'bg-foreground/[0.06] text-muted-foreground group-hover/onglet:text-foreground group-data-active/onglet:bg-[#004c68] group-data-active/onglet:text-white dark:group-data-active/onglet:bg-[#ff8806] dark:group-data-active/onglet:text-[#05161f]',
  danger:
    'bg-destructive/10 text-destructive group-data-active/onglet:bg-destructive group-data-active/onglet:text-white',
  muted:
    'bg-foreground/[0.06] text-muted-foreground group-hover/onglet:text-foreground group-data-active/onglet:bg-foreground/80 group-data-active/onglet:text-background',
};

/**
 * Onglets du domaine Tarifs — structure shadcn/ui inchangée (Tabs › TabsList › TabsTrigger, Base UI),
 * habillée en « segmented control » : piste neutre, onglet actif en surface élevée (fond carte,
 * ombre légère, libellé et icône à la couleur de marque), badge de compteur plein à l'état actif.
 *
 * Les onglets pilotent un filtre SERVEUR (navigation Inertia) : aucun contenu n'est caché côté
 * client, et les compteurs reviennent à jour avec les props de la page après chaque action.
 */
export function TarifsTabs({ value, onValueChange, onglets, label }: Props) {
  return (
    <Tabs value={value} onValueChange={(v) => onValueChange(String(v))} className="min-w-0">
      <div className="-mx-1 overflow-x-auto px-1 pb-1">
        <TabsList
          aria-label={label}
          className="h-auto w-max gap-1 rounded-lg group-data-horizontal/tabs:h-auto border border-border/70 bg-muted/60 p-1 shadow-inner shadow-black/[0.03] dark:bg-[#0a2533]/60"
        >
          {onglets.map((o) => (
            <TabsTrigger
              key={o.value}
              value={o.value}
              className={[
                'group/onglet h-8 flex-none gap-2 rounded-md px-3 text-xs font-medium text-muted-foreground',
                'transition-[background-color,color,box-shadow] duration-200 ease-out',
                'hover:bg-background/60 hover:text-foreground',
                // État actif : surface élevée et couleur de marque.
                'data-active:bg-background data-active:font-semibold data-active:text-[#004c68] data-active:shadow-sm data-active:ring-1 data-active:ring-border/80',
                'dark:data-active:border-transparent dark:data-active:bg-[#05161f] dark:data-active:text-[#ff8806] dark:data-active:ring-white/10',
                // Icône : atténuée au repos, colorée à l'état actif.
                '[&_svg]:opacity-60 data-active:[&_svg]:opacity-100',
                o.tone === 'danger' ? 'data-active:text-destructive dark:data-active:text-red-400' : '',
              ].join(' ')}
            >
              {o.icon}
              <span>{o.label}</span>
              <span
                className={`inline-flex h-[18px] min-w-[22px] items-center justify-center rounded-full px-1.5 font-mono text-[10px] font-semibold tabular-nums transition-colors duration-200 ${BADGE[o.tone ?? 'default']}`}
                aria-label={`${o.count} élément(s)`}
              >
                {o.count.toLocaleString('fr-FR')}
              </span>
            </TabsTrigger>
          ))}
        </TabsList>
      </div>
    </Tabs>
  );
}
