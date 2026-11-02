import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Loader2, Map as MapIcon } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { CodeZone, RegimeBadge } from '@/components/zones/RegimeBadge';
import { PanneauVilles } from '@/components/zones/PanneauVilles';
import { ZoneFormFields, type ZoneFormData } from '@/components/zones/ZoneFormFields';
import type { TypeZone } from '@/components/zones/types';

interface Props {
  zone: { id: number; name: string; code: string; type: TypeZone; description: string | null };
  stats: { villes: number; sans_zone: number };
}

/**
 * Page de modification d'une zone (WinningExpress ZoneForm) : informations en haut, puis
 * deux panneaux synchronisés « Villes disponibles » ↔ « Villes de la zone ».
 */
export default function ZoneEdit({ zone, stats }: Props) {
  const initial: ZoneFormData = { name: zone.name, code: zone.code, type: zone.type, description: zone.description ?? '' };
  const form = useForm<ZoneFormData>(initial);
  const [version, setVersion] = useState(0);
  const [message, setMessage] = useState<{ ton: 'ok' | 'erreur'; texte: string } | null>(null);
  const modifie = (Object.keys(initial) as (keyof ZoneFormData)[]).some((k) => form.data[k] !== initial[k]);

  const transfert = (texte: string) => { setMessage({ ton: 'ok', texte }); setVersion((v) => v + 1); };
  const erreur = (texte: string) => setMessage({ ton: 'erreur', texte });

  return (
    <AuthenticatedLayout title={`Zone ${zone.code}`}>
      <Head title={`Zone ${zone.code} - OUAISS EXPRESS`} />

      <div className="space-y-6">
        <div className="flex flex-wrap items-center gap-3">
          <Button size="sm" variant="ghost" className="h-8 rounded-md text-xs" render={<Link href="/zones" />}>
            <ArrowLeft className="size-3.5" /> Zones
          </Button>
          <div className="flex items-center gap-2.5">
            <div className="flex size-9 items-center justify-center rounded-md bg-[#004c68]/10 text-[#004c68] dark:bg-[#004c68]/20 dark:text-[#ff8806]">
              <MapIcon className="size-5" />
            </div>
            <h1 className="text-lg font-bold tracking-tight">{zone.name}</h1>
            <CodeZone code={zone.code} />
            <RegimeBadge type={zone.type} />
          </div>
          <span className="ml-auto text-[11px] text-muted-foreground">
            {stats.sans_zone.toLocaleString('fr-FR')} ville(s) sans zone dans le référentiel
          </span>
        </div>

        <form
          onSubmit={(e) => { e.preventDefault(); form.put(`/zones/${zone.id}`, { preserveScroll: true }); }}
          className="rounded-md border border-border bg-card p-4 shadow-2xs"
        >
          <div className="mb-3">
            <h2 className="text-sm font-semibold">Informations de la zone</h2>
            <p className="text-[11px] text-muted-foreground">Changer le code met à jour l’étiquette de toutes ses villes ; les colis existants gardent leur code figé.</p>
          </div>
          <ZoneFormFields data={form.data} errors={form.errors} onChange={(c, v) => form.setData((d) => ({ ...d, [c]: v }))} />
          <div className="mt-4 flex justify-end gap-2">
            <Button type="button" variant="outline" size="sm" disabled={!modifie || form.processing} onClick={() => { form.setData(initial); form.clearErrors(); }}>
              Annuler
            </Button>
            <Button type="submit" size="sm" className="bg-[#004c68] text-white hover:bg-[#00384d]" disabled={!modifie || form.processing}>
              {form.processing ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />} Enregistrer les modifications
            </Button>
          </div>
        </form>

        {message && (
          <div role={message.ton === 'erreur' ? 'alert' : 'status'}
            className={`rounded-md border px-4 py-2 text-xs ${message.ton === 'erreur'
              ? 'border-destructive/30 bg-destructive/5 text-destructive'
              : 'border-emerald-600/30 bg-emerald-600/5 text-emerald-700 dark:text-emerald-400'}`}>
            {message.texte}
          </div>
        )}

        <div className="grid gap-4 xl:grid-cols-2">
          <PanneauVilles zoneId={zone.id} zoneCode={zone.code} panneau="disponibles" version={version} onTransfert={transfert} onErreur={erreur} />
          <PanneauVilles zoneId={zone.id} zoneCode={zone.code} panneau="zone" version={version} onTransfert={transfert} onErreur={erreur} />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
