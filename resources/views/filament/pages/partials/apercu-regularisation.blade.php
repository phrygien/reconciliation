@php
    $fmt = fn ($v, int $d = 2) => $v === null ? '—' : number_format((float) $v, $d, ',', ' ');

    $lignesCol    = collect($lignes);
    $totalQte     = $lignesCol->sum('qte_a_facturer');
    $totalMontant = round((float) $lignesCol->sum('valeur_a_facturer'), 2);
    $tronque      = $nb > $limite;
@endphp

<div class="border border-black bg-white text-black">

    {{-- ============ EN-TÊTE ============ --}}
    <div class="flex items-end justify-between border-b border-black px-4 py-3">
        <div>
            <div class="text-lg font-bold uppercase tracking-widest">Facture</div>
            <div class="text-xs uppercase tracking-wider text-gray-600">de régularisation de stock</div>
        </div>
        <div class="text-right text-xs text-gray-600">
            {{ $fmt($nb, 0) }} {{ $nb > 1 ? 'lignes' : 'ligne' }}
        </div>
    </div>

    {{-- ============ LIGNES ============ --}}
    <div class="overflow-auto" style="max-height: 24rem;">
        <table class="w-full border-collapse text-sm">
            <thead class="sticky top-0 bg-white">
            <tr class="border-b border-black text-left text-xs font-bold uppercase tracking-wide text-black">
                <th class="px-3 py-2 whitespace-nowrap">Période</th>
                <th class="px-3 py-2 whitespace-nowrap">Article</th>
                <th class="px-3 py-2 whitespace-nowrap">EAN</th>
                <th class="px-3 py-2 whitespace-nowrap">Marque</th>
                <th class="px-3 py-2 whitespace-nowrap">Description</th>
                <th class="px-3 py-2 text-right whitespace-nowrap">Qté ADV</th>
                <th class="px-3 py-2 text-right whitespace-nowrap">Qté COSMA</th>
                <th class="px-3 py-2 text-center whitespace-nowrap">Facturer à</th>
                <th class="px-3 py-2 text-center whitespace-nowrap">Facturé par</th>
                <th class="px-3 py-2 text-right whitespace-nowrap">Qté à facturer</th>
                <th class="px-3 py-2 text-right whitespace-nowrap">PAMP</th>
                <th class="px-3 py-2 text-right whitespace-nowrap">Montant</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($lignes as $l)
                <tr class="border-b border-gray-300 text-black last:border-black">
                    <td class="px-3 py-2 whitespace-nowrap">{{ $l['periode'] }}</td>
                    <td class="px-3 py-2 font-bold whitespace-nowrap">{{ $l['parkod'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $l['ean'] ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $l['marque'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-gray-700" title="{{ $l['description'] ?? '' }}">
                        {{ \Illuminate\Support\Str::limit($l['description'] ?? '—', 40) }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $fmt($l['qte_adv']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $fmt($l['qte_cosma']) }}</td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">{{ $l['facturer_a'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">{{ $l['facture_par'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $fmt($l['qte_a_facturer']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $fmt($l['pamp'], 4) }}</td>
                    <td class="px-3 py-2 text-right font-bold tabular-nums whitespace-nowrap">{{ $fmt($l['valeur_a_facturer']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- ============ TOTAUX ============ --}}
    <div class="flex justify-end px-4 py-3">
        <table class="text-sm" style="min-width: 18rem;">
            <tr>
                <td class="py-1 pr-6 text-right text-gray-600">Nombre de lignes{{ $tronque ? ' affichées' : '' }}</td>
                <td class="py-1 text-right font-bold tabular-nums">{{ $fmt($lignesCol->count(), 0) }}</td>
            </tr>
            <tr>
                <td class="py-1 pr-6 text-right text-gray-600">Quantité totale{{ $tronque ? ' affichée' : '' }}</td>
                <td class="py-1 text-right font-bold tabular-nums">{{ $fmt($totalQte) }}</td>
            </tr>
            <tr class="border-y-2 border-black text-base font-bold">
                <td class="py-2 pr-6 text-right uppercase">Total à facturer{{ $tronque ? ' (lignes affichées)' : '' }}</td>
                <td class="py-2 text-right tabular-nums">{{ $fmt($totalMontant) }}</td>
            </tr>
        </table>
    </div>

    @if ($tronque)
        <p class="border-t border-black px-4 py-2 text-xs text-gray-600">
            {{ $limite }} premières lignes affichées sur {{ $nb }}. Toutes les lignes seront régularisées.
        </p>
    @endif
</div>
