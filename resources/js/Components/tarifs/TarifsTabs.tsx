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

const TONS: Record<NonNullable<OngletTarif['tone']>, string> = {
  default: 'bg-[#004c68]/10 text-[#004c68] group-data-active/onglet:bg-[#004c68] group-data-active/onglet:text-white dark:text-[#ff8806] dark:group-data-active/onglet:bg-[#ff8806] dark:group-data-active/onglet:text-[#05161f]',
  danger: 'bg-destructive/10 text-destructive group-data-active/onglet:bg-destructive group-data-active/onglet:text-white',
  muted: 'bg-muted text-muted-foreground group-data-active/onglet:bg-foreground/80 group-data-active/onglet:text-background',
};

/**
 * Onglets du domaine Tarifs (shadcn/ui Tabs sur Base UI) : libellé + badge de compteur.
 * Les onglets pilotent un filtre SERVEUR (navigation Inertia) : aucun contenu n'est
 * caché côté client, et les compteurs reviennent à jour avec les props de la page.
 */
export function TarifsTabs({ value, onValueChange, onglets, label }: Props) {
  return (
    <Tabs value={value} onValueChange={(v) => onValueChange(String(v))} className="min-w-0">
      <div className="overflow-x-auto">
        <TabsList variant="line" aria-label={label} className="h-9 w-max border-b border-border">
          {onglets.map((o) => (
            <TabsTrigger key={o.value} value={o.value} className="group/onglet h-8 flex-none gap-2 px-3 text-xs">
              {o.icon}
              <span>{o.label}</span>
              <span className={`inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-px font-mono text-[10px] font-semibold tabular-nums transition-colors ${TONS[o.tone ?? 'default']}`}
                aria-label={`${o.count} élément(s)`}>
                {o.count}
              </span>
            </TabsTrigger>
          ))}
        </TabsList>
      </div>
    </Tabs>
  );
}
