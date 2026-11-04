import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { X } from 'lucide-react';

/**
 * Sélection par cases à cocher sur la page courante d'un tableau serveur.
 * La sélection est vidée quand les lignes affichées changent (page, onglet, filtre).
 */
export function useSelection(ids: number[]) {
  const [choisis, setChoisis] = useState<Set<number>>(new Set());
  const cle = ids.join(',');
  useEffect(() => setChoisis(new Set()), [cle]);

  return useMemo(() => {
    const tous = ids.length > 0 && ids.every((id) => choisis.has(id));
    return {
      ids: [...choisis],
      taille: choisis.size,
      tous,
      partiel: choisis.size > 0 && !tous,
      estChoisi: (id: number) => choisis.has(id),
      basculer: (id: number) => setChoisis((s) => {
        const n = new Set(s);
        if (n.has(id)) n.delete(id); else n.add(id);
        return n;
      }),
      basculerTous: () => setChoisis(tous ? new Set() : new Set(ids)),
      vider: () => setChoisis(new Set()),
    };
  }, [choisis, cle]); // eslint-disable-line react-hooks/exhaustive-deps
}

/** Case native (accessible, indéterminée si sélection partielle). */
export function CaseACocher({ coche, partiel = false, onChange, label }: { coche: boolean; partiel?: boolean; onChange: () => void; label: string }) {
  return (
    <input
      type="checkbox"
      aria-label={label}
      checked={coche}
      ref={(el) => { if (el) el.indeterminate = partiel; }}
      onChange={onChange}
      onClick={(e) => e.stopPropagation()}
      className="size-4 cursor-pointer rounded border-input accent-[#004c68] dark:accent-[#ff8806]"
    />
  );
}

/** Barre d'actions groupées, affichée tant qu'au moins une ligne est cochée. */
export function BarreSelection({ taille, onVider, children }: { taille: number; onVider: () => void; children: ReactNode }) {
  if (taille === 0) return null;
  return (
    <div className="sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-lg border border-[#004c68]/30 bg-[#004c68] px-3 py-2 text-xs text-white shadow-lg dark:border-[#ff8806]/40 dark:bg-[#0a2533]">
      <span className="font-semibold">{taille} sélectionné(s)</span>
      <span className="mx-1 h-4 w-px bg-white/30" />
      {children}
      <button type="button" onClick={onVider} className="ml-auto inline-flex items-center gap-1 rounded px-2 py-1 text-white/80 hover:bg-white/10 hover:text-white">
        <X className="size-3.5" /> Désélectionner
      </button>
    </div>
  );
}

/** Bouton de la barre d'actions groupées. */
export function ActionGroupee({ onClick, icone, children, danger = false, disabled = false }: { onClick: () => void; icone: ReactNode; children: ReactNode; danger?: boolean; disabled?: boolean }) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium disabled:opacity-50 ${danger ? 'bg-destructive/90 hover:bg-destructive' : 'bg-white/15 hover:bg-white/25'}`}
    >
      {icone} {children}
    </button>
  );
}
