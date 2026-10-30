import React from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type StatusType = 
  | 'active' | 'inactive' | 'pending'
  | 'livre' | 'en_cours' | 'nouveau' | 'retour' | 'annule'
  | 'tournee' | 'disponible' | 'repos'
  | 'default' | string;

interface StatusBadgeProps {
  status: StatusType;
  label?: string;
  className?: string;
}

const statusConfig: Record<string, { label: string; className: string }> = {
  // Statuts généraux
  active: {
    label: 'Actif',
    className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
  },
  inactive: {
    label: 'Inactif',
    className: 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/20',
  },
  pending: {
    label: 'En attente',
    className: 'bg-amber-500/15 text-amber-800 dark:text-amber-200 border-amber-500/20',
  },

  // Statuts Colis
  livre: {
    label: 'Livré',
    className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
  },
  en_cours: {
    label: 'En distribution',
    className: 'bg-[#004c68]/15 text-[#004c68] dark:text-cyan-300 border-[#004c68]/20',
  },
  nouveau: {
    label: 'Nouveau colis',
    className: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/20',
  },
  retour: {
    label: 'Retour',
    className: 'bg-orange-500/15 text-orange-800 dark:text-orange-200 border-orange-500/20',
  },
  annule: {
    label: 'Annulé',
    className: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/20',
  },

  // Statuts Livreurs
  tournee: {
    label: 'En tournée',
    className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
  },
  disponible: {
    label: 'Au hub',
    className: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/20',
  },
  repos: {
    label: 'En repos',
    className: 'bg-slate-500/15 text-slate-600 dark:text-slate-400 border-slate-500/20',
  },
};

export function StatusBadge({ status, label, className }: StatusBadgeProps) {
  const normalizedKey = (status || '').toLowerCase().trim();
  const config = statusConfig[normalizedKey] || {
    label: label || status || 'Standard',
    className: 'bg-muted text-foreground border-border',
  };

  return (
    <Badge
      variant="outline"
      className={cn(
        "rounded-md text-xs font-medium px-2 py-0.5 border shadow-2xs",
        config.className,
        className
      )}
    >
      {label || config.label}
    </Badge>
  );
}

