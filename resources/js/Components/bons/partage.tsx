const TONS: Record<string, string> = {
  brouillon: 'border-border bg-muted text-muted-foreground',
  expedie: 'border-sky-600/25 bg-sky-600/10 text-sky-700 dark:text-sky-300',
  valide: 'border-sky-600/25 bg-sky-600/10 text-sky-700 dark:text-sky-300',
  enregistre: 'border-sky-600/25 bg-sky-600/10 text-sky-700 dark:text-sky-300',
  traite: 'border-emerald-600/25 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
};

export function BadgeStatutBon({ statut, libelle }: { statut: string; libelle: string }) {
  return <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold ${TONS[statut] ?? TONS.brouillon}`}>{libelle}</span>;
}

export const montant = (v: number) => `${v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DH`;

export function dateBon(d: string | null) {
  if (!d) return '—';
  const x = new Date(d.replace(' ', 'T'));
  return `${x.toLocaleDateString('fr-FR')} ${x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
}
