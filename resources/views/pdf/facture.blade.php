@php
    use App\Models\Facture;
    use Illuminate\Support\Str;

    // ---- À ADAPTER : informations de la société émettrice -------------------
    $societes = [
        'ADV' => [
            'nom'     => 'ADV',
            'adresse' => ['Adresse ligne 1', 'Ville, Pays'],
            'contact' => 'Tél : +230 000 0000 · compta@adv.example',
            'ident'   => 'BRN : C00000000 · VAT : 00000000',
        ],
        'COSMA' => [
            'nom'     => 'COSMA',
            'adresse' => ['Adresse ligne 1', 'Ville, Pays'],
            'contact' => 'Tél : +230 000 0000 · compta@cosma.example',
            'ident'   => 'BRN : C00000000 · VAT : 00000000',
        ],
    ];
    $devise = 'EUR';
    // -------------------------------------------------------------------------

    $emetteur = $societes[$record->facture_par] ?? ['nom' => $record->facture_par, 'adresse' => [], 'contact' => '', 'ident' => ''];
    $client   = $societes[$record->facturer_a]  ?? ['nom' => $record->facturer_a,  'adresse' => [], 'contact' => '', 'ident' => ''];

    $fmt = fn ($v, int $d = 2) => number_format((float) $v, $d, ',', ' ');

    [$tampon, $couleur] = match ($statutPdf) {
        Facture::STATUT_BROUILLON => ['BROUILLON', '#B45309'],
        Facture::STATUT_EMISE     => ['ÉMISE',     '#047857'],
        Facture::STATUT_ANNULEE   => ['ANNULÉE',   '#B91C1C'],
        default                   => [(string) $statutPdf, '#6B7280'],
    };

    $totalQte     = $lignes->sum('qte');
    $totalMontant = round((float) $lignes->sum('valeur'), 2);

    // montant en lettres (nécessite l'extension PHP intl, sinon ignoré)
    $enLettres = null;
    if (class_exists(\NumberFormatter::class)) {
        $nf        = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);
        $entier    = (int) floor($totalMontant);
        $centimes  = (int) round(($totalMontant - $entier) * 100);
        $enLettres = Str::ucfirst($nf->format($entier)) . " {$devise}"
                   . ($centimes > 0 ? ' et ' . $nf->format($centimes) . ' centimes' : '');
    }
