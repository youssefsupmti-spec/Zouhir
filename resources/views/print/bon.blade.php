@php
    use Illuminate\Support\Facades\Vite;
    $arabe = static fn ($s): bool => (bool) preg_match('/\p{Arabic}/u', (string) $s);
    $dh = static fn ($v): string => number_format((float) $v, 2, ',', ' ');
    $date = $bon->legacy_dateadd_at ?? $bon->created_at;
    $police = static function (string $src): ?string {
        try { return Vite::asset($src); } catch (\Throwable) { return null; }
    };
    $inter = $police('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2');
    $kufi = $police('resources/fonts/droid-arabic-kufi-regular.woff');
    $kufiGras = $police('resources/fonts/droid-arabic-kufi-bold.woff');
    $absents = $lignes->where('absent', true)->count();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $titre }} {{ $bon->code }} — OUAISS EXPRESS</title>
<style>
    @if($inter)@font-face { font-family: 'Inter Variable'; src: url('{{ $inter }}') format('woff2'); font-weight: 100 900; }@endif
    @if($kufi)@font-face { font-family: 'Droid Arabic Kufi'; src: url('{{ $kufi }}') format('woff'); font-weight: 400; }@endif
    @if($kufiGras)@font-face { font-family: 'Droid Arabic Kufi'; src: url('{{ $kufiGras }}') format('woff'); font-weight: 700; }@endif

    @page { size: A4; margin: 12mm 11mm 14mm; }
    :root { --bleu: #004c68; --orange: #ff8806; --encre: #0f172a; --gris: #64748b; --filet: #d7dee5; --fond: #f4f7f9; }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: #e9eef2; color: var(--encre); font: 11px/1.4 'Inter Variable', 'Droid Arabic Kufi', Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .feuille { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 11mm 14mm; background: #fff; }
    .ar { font-family: 'Droid Arabic Kufi', 'Inter Variable', sans-serif; direction: rtl; text-align: right; }

    /* En-tête : bandeau de marque + titre du document */
    .entete { display: flex; justify-content: space-between; align-items: stretch; border-bottom: 3px solid var(--bleu); padding-bottom: 10px; }
    .marque img { height: 46px; display: block; }
    .marque .agence { margin-top: 6px; color: var(--gris); font-size: 10px; line-height: 1.5; }
    .doc { text-align: right; display: flex; flex-direction: column; justify-content: space-between; }
    .doc .type { font-size: 9px; letter-spacing: .14em; text-transform: uppercase; color: var(--orange); font-weight: 700; }
    .doc h1 { margin: 2px 0 0; font-size: 20px; letter-spacing: -.01em; color: var(--bleu); }
    .doc .statut { align-self: flex-end; margin-top: 6px; border: 1px solid var(--bleu); color: var(--bleu); border-radius: 3px; padding: 1px 7px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }

    /* Bandeau d'informations */
    .infos { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0; margin-top: 12px; border: 1px solid var(--filet); border-radius: 4px; }
    .infos > div { padding: 8px 10px; border-right: 1px solid var(--filet); }
    .infos > div:last-child { border-right: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .k { display: block; font-size: 8.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--gris); }
    .v { display: block; margin-top: 2px; font-weight: 700; font-size: 12px; }
    .v.code { font-family: ui-monospace, Menlo, Consolas, monospace; color: var(--bleu); font-size: 13px; }
    .sous { color: var(--gris); font-size: 10px; font-weight: 400; }
    .barres svg { display: block; height: 40px; width: auto; }
    .barres .lib { margin-top: 2px; font: 9px ui-monospace, Menlo, Consolas, monospace; letter-spacing: .12em; }

    /* Tableau des colis */
    table.colis { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .colis thead th { background: var(--bleu); color: #fff; font-size: 8.5px; text-transform: uppercase; letter-spacing: .07em; text-align: left; padding: 6px 6px; }
    .colis thead th.r, .colis td.r { text-align: right; }
    .colis td { padding: 5px 6px; border-bottom: 1px solid var(--filet); vertical-align: top; }
    .colis tbody tr:nth-child(even) td { background: var(--fond); }
    .colis td.n { color: var(--gris); width: 22px; }
    .colis td.code { font: 700 10.5px ui-monospace, Menlo, Consolas, monospace; color: var(--bleu); white-space: nowrap; }
    .colis td.tel { white-space: nowrap; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 10px; }
    .colis td.case { width: 46px; text-align: center; }
    .colis td.case span { display: inline-block; width: 12px; height: 12px; border: 1px solid var(--gris); border-radius: 2px; }
    .colis tr.absent td { color: #b45309; font-style: italic; }
    .colis tfoot td { padding: 8px 6px; font-weight: 700; border-top: 2px solid var(--bleu); }
    .colis tfoot .total { font-size: 13px; color: var(--bleu); }

    /* Pied : contrôle et signatures */
    .controle { display: flex; gap: 18px; margin-top: 10px; font-size: 10px; color: var(--gris); }
    .controle b { color: var(--encre); }
    .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 18px; page-break-inside: avoid; }
    .signatures div { border: 1px dashed var(--filet); border-radius: 4px; height: 78px; padding: 7px 9px; }
    .pied { margin-top: 14px; display: flex; justify-content: space-between; font-size: 8.5px; color: var(--gris); border-top: 1px solid var(--filet); padding-top: 6px; }

    @media print {
        html, body { background: #fff; }
        .feuille { margin: 0; padding: 0; width: auto; min-height: 0; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }
</style>
</head>
<body>
<main class="feuille">
    <header class="entete">
        <div class="marque">
            <img src="{{ asset('images/'.rawurlencode('Logo de livraison OUAISS EXPRESS.png')) }}" alt="OUAISS EXPRESS">
            <div class="agence">
                {{ $agence?->nom ?? 'OUAISS EXPRESS' }}@if($agence?->adresse) · {{ $agence->adresse }}@endif<br>
                @if($agence?->telephone)Tél. {{ $agence->telephone }} · @endif ouaissexpress.com
            </div>
        </div>
        <div class="doc">
            <div>
                <div class="type">Document de transport</div>
                <h1>{{ $titre }}</h1>
            </div>
            <span class="statut">{{ $statut }}@if($bon->deleted_at) · en corbeille @endif</span>
        </div>
    </header>

    <section class="infos">
        <div>
            <span class="k">N° du bon</span>
            <span class="v code">{{ $bon->code }}</span>
            <span class="sous">{{ $date?->format('d/m/Y à H:i') ?? '—' }}</span>
        </div>
        <div>
            <span class="k">{{ $roleTiers }}</span>
            <span class="v @if($arabe($tiers)) ar @endif">{{ $tiers }}</span>
            @if($telTiers)<span class="sous">{{ $telTiers }}</span>@endif
        </div>
        <div>
            <span class="k">Colis · montant CRBT</span>
            <span class="v">{{ $lignes->count() }} colis · {{ $dh($total) }} DH</span>
            @if($absents > 0)<span class="sous">{{ $absents }} colis introuvable(s)</span>@endif
        </div>
        <div class="barres">
            {!! $codeBarres !!}
            <div class="lib">{{ $bon->code }}</div>
        </div>
    </section>

    <table class="colis">
        <thead>
            <tr>
                <th>#</th>
                <th>Code suivi</th>
                <th>Destinataire</th>
                <th>Téléphone</th>
                <th>Ville</th>
                <th>Adresse</th>
                <th>Produit</th>
                <th class="r">CRBT (DH)</th>
                <th class="r">Remis</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lignes as $l)
                <tr class="{{ $l['absent'] ? 'absent' : '' }}">
                    <td class="n">{{ $l['sequence'] }}</td>
                    <td class="code">{{ $l['code'] ?? '—' }}</td>
                    <td class="{{ $arabe($l['destinataire']) ? 'ar' : '' }}">{{ $l['absent'] ? 'Colis absent de l’ancienne base' : ($l['destinataire'] ?? '—') }}</td>
                    <td class="tel">{{ $l['telephone'] ?? '' }}</td>
                    <td>{{ $l['ville'] ?? '' }}</td>
                    <td class="{{ $arabe($l['adresse']) ? 'ar' : '' }}">{{ $l['adresse'] ?? '' }}</td>
                    <td class="{{ $arabe($l['produit']) ? 'ar' : '' }}">{{ $l['produit'] ?? '' }}</td>
                    <td class="r"><b>{{ $l['absent'] ? '—' : $dh($l['montant']) }}</b></td>
                    <td class="case"><span></span></td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:18px;color:#64748b">Aucun colis dans ce bon.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">{{ $lignes->count() }} colis</td>
                <td class="r total" colspan="2">{{ $dh($total) }} DH</td>
            </tr>
        </tfoot>
    </table>

    <div class="controle">
        <span>Colis remis : <b>______</b></span>
        <span>Colis retournés : <b>______</b></span>
        <span>Montant encaissé : <b>______________ DH</b></span>
    </div>

    <section class="signatures">
        <div><span class="k">Visa de l’agence</span></div>
        <div><span class="k">Signature {{ mb_strtolower($roleTiers) === 'marchand' ? 'du marchand' : (mb_strtolower($roleTiers) === 'partenaire' ? 'du partenaire' : 'du livreur') }}</span></div>
    </section>

    <footer class="pied">
        <span>OUAISS EXPRESS — {{ $titre }} {{ $bon->code }}</span>
        <span>Imprimé le {{ now()->format('d/m/Y à H:i') }}@if($imprimePar) par {{ $imprimePar }}@endif</span>
    </footer>
</main>
</body>
</html>
