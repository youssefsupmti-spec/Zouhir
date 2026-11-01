import { AppliquerTarifDialog } from '@/components/tarifs/AppliquerTarifDialog';

interface Props {
  open: boolean;
  livreurId: number | null;
  zoneId: number | null;
  onClose: () => void;
}

/**
 * Affectation de commissions SPÉCIFIQUES à un livreur sur une ou plusieurs villes (ou une zone).
 * Pas de barème standard des commissions (comme WinningExpress) : seule la commission du livreur compte.
 */
export function AjouterTarifLivreurDialog({ open, livreurId, zoneId, onClose }: Props) {
  return (
    <AppliquerTarifDialog
      open={open}
      onClose={onClose}
      zoneInitiale={zoneId}
      livreurInitial={livreurId}
      avecLivreur
      avecRestauration
      titre="Affecter des villes au livreur"
      description="Commission versée à CE livreur pour ces villes. Sans commission, la commission d'un colis est « manquante ». Une commission existante n'est modifiée que sur choix explicite."
      referentielUrl="/tarifs-livreurs/referentiel"
      apercuUrl="/tarifs-livreurs/apercu"
      storeUrl="/tarifs-livreurs"
      champs={[
        { cle: 'livraison', label: 'Commission livraison' },
        { cle: 'refus', label: 'Commission refus', defaut: '0' },
      ]}
    />
  );
}
