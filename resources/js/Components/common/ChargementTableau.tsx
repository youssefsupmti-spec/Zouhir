import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';

/**
 * Vrai pendant qu'une visite Inertia vers la page courante est en cours (filtre, tri, onglet,
 * pagination, recherche, action groupée). Un court délai évite le clignotement des réponses rapides.
 */
export function useChargementPage(delaiMs = 120): boolean {
  const [enCours, setEnCours] = useState(false);

  useEffect(() => {
    let minuterie: ReturnType<typeof setTimeout> | undefined;
    const debut = router.on('start', (e) => {
      const visite = e.detail.visit;
      const cible = visite.url instanceof URL ? visite.url.pathname : String(visite.url);
      if (cible === window.location.pathname) {
        minuterie = setTimeout(() => setEnCours(true), delaiMs);
      }
    });
    const fin = router.on('finish', () => {
      clearTimeout(minuterie);
      setEnCours(false);
    });
    return () => { clearTimeout(minuterie); debut(); fin(); };
  }, [delaiMs]);

  return enCours;
}

/** Voile posé sur un tableau (parent en `relative`) pendant le chargement des données. */
export function ChargementTableau({ actif, libelle = 'Chargement des données…' }: { actif: boolean; libelle?: string }) {
  if (!actif) return null;
  return (
    <div role="status" aria-live="polite" className="absolute inset-0 z-10 flex items-start justify-center bg-background/60 pt-24 backdrop-blur-[1px]">
      <span className="inline-flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-xs font-medium text-foreground">
        <Loader2 className="size-4 animate-spin text-[#004c68] dark:text-[#ff8806]" /> {libelle}
      </span>
    </div>
  );
}
