@php
    use Illuminate\Support\Facades\Vite;
    use Illuminate\Support\Str;
    $arabe = static fn ($s): bool => (bool) preg_match('/\p{Arabic}/u', (string) $s);
    $dh = static fn ($v): string => number_format((float) $v, 2, ',', ' ');
    $police = static function (string $src): ?string {
        try { return Vite::asset($src); } catch (\Throwable) { return null; }
    };
    $inter = $police('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2');
    $kufi = $police('resources/fonts/droid-arabic-kufi-regular.woff');
    $kufiGras = $police('resources/fonts/droid-arabic-kufi-bold.woff');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Étiquettes colis ({{ $etiquettes->count() }}) — OUAISS EXPRESS</title>
<style>
    @if($inter)@font-face { font-family: 'Inter Variable'; src: url('{{ $inter }}') format('woff2'); font-weight: 100 900; }@endif
    @if($kufi)@font-face { font-family: 'Droid Arabic Kufi'; src: url('{{ $kufi }}') format('woff'); font-weight: 400; }@endif
    @if($kufiGras)@font-face { font-family: 'Droid Arabic Kufi'; src: url('{{ $kufiGras }}') format('woff'); font-weight: 700; }@endif

    /* Étiquette thermique standard 100 × 150 mm, une par page. Noir et blanc d'abord : la couleur
       n'est qu'un accent (elle sort en gris sur une imprimante thermique). */
    @page { size: 100mm 150mm; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: #e9eef2; color: #000; font: 10px/1.3 'Inter Variable', 'Droid Arabic Kufi', Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ar { font-family: 'Droid Arabic Kufi', 'Inter Variable', sans-serif; direction: rtl; unicode-bidi: plaintext; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }

    .etiquette { width: 100mm; height: 150mm; margin: 14px auto; padding: 4mm; background: #fff; display: flex; flex-direction: column; gap: 2.4mm; overflow: hidden; page-break-after: always; }
    .etiquette:last-child { page-break-after: auto; }

    /* 1. Marque + agence */
    .haut { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 1.6mm; border-bottom: 0.5mm solid #000; }
    .marque { font-size: 15px; font-weight: 800; letter-spacing: -.02em; line-height: 1; }
    .marque b { color: #004c68; } .marque i { font-style: normal; color: #ff8806; }
    .marque small { display: block; margin-top: 1px; font-size: 6.5px; font-weight: 600; letter-spacing: .2em; color: #444; text-transform: uppercase; }
    .agence { text-align: right; font-size: 8px; line-height: 1.35; color: #222; }

    /* 2. Destination + montant à encaisser */
    .cle { display: grid; grid-template-columns: 1fr 34mm; gap: 2mm; }
    .destination { border: 0.4mm solid #000; border-radius: 1.5mm; padding: 1.6mm 2mm; display: flex; flex-direction: column; justify-content: center; }
    .destination .lbl { font-size: 6.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
    .destination .ville { font-size: 19px; font-weight: 800; text-transform: uppercase; line-height: 1.05; letter-spacing: -.01em; word-break: break-word; }
    .destination .zone { display: inline-block; margin-top: 1mm; border: 0.3mm solid #000; border-radius: 1mm; padding: 0 1.4mm; font-size: 8px; font-weight: 700; letter-spacing: .1em; }
    .montant { background: #000; color: #fff; border-radius: 1.5mm; padding: 1.6mm 2mm; display: flex; flex-direction: column; justify-content: center; text-align: center; }
    .montant .lbl { font-size: 6.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; opacity: .85; }
    .montant .val { font-size: 17px; font-weight: 800; line-height: 1.1; }
    .montant .dev { font-size: 8px; font-weight: 700; }
    .montant.paye { background: #fff; color: #000; border: 0.4mm dashed #000; }

    /* 3. Code-barres */
    .barres { text-align: center; }
    .barres svg { width: 88%; height: 18mm; display: block; margin: 0 auto; }
    .barres .code { margin-top: .8mm; font-size: 13px; font-weight: 800; letter-spacing: .14em; }

    /* 4. Destinataire */
    .bloc { border-top: 0.3mm solid #000; padding-top: 1.6mm; }
    .bloc .lbl { font-size: 6.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #333; }
    .dest-nom { font-size: 13px; font-weight: 800; line-height: 1.15; margin-top: .4mm; }
    .dest-tel { font-size: 13px; font-weight: 700; letter-spacing: .04em; }
    .dest-adr { font-size: 9px; line-height: 1.3; margin-top: .4mm; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

    /* 5. Expéditeur | Produit */
    .duo { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; }
    .duo .val { font-size: 9.5px; font-weight: 700; margin-top: .4mm; line-height: 1.25; }
    .duo .sec { font-size: 8.5px; color: #333; }

    /* 6. Options */
    .options { display: flex; gap: 4mm; font-size: 8.5px; font-weight: 600; }
    .options span::before { content: ''; display: inline-block; width: 2.6mm; height: 2.6mm; margin-right: 1.2mm; vertical-align: -0.5mm; border: 0.3mm solid #000; border-radius: .4mm; }
    .options span.oui::before { background: #000; box-shadow: inset 0 0 0 0.5mm #fff; }

    /* 7. Pied */
    .pied { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end; padding-top: 1.4mm; border-top: 0.5mm solid #000; font-size: 7.5px; color: #222; }

    @media print {
        html, body { background: #fff; }
        .etiquette { margin: 0; }
    }
</style>
</head>
<body>
@foreach($etiquettes as $e)
    <article class="etiquette">
        <header class="haut">
            <div class="marque"><b>OUAISS</b> <i>EXPRESS</i><small>Livraison express · Maroc</small></div>
            <div class="agence">
                {{ $agence?->nom ?? 'OUAISS EXPRESS' }}<br>
                Enregistré le {{ $e['date']?->format('d/m/Y') ?? '—' }}
            </div>
        </header>

        <section class="cle">
            <div class="destination">
                <span class="lbl">Destination</span>
                <span class="ville {{ $arabe($e['ville']) ? 'ar' : '' }}">{{ Str::limit($e['ville'] ?: '—', 22, '…') }}</span>
                @if($e['zone'])<span><span class="zone">Zone {{ $e['zone'] }}</span></span>@endif
            </div>
            <div class="montant {{ $e['montant'] <= 0 ? 'paye' : '' }}">
                <span class="lbl">{{ $e['montant'] > 0 ? 'À encaisser' : 'Montant' }}</span>
                @if($e['montant'] > 0)
                    <span class="val">{{ $dh($e['montant']) }}</span><span class="dev">DH</span>
                @else
                    <span class="val" style="font-size:13px">Déjà réglé</span>
                @endif
            </div>
        </section>

        <section class="barres">
            {!! str_replace('<svg ', '<svg preserveAspectRatio="none" ', $e['codeBarres']) !!}
            <div class="code mono">{{ $e['code'] }}</div>
        </section>

        <section class="bloc">
            <span class="lbl">Destinataire</span>
            <div class="dest-nom {{ $arabe($e['destinataire']) ? 'ar' : '' }}">{{ Str::limit($e['destinataire'] ?: '—', 34, '…') }}</div>
            <div class="dest-tel mono">{{ $e['telephone'] ?: '—' }}</div>
            @if($e['adresse'])<div class="dest-adr {{ $arabe($e['adresse']) ? 'ar' : '' }}">{{ $e['adresse'] }}</div>@endif
        </section>

        <section class="bloc duo">
            <div>
                <span class="lbl">Expéditeur</span>
                <div class="val {{ $arabe($e['expediteur']) ? 'ar' : '' }}">{{ Str::limit($e['expediteur'] ?: '—', 30, '…') }}</div>
                @if($e['telephone_expediteur'])<div class="sec mono">{{ $e['telephone_expediteur'] }}</div>@endif
            </div>
            <div>
                <span class="lbl">Contenu</span>
                <div class="val {{ $arabe($e['produit']) ? 'ar' : '' }}">{{ Str::limit($e['produit'] ?: 'Non précisé', 52, '…') }}</div>
                <div class="sec">Quantité : {{ max(1, $e['quantite']) }}</div>
            </div>
        </section>

        <section class="options">
            <span class="{{ $e['ouverture'] ? 'oui' : '' }}">Ouverture autorisée</span>
            <span class="{{ $e['echange'] ? 'oui' : '' }}">Échange</span>
        </section>

        <footer class="pied">
            <span>Service client{{ $agence?->telephone ? ' : '.$agence->telephone : '' }} · ouaissexpress.com</span>
            <span class="mono">{{ $loop->iteration }}/{{ $loop->count }}</span>
        </footer>
    </article>
@endforeach
</body>
</html>
