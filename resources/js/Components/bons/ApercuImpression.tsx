import { useRef, useState } from 'react';
import { ExternalLink, FileDown, Loader2, Printer } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

/**
 * Aperçu d'un bon imprimable dans une modale (page A4 servie par le serveur, dans un iframe).
 * « Imprimer » lance l'impression du document seul ; « Enregistrer en PDF » ouvre la même boîte
 * d'impression, où l'on choisit « Enregistrer au format PDF ».
 */
export function ApercuImpression({ url, titre, onClose, format = 'A4', taille = 'a4' }: { url: string | null; titre: string; onClose: () => void; format?: string; taille?: 'a4' | 'etiquette' }) {
  const cadre = useRef<HTMLIFrameElement>(null);
  const [charge, setCharge] = useState(false);

  const imprimer = () => cadre.current?.contentWindow?.print();

  return (
    <Dialog open={url !== null} onOpenChange={(o) => { if (!o) { setCharge(false); onClose(); } }}>
      <DialogContent className={`flex max-w-none flex-col gap-0 p-0 sm:max-w-none ${taille === 'etiquette' ? 'h-[min(640px,92vh)] w-[min(560px,96vw)]' : 'h-[92vh] w-[min(1040px,96vw)]'}`}>
        <DialogHeader className="flex-row items-center justify-between gap-3 border-b border-border px-4 py-3">
          <div className="min-w-0">
            <DialogTitle className="truncate text-sm">{titre}</DialogTitle>
            <DialogDescription className="text-[11px]">Aperçu avant impression ({format})</DialogDescription>
          </div>
          <div className="flex shrink-0 flex-wrap justify-end gap-2 pr-8">
            <Button size="sm" variant="outline" className="h-8 text-xs" disabled={!url} render={<a href={url ?? '#'} target="_blank" rel="noopener" />}>
              <ExternalLink className="size-3.5" /> Nouvel onglet
            </Button>
            <Button size="sm" variant="outline" className="h-8 text-xs" disabled={!charge} onClick={imprimer} title="Dans la boîte d’impression, choisir « Enregistrer au format PDF »">
              <FileDown className="size-3.5" /> Enregistrer en PDF
            </Button>
            <Button size="sm" className="h-8 bg-[#004c68] text-xs text-white hover:bg-[#00384d]" disabled={!charge} onClick={imprimer}>
              <Printer className="size-3.5" /> Imprimer
            </Button>
          </div>
        </DialogHeader>
        <div className="relative flex-1 bg-muted/40">
          {!charge && (
            <div className="absolute inset-0 flex items-center justify-center gap-2 text-xs text-muted-foreground">
              <Loader2 className="size-4 animate-spin" /> Préparation du bon…
            </div>
          )}
          {url && <iframe ref={cadre} src={url} title={titre} onLoad={() => setCharge(true)} className="size-full border-0" />}
        </div>
      </DialogContent>
    </Dialog>
  );
}
