import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Search, X, SlidersHorizontal } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

interface PageToolbarProps {
  searchPlaceholder?: string;
  initialSearch?: string;
  routeName?: string;
  currentParams?: Record<string, any>;
  children?: React.ReactNode;
  rightActions?: React.ReactNode;
}

export function PageToolbar({
  searchPlaceholder = 'Rechercher...',
  initialSearch = '',
  routeName,
  currentParams = {},
  children,
  rightActions,
}: PageToolbarProps) {
  const [searchTerm, setSearchTerm] = useState(initialSearch);

  // Debounced search vers le serveur Laravel
  useEffect(() => {
    const handler = setTimeout(() => {
      if (searchTerm !== (currentParams.search || '')) {
        const queryParams = {
          ...currentParams,
          search: searchTerm || undefined,
          page: 1, // Reset page sur nouvelle recherche
        };

        const targetUrl = routeName ? route(routeName, queryParams) : window.location.pathname;

        router.get(targetUrl, queryParams, {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        });
      }
    }, 350);

    return () => clearTimeout(handler);
  }, [searchTerm]);

  const handleClear = () => {
    setSearchTerm('');
    const queryParams = { ...currentParams, search: undefined, page: 1 };
    const targetUrl = routeName ? route(routeName, queryParams) : window.location.pathname;

    router.get(targetUrl, queryParams, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  return (
    <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3 bg-card border border-border rounded-md shadow-2xs">
      <div className="flex flex-1 flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        {/* Champ de recherche */}
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder={searchPlaceholder}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-9 pr-8 h-9 text-xs rounded-md bg-background"
          />
          {searchTerm && (
            <button
              onClick={handleClear}
              className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              title="Effacer la recherche"
            >
              <X className="h-3.5 w-3.5" />
            </button>
          )}
        </div>

        {/* Filtres personnalisés (dropdowns, selects) */}
        {children}
      </div>

      {/* Actions à droite */}
      {rightActions && (
        <div className="flex items-center gap-2 shrink-0">
          {rightActions}
        </div>
      )}
    </div>
  );
}
