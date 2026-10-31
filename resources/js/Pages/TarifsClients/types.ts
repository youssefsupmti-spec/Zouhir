// Contrats de données de l'écran « Tarifs Clients » (Sprint 28).
// Tous les montants arrivent du serveur en chaînes décimales exactes (« 15.00 ») ;
// aucune résolution tarifaire n'est faite côté React.

export type Comparaison = 'superieur' | 'inferieur' | 'egal' | 'indisponible';

export interface ClientResume {
  id: number;
  nom: string;
  email: string | null;
  telephone: string | null;
  enseignes: string[];
  nb_tarifs: number;
  nb_zones: number;
  livraison_min: string;
  livraison_max: string;
}

export interface LigneTarif {
  id: number;
  ville_id: number;
  ville_nom: string;
  livraison: string;
  refus: string;
  retour: string;
  standard: string | null;
  comparaison: Comparaison;
  gratuit: boolean;
  updated_at: string | null;
}

export interface ZoneGrille {
  zone_id: number | null;
  zone_nom: string;
  zone_code: string | null;
  lignes: LigneTarif[];
}

export interface ZoneRef {
  id: number;
  name: string;
  code: string | null;
}

export interface Capacites {
  create: boolean;
  update: boolean;
  delete: boolean;
}

export interface Referentiel {
  clients: { id: number; nom: string; email: string | null; enseignes: string[] }[];
  zones: ZoneRef[];
  villes: { id: number; nom: string; zone_id: number | null; standard: string | null }[];
}

export const formatMad = (m: string | null | undefined) => (m === null || m === undefined ? '—' : `${m} MAD`);

/** Validation de confort (le serveur reste l'autorité) : obligatoire, ≥ 0, 2 décimales max. */
export function erreurMontant(valeur: string): string | null {
  const v = valeur.trim().replace(',', '.');
  if (v === '') return 'Obligatoire (0 accepté)';
  if (!/^\d{1,8}(\.\d{1,2})?$/.test(v)) return Number(v) < 0 ? 'Négatif interdit' : 'Nombre ≥ 0, 2 décimales max';
  return null;
}

export const normaliserMontant = (valeur: string) => valeur.trim().replace(',', '.');

function csrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/** Appel JSON same-origin (lecture de grille, référentiel, aperçu). */
export async function jsonFetch<T>(url: string, body?: unknown): Promise<T> {
  const res = await fetch(url, {
    method: body === undefined ? 'GET' : 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (!res.ok) {
    let message = res.status === 403 ? 'Action non autorisée.' : `Erreur serveur (${res.status}).`;
    try {
      const data = await res.json();
      if (data?.errors) message = Object.values(data.errors as Record<string, string[]>).flat()[0] ?? message;
      else if (data?.message && res.status !== 500) message = data.message;
    } catch {
      /* corps non JSON */
    }
    throw new Error(message);
  }

  return res.json() as Promise<T>;
}
