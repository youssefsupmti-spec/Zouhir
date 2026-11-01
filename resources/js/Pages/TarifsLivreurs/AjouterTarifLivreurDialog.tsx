import { AppliquerTarifDialog } from '@/components/tarifs/AppliquerTarifDialog';

interface Props {
  open: boolean;
  /** `specifique` : commission propre à un livreur ; `standard` : barème standard livreur. */
  type: 'specifique' | 'standard';
  livreurId: number | null;
  zoneId: number | null;
  onClose: () => void;
}

/** Affectation de commissions livreurs à une ou plusieurs villes (ou à une zone). */
export function AjouterTarifLivreurDialog({ open, type, livreurId, zoneId, onClose }: Props) {
  const specifique = type === 'specifique';

  return (
    <AppliquerTarifDialog
      open={open}
      onClose={onClose}
      zoneInitiale={zoneId}
      livreurInitial={livreurId}
      avecLivreur={specifique}
      avecRestauration={specifique}
      titre={specifique ? 'Affecter des villes au livreur' : 'Appliquer le barème standard livreur'}
      description={specifique
        ? 'Commission versée à CE livreur pour ces villes ; elle remplace le barème standard livreur. Une commission existante n\'est modifiée que sur choix explicite.'
        : 'Commission par défaut versée à tout livreur sans commission spécifique. Distincte du tarif de livraison facturé au client.'}
      referentielUrl="/tarifs-livreurs/referentiel"
      apercuUrl={specifique ? '/tarifs-livreurs/apercu' : '/tarifs-livreurs/standard/apercu'}
      storeUrl={specifique ? '/tarifs-livreurs' : '/tarifs-livreurs/standard'}
      libelleActuel="Standard livreur"
      champs={[
        { cle: 'livraison', label: 'Commission livraison' },
        { cle: 'refus', label: 'Commission refus', defaut: '0' },
      ]}
    />
  );
}
