import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Package, Loader2 } from 'lucide-react';

interface BusinessOption {
  id: number;
  user_id: number;
  name: string;
}

interface ClientOption {
  id: number;
  nom_complet: string;
  telephone?: string;
  businesses?: BusinessOption[];
}

interface VilleOption {
  id: number;
  name_fr: string;
  code_zone?: string;
}

interface LivreurOption {
  id: number;
  nom_complet: string;
  telephone?: string;
}

interface ColisItem {
  id?: number;
  code?: string;
  client_id?: number;
  business_id?: number;
  produit?: string;
  quantite?: number;
  destinataire_nom?: string;
  destinataire_telephone?: string;
  destinataire_adresse?: string;
  destinataire_ville_id?: number;
  prix_brut?: number | string;
  frais_livraison_client?: number | string;
  livreur_id?: number;
  ouverture_autorisee?: boolean;
  echange?: boolean;
  note?: string;
}

interface ColisFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  colis?: ColisItem | null;
  clients: ClientOption[];
  villes: VilleOption[];
  livreurs: LivreurOption[];
}

export function ColisFormDialog({
  open,
  onOpenChange,
  colis,
  clients,
  villes,
  livreurs,
}: ColisFormDialogProps) {
  const isEditing = !!colis?.id;

  const defaultClientId = clients[0]?.id?.toString() ?? '';
  const initialClient = clients.find((c) => c.id.toString() === defaultClientId);
  const defaultBusinessId = initialClient?.businesses?.[0]?.id?.toString() ?? '';

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    client_id: colis?.client_id?.toString() || defaultClientId,
    business_id: colis?.business_id?.toString() || defaultBusinessId,
    produit: colis?.produit || '',
    quantite: colis?.quantite?.toString() || '1',
    destinataire_nom: colis?.destinataire_nom || '',
    destinataire_telephone: colis?.destinataire_telephone || '',
    destinataire_adresse: colis?.destinataire_adresse || '',
    destinataire_ville_id: colis?.destinataire_ville_id?.toString() || (villes[0]?.id?.toString() ?? ''),
    prix_brut: colis?.prix_brut?.toString() || '',
    frais_livraison_client: colis?.frais_livraison_client?.toString() || '35.00',
    livreur_id: colis?.livreur_id?.toString() || '',
    ouverture_autorisee: colis?.ouverture_autorisee ?? false,
    echange: colis?.echange ?? false,
    note: colis?.note || '',
  });

  const selectedClient = clients.find((c) => c.id.toString() === data.client_id);
  const availableBusinesses = selectedClient?.businesses || [];

  useEffect(() => {
    if (colis) {
      setData({
        client_id: colis.client_id?.toString() || defaultClientId,
        business_id: colis.business_id?.toString() || defaultBusinessId,
        produit: colis.produit || '',
        quantite: colis.quantite?.toString() || '1',
        destinataire_nom: colis.destinataire_nom || '',
        destinataire_telephone: colis.destinataire_telephone || '',
        destinataire_adresse: colis.destinataire_adresse || '',
        destinataire_ville_id: colis.destinataire_ville_id?.toString() || (villes[0]?.id?.toString() ?? ''),
        prix_brut: colis.prix_brut?.toString() || '',
        frais_livraison_client: colis.frais_livraison_client?.toString() || '35.00',
        livreur_id: colis.livreur_id?.toString() || '',
        ouverture_autorisee: colis.ouverture_autorisee ?? false,
        echange: colis.echange ?? false,
        note: colis.note || '',
      });
    } else {
      reset();
      if (clients.length > 0) setData('client_id', clients[0].id.toString());
      if (villes.length > 0) setData('destinataire_ville_id', villes[0].id.toString());
    }
    clearErrors();
  }, [colis, open]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing) {
      put(`/colis/${colis.id}`, {
        onSuccess: () => onOpenChange(false),
      });
    } else {
      post('/colis', {
        onSuccess: () => onOpenChange(false),
      });
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl p-6 bg-card text-card-foreground border border-border rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
        <DialogHeader className="gap-2">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Package className="h-5 w-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                {isEditing ? `Modifier le colis #${colis.code}` : "Nouvelle Expédition Express"}
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground">
                {isEditing
                  ? "Ajustez les informations de livraison, produit ou montant CRBT."
                  : "Création d'un colis avec génération automatique du code AWB."}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-2">
          {/* Section Expéditeur */}
          <div className="rounded-md border border-border/80 bg-muted/20 p-3 space-y-3">
            <span className="text-xs font-bold text-foreground flex items-center gap-1.5">
              1. Expéditeur & Boutique Marchande
            </span>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label htmlFor="client_id">Client Marchand *</Label>
                <select
                  id="client_id"
                  value={data.client_id}
                  disabled={isEditing}
                  onChange={(e) => {
                    const cId = e.target.value;
                    const c = clients.find((cl) => cl.id.toString() === cId);
                    setData((prev) => ({
                      ...prev,
                      client_id: cId,
                      business_id: c?.businesses?.[0]?.id?.toString() || '',
                    }));
                  }}
                  className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
                  required
                >
                  {clients.map((c) => (
                    <option key={c.id} value={c.id} className="dark:bg-slate-900">
                      {c.nom_complet}
                    </option>
                  ))}
                </select>
                {errors.client_id && (
                  <p className="text-[11px] text-destructive font-medium">{errors.client_id}</p>
                )}
              </div>

              <div className="space-y-1">
                <Label htmlFor="business_id">Enseigne / Marque</Label>
                <select
                  id="business_id"
                  value={data.business_id}
                  disabled={isEditing}
                  onChange={(e) => setData('business_id', e.target.value)}
                  className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
                >
                  <option value="" className="dark:bg-slate-900">Enseigne par défaut</option>
                  {availableBusinesses.map((b) => (
                    <option key={b.id} value={b.id} className="dark:bg-slate-900">
                      {b.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Section Destinataire */}
          <div className="rounded-md border border-border/80 bg-muted/20 p-3 space-y-3">
            <span className="text-xs font-bold text-foreground flex items-center gap-1.5">
              2. Destinataire Final & Desserte
            </span>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label htmlFor="destinataire_nom">Nom du Client Destinataire *</Label>
                <Input
                  id="destinataire_nom"
                  placeholder="Ex: Fatima Zahra Alami"
                  value={data.destinataire_nom}
                  onChange={(e) => setData('destinataire_nom', e.target.value)}
                  required
                />
                {errors.destinataire_nom && (
                  <p className="text-[11px] text-destructive font-medium">{errors.destinataire_nom}</p>
                )}
              </div>

              <div className="space-y-1">
                <Label htmlFor="destinataire_telephone">Téléphone Mobile *</Label>
                <Input
                  id="destinataire_telephone"
                  placeholder="0612345678"
                  value={data.destinataire_telephone}
                  onChange={(e) => setData('destinataire_telephone', e.target.value)}
                  required
                />
                {errors.destinataire_telephone && (
                  <p className="text-[11px] text-destructive font-medium">{errors.destinataire_telephone}</p>
                )}
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label htmlFor="destinataire_ville_id">Ville de Destination *</Label>
                <select
                  id="destinataire_ville_id"
                  value={data.destinataire_ville_id}
                  onChange={(e) => setData('destinataire_ville_id', e.target.value)}
                  className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
                  required
                >
                  {villes.map((v) => (
                    <option key={v.id} value={v.id} className="dark:bg-slate-900">
                      {v.name_fr} ({v.code_zone || 'BAM'})
                    </option>
                  ))}
                </select>
                {errors.destinataire_ville_id && (
                  <p className="text-[11px] text-destructive font-medium">{errors.destinataire_ville_id}</p>
                )}
              </div>

              <div className="space-y-1">
                <Label htmlFor="livreur_id">Livreur Affecté (Optionnel)</Label>
                <select
                  id="livreur_id"
                  value={data.livreur_id}
                  onChange={(e) => setData('livreur_id', e.target.value)}
                  className="flex h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring dark:bg-input/30"
                >
                  <option value="" className="dark:bg-slate-900">Affectation automatique en tournée</option>
                  {livreurs.map((l) => (
                    <option key={l.id} value={l.id} className="dark:bg-slate-900">
                      {l.nom_complet}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="space-y-1">
              <Label htmlFor="destinataire_adresse">Adresse Complète de Livraison</Label>
              <Input
                id="destinataire_adresse"
                placeholder="Quartier, Rue, Résidence, N° Appartement..."
                value={data.destinataire_adresse}
                onChange={(e) => setData('destinataire_adresse', e.target.value)}
              />
            </div>
          </div>

          {/* Section Colis & Montants */}
          <div className="rounded-md border border-border/80 bg-muted/20 p-3 space-y-3">
            <span className="text-xs font-bold text-foreground flex items-center gap-1.5">
              3. Marchandise & Montant CRBT
            </span>
            <div className="grid grid-cols-3 gap-3">
              <div className="space-y-1 col-span-2">
                <Label htmlFor="produit">Désignation du Produit</Label>
                <Input
                  id="produit"
                  placeholder="Ex: Robe Kaftan Soie / Pack Cosmétique"
                  value={data.produit}
                  onChange={(e) => setData('produit', e.target.value)}
                />
              </div>

              <div className="space-y-1">
                <Label htmlFor="quantite">Quantité</Label>
                <Input
                  id="quantite"
                  type="number"
                  min="1"
                  value={data.quantite}
                  onChange={(e) => setData('quantite', e.target.value)}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label htmlFor="prix_brut">Prix à Encaisser CRBT (MAD) *</Label>
                <Input
                  id="prix_brut"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="Ex: 350.00"
                  value={data.prix_brut}
                  onChange={(e) => setData('prix_brut', e.target.value)}
                  required
                  className="font-mono font-bold text-foreground"
                />
                {errors.prix_brut && (
                  <p className="text-[11px] text-destructive font-medium">{errors.prix_brut}</p>
                )}
              </div>

              <div className="space-y-1">
                <Label htmlFor="frais_livraison_client">Frais Livraison Client (MAD)</Label>
                <Input
                  id="frais_livraison_client"
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.frais_livraison_client}
                  onChange={(e) => setData('frais_livraison_client', e.target.value)}
                  className="font-mono"
                />
              </div>
            </div>

            <div className="flex items-center gap-6 pt-1">
              <label className="flex items-center gap-2 cursor-pointer text-xs font-medium text-foreground">
                <input
                  type="checkbox"
                  checked={data.ouverture_autorisee}
                  onChange={(e) => setData('ouverture_autorisee', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                />
                Ouverture autorisée avant paiement
              </label>

              <label className="flex items-center gap-2 cursor-pointer text-xs font-medium text-foreground">
                <input
                  type="checkbox"
                  checked={data.echange}
                  onChange={(e) => setData('echange', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                />
                Colis d'échange
              </label>
            </div>

            <div className="space-y-1 pt-1">
              <Label htmlFor="note">Instructions Spéciales / Note au Livreur</Label>
              <Textarea
                id="note"
                placeholder="Ex: Appeler avant 14h, sonner à l'interphone..."
                value={data.note}
                onChange={(e) => setData('note', e.target.value)}
                rows={2}
              />
            </div>
          </div>

          <DialogFooter className="mt-5 flex items-center justify-end gap-2">
            <DialogClose asChild>
              <Button type="button" variant="outline" size="sm" className="text-xs h-8">
                Annuler
              </Button>
            </DialogClose>
            <Button
              type="submit"
              size="sm"
              disabled={processing}
              className="text-xs h-8 bg-primary text-primary-foreground hover:bg-primary/90"
            >
              {processing && <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />}
              {isEditing ? "Enregistrer les modifications" : "Valider l'expédition"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
