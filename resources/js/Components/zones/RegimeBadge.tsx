import { REGIMES, type TypeZone } from './types';

/** Régime logistique d'une zone : vert « Local direct · BL », bleu « National partenaire · BE ». */
export function RegimeBadge({ type, compact = false }: { type: TypeZone; compact?: boolean }) {
  const r = REGIMES[type];

  return (
    <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap ${r.classe}`}>
      {compact ? r.bon : <>{r.label} <span className="opacity-70">· {r.bon}</span></>}
    </span>
  );
}

/** Code de zone en pastille typographique (espacement, gras), comme WinningExpress. */
export function CodeZone({ code }: { code: string }) {
  return (
    <span className="inline-flex h-6 min-w-11 items-center justify-center rounded-md bg-[#004c68] px-2 font-mono text-[11px] font-bold tracking-[0.12em] text-white dark:bg-[#ff8806] dark:text-[#05161f]">
      {code}
    </span>
  );
}
