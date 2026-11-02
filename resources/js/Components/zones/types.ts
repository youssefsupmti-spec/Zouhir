export type TypeZone = 'locale' | 'nationale';

export interface VilleApercu {
  id: number;
  nom: string;
  nom_ar: string | null;
}

export interface ZoneLigne {
  id: number;
  name: string;
  code: string;
  type: TypeZone;
  description: string | null;
  villes_count: number;
  apercu: VilleApercu[];
  deleted_at: string | null;
}

export interface VillePanneau extends VilleApercu {
  zone_code: string | null;
  zone_nom: string | null;
}

export interface Paginee<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export const REGIMES: Record<TypeZone, { label: string; bon: string; classe: string }> = {
  locale: {
    label: 'Local direct',
    bon: 'BL',
    classe: 'border-emerald-600/25 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
  },
  nationale: {
    label: 'National partenaire',
    bon: 'BE',
    classe: 'border-[#004c68]/25 bg-[#004c68]/10 text-[#004c68] dark:border-sky-400/25 dark:bg-sky-400/10 dark:text-sky-300',
  },
};

/** Libellé bilingue d'une ville : « Temara (تمارة) ». */
export const nomVille = (v: VilleApercu) => (v.nom_ar ? `${v.nom} (${v.nom_ar})` : v.nom);

function csrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/** Appel JSON same-origin (lecture des panneaux, rattachement / retrait de villes). */
export async function zoneJson<T>(url: string, method: 'GET' | 'POST' | 'DELETE' = 'GET', body?: unknown): Promise<T> {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(method === 'GET' ? {} : { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }),
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
