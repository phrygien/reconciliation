@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endassets

<x-filament-widgets::widget>
    <x-filament::section heading="Évolution des OUT par magasin">
        <x-slot name="afterHeader">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::input.wrapper class="w-32">
                    <x-filament::input.select wire:model.live="periode">
                        @foreach ($this->getPeriodes() as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper class="w-36">
                    <x-filament::input.select wire:model.live="metric">
                        <option value="qte">Qté OUT</option>
                        <option value="montant">Montant OUT</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper class="w-36">
                    <x-filament::input.select wire:model.live="vue">
                        <option value="separe">Séparé</option>
                        <option value="superpose">Superposé</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        <div class="space-y-10">
            @foreach ($this->getSocietesCharts() as $chart)
                <div wire:key="out-soc-{{ $chart['id'] }}-{{ $metric }}-{{ $periode }}-{{ $vue }}" class="space-y-3">
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {{ $chart['title'] }}
                    </div>

                    @if ($vue === 'separe')
                        {{-- Un mini-graphique par magasin, même échelle Y --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($chart['data']['datasets'] as $ds)
                                @php
                                    $single = [
                                        'labels' => $chart['data']['labels'],
                                        'datasets' => [array_merge($ds, [
                                            'fill' => true,
                                            'backgroundColor' => $ds['baseColor'] . '22',
                                            'borderWidth' => 2,
                                            'borderDash' => [],
                                        ])],
                                    ];
                                @endphp
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                    <div class="mb-1 flex items-baseline justify-between gap-2">
                                        <div class="flex min-w-0 items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-200">
                                            <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $ds['baseColor'] }}"></span>
                                            <span class="truncate">{{ $ds['label'] }}</span>
                                        </div>
                                        <div class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                            {{ number_format($ds['total'], 0, ',', ' ') }}
                                        </div>
                                    </div>

                                    <div
                                        wire:ignore
                                        x-data="{
                                            chart: null,
                                            init() {
                                                const fmt = (v) => new Intl.NumberFormat('fr-FR').format(v);
                                                this.chart = new Chart(this.$refs.canvas, {
                                                    type: 'line',
                                                    data: @js($single),
                                                    options: {
                                                        maintainAspectRatio: false,
                                                        interaction: { mode: 'index', intersect: false },
                                                        plugins: {
                                                            legend: { display: false },
                                                            tooltip: {
                                                                callbacks: {
                                                                    title: (i) => 'Jour ' + i[0].label,
                                                                    label: (c) => ' ' + fmt(c.parsed.y),
                                                                },
                                                            },
                                                        },
                                                        scales: {
                                                            x: {
                                                                grid: { display: false },
                                                                ticks: { color: 'rgba(128,128,128,.9)', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                                                            },
                                                            y: {
                                                                beginAtZero: true,
                                                                max: {{ $chart['max'] }},
                                                                grid: { color: 'rgba(128,128,128,.15)', drawTicks: false },
                                                                border: { display: false },
                                                                ticks: { color: 'rgba(128,128,128,.9)', padding: 6, maxTicksLimit: 4, callback: (v) => fmt(v) },
                                                            },
                                                        },
                                                    },
                                                });
                                            },
                                            destroy() { this.chart?.destroy(); },
                                        }"
                                        style="height: 150px;"
                                    >
                                        <canvas x-ref="canvas"></canvas>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Vue superposée --}}
                        <div
                            wire:ignore
                            x-data="{
                                chart: null,
                                init() {
                                    const fmt = (v) => new Intl.NumberFormat('fr-FR').format(v);
                                    const dim = (chart, index) => {
                                        chart.data.datasets.forEach((ds, i) => {
                                            ds.borderColor = (index === null || i === index) ? ds.baseColor : ds.baseColor + '25';
                                            ds.borderWidth = (index !== null && i === index) ? 3 : 1.5;
                                        });
                                        chart.update('none');
                                    };

                                    this.chart = new Chart(this.$refs.canvas, {
                                        type: 'line',
                                        data: @js($chart['data']),
                                        options: {
                                            maintainAspectRatio: false,
                                            interaction: { mode: 'index', intersect: false },
                                            plugins: {
                                                legend: {
                                                    position: 'top',
                                                    align: 'start',
                                                    labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10, padding: 14, font: { size: 11 } },
                                                    onHover: (e, item, legend) => dim(legend.chart, item.datasetIndex),
                                                    onLeave: (e, item, legend) => dim(legend.chart, null),
                                                },
                                                tooltip: {
                                                    itemSort: (a, b) => b.parsed.y - a.parsed.y,
                                                    filter: (item) => item.parsed.y > 0,
                                                    callbacks: {
                                                        title: (items) => 'Jour ' + items[0].label,
                                                        label: (ctx) => ' ' + ctx.dataset.label + ' : ' + fmt(ctx.parsed.y),
                                                        footer: (items) => 'Total : ' + fmt(items.reduce((s, i) => s + i.parsed.y, 0)),
                                                    },
                                                },
                                            },
                                            scales: {
                                                x: { grid: { display: false }, ticks: { color: 'rgba(128,128,128,.9)', maxRotation: 0 } },
                                                y: {
                                                    beginAtZero: true,
                                                    grid: { color: 'rgba(128,128,128,.15)', drawTicks: false },
                                                    border: { display: false },
                                                    ticks: { color: 'rgba(128,128,128,.9)', padding: 8, callback: (v) => fmt(v) },
                                                },
                                            },
                                        },
                                    });
                                },
                                destroy() { this.chart?.destroy(); },
                            }"
                            style="height: 420px;"
                        >
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
