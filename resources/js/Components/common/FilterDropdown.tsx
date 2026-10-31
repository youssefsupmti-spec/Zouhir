import { ChevronDownIcon, CheckIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export interface FilterOption {
  value: string;
  label: string;
}

interface FilterDropdownProps {
  label: string;
  value: string;
  options: FilterOption[];
  onChange: (value: string) => void;
}

/**
 * Filtre compact pour les barres d'outils serveur (Base UI : `render` au lieu de `asChild`).
 * La valeur « all » est considérée comme neutre.
 */
export function FilterDropdown({ label, value, options, onChange }: FilterDropdownProps) {
  const current = options.find((o) => o.value === value);
  const active = value !== 'all' && current;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button
            variant="outline"
            size="sm"
            className={`h-8 rounded-md text-xs ${active ? 'border-[#004c68]/40 bg-[#004c68]/5 text-[#004c68] dark:text-[#ff8806]' : ''}`}
          />
        }
      >
        <span className="text-muted-foreground">{label} :</span>
        <span className="font-semibold">{current?.label ?? 'Tous'}</span>
        <ChevronDownIcon className="size-3.5 opacity-60" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-48">
        {options.map((option) => (
          <DropdownMenuItem
            key={option.value}
            onClick={() => onChange(option.value)}
            className="cursor-pointer text-xs"
          >
            <span className="flex-1">{option.label}</span>
            {option.value === value && <CheckIcon className="size-3.5" />}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
