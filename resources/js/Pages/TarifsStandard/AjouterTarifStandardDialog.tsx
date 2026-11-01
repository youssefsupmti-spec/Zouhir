import { AppliquerTarifDialog } from '@/components/tarifs/AppliquerTarifDialog';

interface Props {
  open: boolean;
  zoneId: number | null;
  onClose: () => void;
}

/** Ajout en masse / application à une zone du tarif STANDARD de livraison (facturé au marchand). */
export function AjouterTarifStandardDialog({ open, zoneId, onClose }: Props) {
  return (
    <AppliquerTarifDialog
      open={open}
      onClose={onClose}
      zoneInitiale={zoneId}
      titre={zoneId ? 'Appliquer un tarif standard à la zone' : 'Ajouter des tarifs standards'}
      description="Frais facturés au client marchand quand aucun tarif négocié n'existe. Les villes déjà tarifées ne sont modifiées que sur choix explicite."
      referentielUrl="/tarifs-standard/referentiel"
      apercuUrl="/tarifs-standard/apercu"
      storeUrl="/tarifs-standard"
      libelleActuel="Standard"
      avecDelai
      champs={[
        { cle: 'livraison', label: 'Livraison' },
        { cle: 'refus', label: 'Refus', defaut: '0' },
        { cle: 'retour', label: 'Retour', defaut: '0' },
      ]}
    />
  );
}
