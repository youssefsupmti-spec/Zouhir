import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { REGIMES, type TypeZone } from './types';

export interface ZoneFormData {
  name: string;
  code: string;
  type: TypeZone;
  description: string;
}

interface Props {
  data: ZoneFormData;
  errors: Partial<Record<keyof ZoneFormData, string>>;
  onChange: <K extends keyof ZoneFormData>(champ: K, valeur: ZoneFormData[K]) => void;
}

/** Champs d'une zone (création en dialog, édition en page) : nom, code, régime, description. */
export function ZoneFormFields({ data, errors, onChange }: Props) {
  const erreur = (m?: string) => m && <p className="text-[11px] font-medium text-destructive">{m}</p>;

  return (
    <div className="grid gap-3.5 md:grid-cols-2">
      <div className="space-y-1.5">
        <Label htmlFor="zone-name">Nom de la zone *</Label>
        <Input id="zone-name" value={data.name} maxLength={255} placeholder="Ex. Zone Témara, Zone Grand Casablanca…"
          onChange={(e) => onChange('name', e.target.value)} aria-invalid={Boolean(errors.name)} required />
        {erreur(errors.name)}
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="zone-code">Code *</Label>
        <Input id="zone-code" value={data.code} maxLength={10} placeholder="Ex. TM, CASA"
          className="font-mono font-bold tracking-[0.15em] uppercase"
          onChange={(e) => onChange('code', e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ''))} aria-invalid={Boolean(errors.code)} required />
        {erreur(errors.code)}
      </div>
      <div className="space-y-1.5 md:col-span-2">
        <Label>Régime logistique *</Label>
        <div role="radiogroup" aria-label="Régime logistique" className="grid gap-2 sm:grid-cols-2">
          {(Object.keys(REGIMES) as TypeZone[]).map((t) => (
            <button key={t} type="button" role="radio" aria-checked={data.type === t} onClick={() => onChange('type', t)}
              className={`rounded-md border px-3 py-2 text-left text-xs transition-colors ${data.type === t
                ? 'border-[#004c68] bg-[#004c68]/5 ring-1 ring-[#004c68] dark:border-[#ff8806] dark:bg-[#ff8806]/10 dark:ring-[#ff8806]'
                : 'border-border hover:bg-muted/50'}`}>
              <span className="block font-semibold">{REGIMES[t].label}</span>
              <span className="text-[11px] text-muted-foreground">
                {t === 'locale' ? 'Flotte propre de l’Agence Témara — Bons de Livraison (BL)' : 'Réseau partenaire — Bons d’Envoi (BE)'}
              </span>
            </button>
          ))}
        </div>
        {erreur(errors.type)}
      </div>
      <div className="space-y-1.5 md:col-span-2">
        <Label htmlFor="zone-description">Description</Label>
        <Input id="zone-description" value={data.description} maxLength={500} placeholder="Ex. Témara centre, Massira, Wifaq…"
          onChange={(e) => onChange('description', e.target.value)} />
        {erreur(errors.description)}
      </div>
    </div>
  );
}