@endphp
    <!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $record->numero }}</title>
    <style>
        @page { margin: 40px 40px 80px 40px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            color: #1F2937;
            line-height: 1.45;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .r { text-align: right; }
        .c { text-align: center; }
        .muted { color: #6B7280; }
        .small { font-size: 8px; }

        /* ---------- En-tête ---------- */
        .titre-doc   { font-size: 24px; font-weight: bold; letter-spacing: 3px; color: #111827; margin: 0; }
        .sous-titre  { font-size: 10px; color: #6B7280; letter-spacing: 1px; margin-top: 2px; }
        .societe-nom { font-size: 15px; font-weight: bold; color: #111827; }
        .barre       { height: 4px; background: #1F2937; margin: 14px 0 18px; }

        /* ---------- Blocs d'information ---------- */
        .bloc {
            border: 1px solid #D1D5DB;
            border-top: 3px solid #1F2937;
            padding: 8px 10px;
        }
        .bloc-titre {
            font-size: 8px; font-weight: bold; letter-spacing: 1.5px;
            color: #6B7280; text-transform: uppercase; margin-bottom: 4px;
        }
        .bloc-nom { font-size: 12px; font-weight: bold; color: #111827; margin-bottom: 2px; }

        .infos td { padding: 3px 0; border-bottom: 1px solid #E5E7EB; }
        .infos td.lbl { color: #6B7280; width: 45%; }
        .infos td.val { font-weight: bold; text-align: right; }

        /* ---------- Lignes ---------- */
        .lignes { margin-top: 20px; }
        .lignes thead th {
            background: #1F2937; color: #FFFFFF;
            font-size: 8px; letter-spacing: 0.8px; text-transform: uppercase;
            padding: 7px 6px; text-align: left;
        }
        .lignes thead th.r { text-align: right; }
        .lignes tbody td { padding: 6px; border-bottom: 1px solid #E5E7EB; }
        .lignes tbody tr.alt td { background: #F9FAFB; }
        .parkod { font-weight: bold; color: #111827; }
        .desc   { color: #4B5563; }

        /* ---------- Totaux ---------- */
        .totaux { margin-top: 14px; }
        .totaux td { padding: 5px 8px; }
        .totaux .lbl { color: #6B7280; text-align: right; }
        .totaux .val { text-align: right; font-weight: bold; width: 110px; }
        .totaux .grand td {
            background: #1F2937; color: #FFFFFF;
            font-size: 12px; font-weight: bold; padding: 9px 8px;
        }

        .lettres {
            margin-top: 14px; padding: 8px 10px;
            background: #F3F4F6; border-left: 3px solid #1F2937;
        }

        /* ---------- Signatures ---------- */
        .signatures { margin-top: 34px; }
        .signature {
            height: 70px; border: 1px solid #D1D5DB; padding: 6px 8px;
            font-size: 8px; color: #6B7280;
        }

        /* ---------- Tampon (répété sur chaque page) ---------- */
        .tampon {
            position: fixed;
            top: 120px;
            right: 50px;
            padding: 4px 18px;
            border: 3px double {{ $couleur }};
            color: {{ $couleur }};
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 3px;
            transform: rotate(-12deg);
        }

        /* ---------- Pied de page (répété) ---------- */
        .pied {
            position: fixed; left: 0; right: 0; bottom: -45px;
            border-top: 1px solid #D1D5DB; padding-top: 5px;
            font-size: 7.5px; color: #6B7280;
        }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>

<div class="tampon">{{ $tampon }}</div>

<div class="pied">
    <table>
        <tr>
            <td>{{ $emetteur['nom'] }} · {{ $emetteur['ident'] }}</td>
            <td class="c">Facture {{ $record->numero }}</td>
            <td class="r">Page <span class="pagenum"></span></td>
        </tr>
    </table>
</div>

{{-- ============ EN-TÊTE ============ --}}
<table>
    <tr>
        <td style="width:55%">
            <div class="societe-nom">{{ $emetteur['nom'] }}</div>
            @foreach ($emetteur['adresse'] as $ligne)
                <div class="muted">{{ $ligne }}</div>
            @endforeach
            @if ($emetteur['contact'])
                <div class="muted">{{ $emetteur['contact'] }}</div>
            @endif
            @if ($emetteur['ident'])
                <div class="muted">{{ $emetteur['ident'] }}</div>
            @endif
        </td>
        <td class="r">
            <h1 class="titre-doc">FACTURE</h1>
            <div class="sous-titre">DE RÉGULARISATION DE STOCK</div>
            <div style="font-size:13px; font-weight:bold; margin-top:6px;">N° {{ $record->numero }}</div>
        </td>
    </tr>
</table>

<div class="barre"></div>

{{-- ============ PARTIES + INFOS ============ --}}
<table>
    <tr>
        <td style="width:34%; padding-right:8px;">
            <div class="bloc">
                <div class="bloc-titre">Émetteur</div>
                <div class="bloc-nom">{{ $emetteur['nom'] }}</div>
                @foreach ($emetteur['adresse'] as $ligne)
                    <div>{{ $ligne }}</div>
                @endforeach
            </div>
        </td>
        <td style="width:34%; padding-right:8px;">
            <div class="bloc">
                <div class="bloc-titre">Facturé à</div>
                <div class="bloc-nom">{{ $client['nom'] }}</div>
                @foreach ($client['adresse'] as $ligne)
                    <div>{{ $ligne }}</div>
                @endforeach
            </div>
        </td>
        <td>
            <div class="bloc">
                <div class="bloc-titre">Détails</div>
                <table class="infos">
                    <tr><td class="lbl">Date</td><td class="val">{{ $dateFacture?->format('d/m/Y') ?? '—' }}</td></tr>
                    <tr><td class="lbl">Période</td><td class="val">{{ $record->periode }}</td></tr>
                    <tr><td class="lbl">Lot</td><td class="val">{{ $record->lot }}</td></tr>
                    <tr><td class="lbl">Devise</td><td class="val">{{ $devise }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div style="margin-top:16px;" class="muted">
    Régularisation des stocks d'ouverture de la période <strong>{{ $record->periode }}</strong> :
    les quantités négatives de {{ $client['nom'] }} sont compensées par {{ $emetteur['nom'] }},
    valorisées au PAMP d'ouverture du stock cédant.
</div>

{{-- ============ LIGNES ============ --}}
<table class="lignes">
    <thead>
    <tr>
        <th style="width:4%">#</th>
        <th style="width:13%">Article</th>
        <th style="width:14%">EAN</th>
        <th style="width:13%">Marque</th>
        <th>Désignation</th>
        <th class="r" style="width:7%">Qté</th>
        <th class="r" style="width:10%">PAMP</th>
        <th class="r" style="width:12%">Montant</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($lignes as $i => $l)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td class="muted">{{ $i + 1 }}</td>
            <td class="parkod">{{ $l->parkod }}</td>
            <td>{{ $l->article?->ean ?? '—' }}</td>
            <td>{{ $l->article?->marque?->nom ?? '—' }}</td>
            <td class="desc">{{ Str::limit($l->article?->description ?? '—', 50) }}</td>
            <td class="r">{{ $fmt($l->qte, 0) }}</td>
            <td class="r">{{ $fmt($l->pamp, 4) }}</td>
            <td class="r"><strong>{{ $fmt($l->valeur) }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- ============ TOTAUX ============ --}}
<table class="totaux" style="page-break-inside: avoid;">
    <tr>
        <td style="width:55%"></td>
        <td>
            <table>
                <tr>
                    <td class="lbl">Nombre d'articles</td>
                    <td class="val">{{ $fmt($lignes->count(), 0) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Quantité totale</td>
                    <td class="val">{{ $fmt($totalQte, 0) }}</td>
                </tr>
                <tr class="grand">
                    <td class="r">TOTAL À FACTURER ({{ $devise }})</td>
                    <td class="r">{{ $fmt($totalMontant) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if ($enLettres)
    <div class="lettres" style="page-break-inside: avoid;">
        <span class="muted">Arrêtée la présente facture à la somme de :</span><br>
        <strong>{{ $enLettres }}</strong>
    </div>
@endif

{{-- ============ SIGNATURES ============ --}}
<table class="signatures" style="page-break-inside: avoid;">
    <tr>
        <td style="width:48%">
            <div class="signature">Établi par — {{ $emetteur['nom'] }}<br>Nom, date et signature</div>
        </td>
        <td style="width:4%"></td>
        <td>
            <div class="signature">Reçu par — {{ $client['nom'] }}<br>Nom, date et signature</div>
        </td>
    </tr>
</table>

</body>
</html>
