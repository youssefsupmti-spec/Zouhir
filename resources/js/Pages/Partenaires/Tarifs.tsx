import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Lock, Network, Save } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';

interface Cellule { tarif: number | null; delai: number | null; exclusif: boolean }
interface Edit { desservie: boolean; tarif: string; delai: string; exclusif: boolean }

interface Props {
  zones: { id: number; code: string; name: string; villes_count: number }[];
  partenaires: { id: number; code: string; name: string; commission_defaut: string | null; delai_transit_moyen_h: number | null }[];
  cellules: Record<string, Cellule>;
  can: { manage: boolean };
}

const cle = (p: number, z: number) => `${p}-${z}`;

export default function PartenairesTarifs({ zones, partenaires, cellules, can }: Props) {
  const initial = useMemo(() => {
    const m: Record<string, Edit> = {};
    for (const p of partenaires) for (const z of zones) {
      const c = cellules[cle(p.id, z.id)];
      m[cle(p.id, z.id)] = { desservie: Boolean(c), tarif: c?.tarif != null ? String(c.tarif) : '', delai: c?.delai != null ? String(c.delai) : '', exclusif: c?.exclusif ?? false };
    }
    return m;
  }, [zones, partenaires, cellules]);

  const [grille, setGrille] = useState(initial);
  const [envoi, setEnvoi] = useState(false);
  const modifiees = Object.keys(grille).filter((k) => JSON.stringify(grille[k]) !== JSON.stringify(initial[k]));

  const changer = (k: string, patch: Partial<Edit>) => setGrille((g) => {
    const suivant = { ...g[k], ...patch };
    // Saisir un tarif ou un délai rend la zone desservie.
    if ((patch.tarif || patch.delai) && !suivant.desservie) suivant.desservie = true;
    return { ...g, [k]: suivant };
  });

  const enregistrer = () => {
    setEnvoi(true);
    router.put('/partenaires/tarifs', {
      cellules: modifiees.map((k) => {
        const [p, z] = k.split('-').map(Number);
        const c = grille[k];
        return { delivery_partner_id: p, zone_id: z, desservie: c.desservie, tarif: c.tarif === '' ? null : Number(c.tarif), delai: c.delai === '' ? null : Number(c.delai), exclusif: c.exclusif };
      }),
    }, { preserveScroll: true, onFinish: () => setEnvoi(false) });
  };

  return (
    <AuthenticatedLayout title="Tarifs transit réseau">
      <Head title="Tarifs transit réseau - OUAISS EXPRESS" />
      <div className="space-y-6">
        <PageHeader
          title="Tarifs transit réseau"
          description="Prix payé à chaque transporteur partenaire par colis et par zone nationale, avec le délai garanti. Une case cochée = zone desservie."
          icon={Network}
          actions={can.manage ? (
            <Button disabled={modifiees.length === 0 || envoi} onClick={enregistrer} className="cursor-pointer bg-[#004c68] text-xs font-semibold text-white hover:bg-[#00384d]">
              <Save className="mr-1.5 size-3.5" /> Enregistrer{modifiees.length > 0 ? ` (${modifiees.length})` : ''}
            </Button>
          ) : undefined}
        />

        {partenaires.length === 0 || zones.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border bg-card p-12 text-center text-sm text-muted-foreground">
            {partenaires.length === 0 ? 'Aucun partenaire actif : créez-en un dans « Transporteurs partenaires ».' : 'Aucune zone nationale définie.'}
          </div>
        ) : (
          <div className="overflow-x-auto rounded-md border border-border bg-card shadow-2xs">
            <table className="w-full min-w-[720px] border-collapse text-xs">
              <thead>
                <tr className="border-b border-border bg-muted/40">
                  <th className="sticky left-0 z-10 bg-muted/40 px-3 py-2 text-left font-semibold">Zone nationale</th>
                  {partenaires.map((p) => (
                    <th key={p.id} className="min-w-44 border-l border-border px-3 py-2 text-left">
                      <span className="font-semibold">{p.name}</span>
                      <span className="block font-mono text-[10px] font-normal text-muted-foreground">
                        {p.code}{p.commission_defaut ? ` · défaut ${Number(p.commission_defaut)} DH` : ''}
                      </span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {zones.map((z) => (
                  <tr key={z.id} className="border-b border-border last:border-0">
                    <td className="sticky left-0 z-10 bg-card px-3 py-2">
                      <span className="font-mono text-[10px] text-muted-foreground">{z.code}</span>
                      <span className="block font-semibold">{z.name}</span>
                      <span className="text-[10px] text-muted-foreground">{z.villes_count} ville(s)</span>
                    </td>
                    {partenaires.map((p) => {
                      const k = cle(p.id, z.id);
                      const c = grille[k];
                      const change = modifiees.includes(k);
                      return (
                        <td key={k} className={`border-l border-border px-2 py-1.5 align-top ${c.desservie ? 'bg-[#f78304]/[0.04]' : ''} ${change ? 'ring-1 ring-inset ring-[#f78304]' : ''}`}>
                          <label className="mb-1 flex cursor-pointer items-center gap-1.5 text-[11px]">
                            <input type="checkbox" className="accent-[#f78304]" disabled={!can.manage} checked={c.desservie} onChange={(e) => changer(k, { desservie: e.target.checked })} />
                            Desservie
                            <button type="button" disabled={!can.manage || !c.desservie} title="Exclusif sur cette zone"
                              onClick={() => changer(k, { exclusif: !c.exclusif })}
                              className={`ml-auto cursor-pointer rounded p-0.5 disabled:cursor-not-allowed ${c.exclusif ? 'text-[#c26200]' : 'text-muted-foreground/40'}`}>
                              <Lock className="size-3" />
                            </button>
                          </label>
                          {c.desservie && (
                            <div className="flex gap-1">
                              <input aria-label="Tarif (DH)" type="number" min={0} step="0.01" placeholder="DH" disabled={!can.manage} value={c.tarif}
                                onChange={(e) => changer(k, { tarif: e.target.value })} className="h-7 w-20 rounded border border-input bg-transparent px-1.5 tabular-nums" />
                              <input aria-label="Délai (h)" type="number" min={1} placeholder="h" disabled={!can.manage} value={c.delai}
                                onChange={(e) => changer(k, { delai: e.target.value })} className="h-7 w-14 rounded border border-input bg-transparent px-1.5 tabular-nums" />
                            </div>
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="text-[11px] text-muted-foreground">Sans tarif saisi, l’estimation de l’API utilise le tarif par défaut du partenaire. Le cadenas marque un partenaire exclusif sur la zone.</p>
      </div>
    </AuthenticatedLayout>
  );
}
