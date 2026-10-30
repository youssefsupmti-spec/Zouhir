import * as React from "react"
import { createColumnHelper } from "@tanstack/react-table"
import { type DataTableFeatures } from "@/components/ui/data-table"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { ArrowUpDown, MoreHorizontal, Eye, Copy, ExternalLink, CheckCircle2, Clock, Truck } from "lucide-react"

export type Parcel = {
  id: string | number
  code: string
  client: string
  phone: string
  city: string
  amount: string
  status: "livre" | "en_cours" | "transit" | "retour"
  statusLabel: string
  date: string
}

const columnHelper = createColumnHelper<DataTableFeatures, Parcel>()

export const columns = columnHelper.columns([
  columnHelper.accessor("code", {
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          size="sm"
          className="-ml-3 h-8 text-xs font-semibold hover:bg-transparent"
          onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
        >
          Code Envoi
          <ArrowUpDown className="ml-1.5 size-3.5 text-muted-foreground" />
        </Button>
      )
    },
    cell: ({ getValue }) => (
      <div className="flex items-center gap-1.5 font-mono font-bold text-foreground">
        <span>{getValue()}</span>
      </div>
    ),
  }),

  columnHelper.accessor("client", {
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          size="sm"
          className="-ml-3 h-8 text-xs font-semibold hover:bg-transparent"
          onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
        >
          Destinataire
          <ArrowUpDown className="ml-1.5 size-3.5 text-muted-foreground" />
        </Button>
      )
    },
    cell: ({ row }) => {
      return (
        <div>
          <div className="font-semibold text-foreground">{row.original.client}</div>
          <div className="text-[11px] text-muted-foreground font-mono">{row.original.phone}</div>
        </div>
      )
    },
  }),

  columnHelper.accessor("city", {
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          size="sm"
          className="-ml-3 h-8 text-xs font-semibold hover:bg-transparent"
          onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
        >
          Ville
          <ArrowUpDown className="ml-1.5 size-3.5 text-muted-foreground" />
        </Button>
      )
    },
    cell: ({ getValue }) => (
      <span className="font-medium text-foreground">{getValue()}</span>
    ),
  }),

  columnHelper.accessor("amount", {
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          size="sm"
          className="-ml-3 h-8 text-xs font-semibold hover:bg-transparent"
          onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
        >
          Montant CRBT
          <ArrowUpDown className="ml-1.5 size-3.5 text-muted-foreground" />
        </Button>
      )
    },
    cell: ({ getValue }) => (
      <span className="font-bold font-mono text-primary">{getValue()}</span>
    ),
  }),

  columnHelper.accessor("status", {
    header: "Statut Expédition",
    cell: ({ row }) => {
      const status = row.original.status
      const label = row.original.statusLabel

      if (status === "livre") {
        return (
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800 text-[11px] font-semibold gap-1">
            <CheckCircle2 className="size-3" />
            {label}
          </Badge>
        )
      }
      if (status === "en_cours") {
        return (
          <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800 text-[11px] font-semibold gap-1">
            <Clock className="size-3" />
            {label}
          </Badge>
        )
      }
      return (
        <Badge variant="outline" className="bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-400 dark:border-sky-800 text-[11px] font-semibold gap-1">
          <Truck className="size-3" />
          {label}
        </Badge>
      )
    },
  }),

  columnHelper.accessor("date", {
    header: "Date Enregistrement",
    cell: ({ getValue }) => (
      <span className="text-[11px] text-muted-foreground">{getValue()}</span>
    ),
  }),

  columnHelper.display({
    id: "actions",
    cell: ({ row }) => {
      const parcel = row.original

      return (
        <div className="text-right">
          <DropdownMenu>
            <DropdownMenuTrigger render={
              <Button variant="ghost" size="sm" className="size-8 p-0 rounded-lg hover:bg-muted">
                <span className="sr-only">Ouvrir menu</span>
                <MoreHorizontal className="size-4" />
              </Button>
            } />
            <DropdownMenuContent align="end" className="w-48">
              <DropdownMenuLabel className="text-xs">Actions Colis</DropdownMenuLabel>
              <DropdownMenuItem
                className="text-xs cursor-pointer gap-2"
                onClick={() => navigator.clipboard.writeText(parcel.code)}
              >
                <Copy className="size-3.5 text-muted-foreground" />
                Copier le code
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem className="text-xs cursor-pointer gap-2">
                <Eye className="size-3.5 text-muted-foreground" />
                Voir détails
              </DropdownMenuItem>
              <DropdownMenuItem className="text-xs cursor-pointer gap-2">
                <ExternalLink className="size-3.5 text-muted-foreground" />
                Suivre en direct
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      )
    },
  }),
])
