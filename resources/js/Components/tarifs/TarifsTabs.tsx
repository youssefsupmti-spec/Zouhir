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
    'bg-[#004c68]/10 text-[#004c68] dark:bg-[#ff8806]/15 dark:text-[#ff8806] group-data-active/onglet:bg-white/20 group-data-active/onglet:text-white dark:group-data-active/onglet:bg-[#05161f]/15 dark:group-data-active/onglet:text-[#05161f]',
  danger:
    'bg-destructive/10 text-destructive group-data-active/onglet:bg-white/20 group-data-active/onglet:text-white',
  muted:
    'bg-foreground/[0.06] text-muted-foreground group-data-active/onglet:bg-white/20 group-data-active/onglet:text-white dark:group-data-active/onglet:text-[#05161f]',
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
          className="h-auto w-max gap-0.5 rounded-lg border border-border bg-card p-0.5 shadow-none group-data-horizontal/tabs:h-auto"
        >
          {onglets.map((o) => (
            <TabsTrigger
              key={o.value}
              value={o.value}
              className={[
                'group/onglet h-8 flex-none gap-1.5 rounded-md border-0 px-3 text-xs font-medium text-muted-foreground shadow-none',
                'transition-colors duration-150 hover:text-foreground after:hidden',
                // Onglet actif : fond plein de la marque (orange en mode sombre), sans ombre.
                'data-active:bg-[#004c68] data-active:text-white data-active:shadow-none data-active:hover:text-white',
                'dark:data-active:bg-[#ff8806] dark:data-active:text-[#05161f] dark:data-active:hover:text-[#05161f]',
                '[&_svg]:opacity-70 data-active:[&_svg]:opacity-100',
                o.tone === 'danger' ? 'data-active:bg-destructive dark:data-active:bg-destructive dark:data-active:text-white' : '',
              ].join(' ')}
            >
              {o.icon}
              <span>{o.label}</span>
              <span
                className={`inline-flex h-[18px] min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-semibold tabular-nums transition-colors duration-150 ${BADGE[o.tone ?? 'default']}`}
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
