import * as React from 'react';
import { router } from '@inertiajs/react';
import {
  columnFilteringFeature,
  columnVisibilityFeature,
  createSortedRowModel,
  rowSelectionFeature,
  rowSortingFeature,
  tableFeatures,
  useTable,
  type ColumnDef,
  type RowData,
  type SortingState,
} from '@tanstack/react-table';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SlidersHorizontal, ArrowUpDown, Inbox } from 'lucide-react';
import { DataTablePagination, LaravelPaginationMeta } from './DataTablePagination';

export const features = tableFeatures({
  columnFilteringFeature,
  columnVisibilityFeature,
  rowSelectionFeature,
  rowSortingFeature,
  sortedRowModel: createSortedRowModel(),
});

export type DataTableFeatures = typeof features;

interface ServerDataTableProps<TData extends RowData> {
  columns: ColumnDef<DataTableFeatures, TData>[];
  data: TData[];
  paginationMeta?: LaravelPaginationMeta;
  routeName?: string;
  currentParams?: Record<string, any>;
  emptyMessage?: string;
  showColumnVisibility?: boolean;
}

export function ServerDataTable<TData extends RowData>({
  columns,
  data,
  paginationMeta,
  routeName,
  currentParams = {},
  emptyMessage = 'Aucune donnée disponible',
  showColumnVisibility = true,
}: ServerDataTableProps<TData>) {
  const [columnVisibility, setColumnVisibility] = React.useState({});
  const [rowSelection, setRowSelection] = React.useState({});

  const sorting = React.useMemo<SortingState>(() => {
    if (currentParams.sort) {
      return [{ id: currentParams.sort, desc: currentParams.direction === 'desc' }];
    }
    return [];
  }, [currentParams.sort, currentParams.direction]);

  const onSortingChange = (updaterOrValue: any) => {
    const nextSorting = typeof updaterOrValue === 'function' ? updaterOrValue(sorting) : updaterOrValue;
    if (nextSorting.length > 0) {
      const sortField = nextSorting[0].id;
      const sortDirection = nextSorting[0].desc ? 'desc' : 'asc';
      const queryParams = { ...currentParams, sort: sortField, direction: sortDirection, page: 1 };
      const targetUrl = routeName ? route(routeName, queryParams) : window.location.pathname;

      router.get(targetUrl, queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      });
    } else {
      const queryParams = { ...currentParams, sort: undefined, direction: undefined };
      const targetUrl = routeName ? route(routeName, queryParams) : window.location.pathname;

      router.get(targetUrl, queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      });
    }
  };

  const table = useTable({
    features,
    data,
    columns,
    state: {
      columnVisibility,
      rowSelection,
      sorting,
    },
    onColumnVisibilityChange: setColumnVisibility,
    onRowSelectionChange: setRowSelection,
    onSortingChange,
  });

  return (
    <div className="space-y-3">
      {showColumnVisibility && (
        <div className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger render={
              <Button variant="outline" size="sm" className="h-8 text-xs rounded-md gap-1.5 font-medium">
                <SlidersHorizontal className="h-3.5 w-3.5 text-muted-foreground" />
                Colonnes
              </Button>
            } />
            <DropdownMenuContent align="end" className="w-44">
              {table
                .getAllColumns()
                .filter((column) => column.getCanHide())
                .map((column) => (
                  <DropdownMenuCheckboxItem
                    key={column.id}
                    className="capitalize text-xs cursor-pointer"
                    checked={column.getIsVisible()}
                    onCheckedChange={(value) => column.toggleVisibility(!!value)}
                  >
                    {column.id}
                  </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      )}

      {/* Table Container */}
      <div className="overflow-hidden rounded-md border border-border bg-card shadow-2xs">
        <Table>
          <TableHeader className="bg-muted/40">
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id} className="hover:bg-transparent">
                {headerGroup.headers.map((header) => {
                  return (
                    <TableHead key={header.id} className="h-10 text-xs font-semibold text-foreground/80">
                      {header.isPlaceholder
                        ? null
                        : header.renderHeader()}
                    </TableHead>
                  );
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {data && data.length > 0 ? (
              table.getRowModel().rows.map((row) => (
                <TableRow
                  key={row.id}
                  data-state={row.getIsSelected() && 'selected'}
                  className="hover:bg-muted/30 transition-colors border-b border-border/60"
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id} className="py-2.5 text-xs text-foreground">
                      {cell.renderCell()}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell
                  colSpan={columns.length}
                  className="h-36 text-center text-muted-foreground"
                >
                  <div className="flex flex-col items-center justify-center gap-2">
                    <Inbox className="h-8 w-8 text-muted-foreground/40 stroke-1" />
                    <span className="text-xs font-medium">{emptyMessage}</span>
                  </div>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>

        {/* Pagination Serveur */}
        {paginationMeta && (
          <DataTablePagination
            meta={paginationMeta}
            routeName={routeName}
            currentParams={currentParams}
          />
        )}
      </div>
    </div>
  );
}
