@php
    $fmt = fn ($v, int $d = 2) => $v === null ? '—' : number_format((float) $v, $d, ',', ' ');
@endphp

<div class="rounded-lg ring-1 ring-gray-950/10 dark:ring-white/20">
    <div class="overflow-auto" style="max-height: 24rem;">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
            <tr class="text-left text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">
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

            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($lignes as $l)
                <tr class="text-gray-950 dark:text-white">
                    <td class="px-3 py-2 whitespace-nowrap">{{ $l['periode'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $l['parkod'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $l['ean'] ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $l['marque'] ?? '—' }}</td>
                    <td class="px-3 py-2" title="{{ $l['description'] ?? '' }}">
                        {{ \Illuminate\Support\Str::limit($l['description'] ?? '—', 40) }}
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fmt($l['qte_adv']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fmt($l['qte_cosma']) }}</td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">{{ $l['facturer_a'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">{{ $l['facture_par'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fmt($l['qte_a_facturer']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fmt($l['pamp'], 4) }}</td>
                    <td class="px-3 py-2 text-right font-semibold whitespace-nowrap">{{ $fmt($l['valeur_a_facturer']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if ($nb > $limite)
        <p class="border-t border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
            {{ $limite }} premières lignes affichées sur {{ $nb }}. Toutes les lignes seront régularisées.
        </p>
    @endif
</div>
