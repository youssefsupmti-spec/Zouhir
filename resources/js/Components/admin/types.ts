/** Métadonnées d'une permission, fournies par PermissionCatalog (backend, source unique). */
export interface CatalogPermission {
  name: string
  label: string
  description: string | null
  module: string
  catalogued: boolean
  critical: boolean
  implemented: boolean
  enforced_by: string[]
  /** L'utilisateur connecté peut-il déléguer cette permission ? (calculé serveur) */
  grantable?: boolean
}

export interface CatalogModule {
  key: string
  label: string
  description: string
}

export interface PermissionCatalogData {
  modules: CatalogModule[]
  permissions: CatalogPermission[]
}
