import React from 'react';
import { router } from '@inertiajs/react';
import { 
  ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight 
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export interface LaravelPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  path?: string;
}

interface DataTablePaginationProps {
  meta: LaravelPaginationMeta;
  routeName?: string;
  currentParams?: Record<string, any>;
  pageSizeOptions?: number[];
}

export function DataTablePagination({
  meta,
  routeName,
  currentParams = {},
  pageSizeOptions = [10, 25, 50, 100],
}: DataTablePaginationProps) {
  const { current_page, last_page, per_page, total, from, to } = meta;

  const navigateTo = (page: number, newPerPage?: number) => {
    if (page < 1 || page > last_page) return;

    const queryParams = {
      ...currentParams,
      page,
      per_page: newPerPage || per_page,
    };

    const targetUrl = routeName ? route(routeName, queryParams) : window.location.pathname;

    router.get(targetUrl, queryParams, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 px-2 py-3 border-t border-border bg-card/50">
      {/* Information de tranche */}
      <div className="text-xs text-muted-foreground">
        {total > 0 ? (
          <>
            Affichage de <span className="font-semibold text-foreground">{from}</span> à{' '}
            <span className="font-semibold text-foreground">{to}</span> sur{' '}
            <span className="font-semibold text-foreground">{total}</span> résultats
          </>
        ) : (
          <span>Aucun résultat à afficher</span>
        )}
      </div>

      <div className="flex items-center gap-4">
        {/* Sélecteur de taille par page */}
        <div className="flex items-center gap-2">
          <span className="text-xs text-muted-foreground">Lignes :</span>
          <DropdownMenu>
            <DropdownMenuTrigger render={
              <Button variant="outline" size="sm" className="h-8 px-2 text-xs rounded-md">
                {per_page}
              </Button>
            } />
            <DropdownMenuContent align="end" className="w-20">
              {pageSizeOptions.map((size) => (
                <DropdownMenuItem
                  key={size}
                  onClick={() => navigateTo(1, size)}
                  className="text-xs justify-center cursor-pointer"
                >
                  {size}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        {/* Boutons de pagination */}
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="icon"
            className="h-8 w-8 rounded-md"
            onClick={() => navigateTo(1)}
            disabled={current_page <= 1}
            title="Première page"
          >
            <ChevronsLeft className="h-4 w-4" />
          </Button>

          <Button
            variant="outline"
            size="icon"
            className="h-8 w-8 rounded-md"
            onClick={() => navigateTo(current_page - 1)}
            disabled={current_page <= 1}
            title="Page précédente"
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>

          <span className="text-xs px-2 font-medium text-foreground">
            Page {current_page} / {last_page || 1}
          </span>

          <Button
            variant="outline"
            size="icon"
            className="h-8 w-8 rounded-md"
            onClick={() => navigateTo(current_page + 1)}
            disabled={current_page >= last_page}
            title="Page suivante"
          >
            <ChevronRight className="h-4 w-4" />
          </Button>

          <Button
            variant="outline"
            size="icon"
            className="h-8 w-8 rounded-md"
            onClick={() => navigateTo(last_page)}
            disabled={current_page >= last_page}
            title="Dernière page"
          >
            <ChevronsRight className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  );
}
