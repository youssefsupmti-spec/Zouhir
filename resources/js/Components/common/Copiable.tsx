import { useState, type ReactNode } from 'react';
import { Check, Copy } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

/**
 * Valeur cliquable qui se copie dans le presse-papiers (référence colis, téléphone, code de bon).
 * Infobulle « Copier » puis « Copié ».
 */
export function Copiable({ valeur, children, className = '' }: { valeur: string; children?: ReactNode; className?: string }) {
  const [copie, setCopie] = useState(false);

  const copier = async (e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      await navigator.clipboard.writeText(valeur);
    } catch {
      const zone = document.createElement('textarea');
      zone.value = valeur;
      document.body.appendChild(zone);
      zone.select();
      document.execCommand('copy');
      zone.remove();
    }
    setCopie(true);
    setTimeout(() => setCopie(false), 1500);
  };

  return (
    <Tooltip>
      <TooltipTrigger
        render={(
          <button
            type="button"
            onClick={copier}
            aria-label={`Copier ${valeur}`}
            className={`group/copie inline-flex max-w-full items-center gap-1 rounded-sm text-left focus-visible:outline-2 focus-visible:outline-[#004c68] ${className}`}
          />
        )}
      >
        <span className="truncate">{children ?? valeur}</span>
        {copie
          ? <Check className="size-3 shrink-0 text-emerald-600" />
          : <Copy className="size-3 shrink-0 opacity-0 transition-opacity group-hover/copie:opacity-60" />}
      </TooltipTrigger>
      <TooltipContent>{copie ? 'Copié' : `Copier ${valeur}`}</TooltipContent>
    </Tooltip>
  );
}
