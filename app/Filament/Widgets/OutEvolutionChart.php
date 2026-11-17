<?php

namespace App\Filament\Widgets;

use App\Models\Magasin;
use App\Models\MouvementBrut;
use App\Models\Societe;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class OutEvolutionChart extends Widget
{
    protected string $view = 'filament.widgets.out-evolution-chart';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public string $metric = 'qte';

    public string $vue = 'separe';

    public ?string $periode = null;

    public function mount(): void
    {
        $this->periode = $this->getPeriodes()->last();
    }

    /** Exclut les magasins de type dépôt (les magasins sans type sont conservés). */
    protected function sansDepot(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('type_magasin')
                ->orWhere('type_magasin', '!=', 'depot');
        });
    }

    /** Périodes (année-mois) extraites de date_mvt pour les OUT. */
    public function getPeriodes()
    {
        return MouvementBrut::query()
            ->out()
            ->whereNotNull('date_mvt')
            ->whereHas('magasinDestination', fn (Builder $q) => $this->sansDepot($q))
            ->selectRaw("DISTINCT DATE_FORMAT(date_mvt, '%Y-%m') as ym")
            ->orderBy('ym')
            ->pluck('ym');
    }

    public function getSocietesCharts(): array
    {
        if (! $this->periode) {
            return [];
        }

        $column = $this->metric === 'montant' ? 'montant' : 'qte';

        $start = CarbonImmutable::createFromFormat('Y-m-d', $this->periode . '-01')->startOfMonth();
        $end   = $start->endOfMonth();

        $jours  = range(1, $start->daysInMonth);
        $labels = array_map(fn ($j) => str_pad((string) $j, 2, '0', STR_PAD_LEFT), $jours);

        // OUT par magasin de destination (hors dépôt) et par jour
        $totaux = MouvementBrut::query()
            ->out()
            ->whereHas('magasinDestination', fn (Builder $q) => $this->sansDepot($q))
            ->whereBetween('date_mvt', [$start, $end])
            ->selectRaw("mag_dest, DAY(date_mvt) as jour, ABS(SUM({$column})) as total")
            ->groupBy('mag_dest', 'jour')
            ->get()
            ->groupBy('mag_dest');

        // Magasins (hors dépôt) groupés par société
        $magasinsParSociete = $this->sansDepot(Magasin::query())
            ->whereIn('mag_code', $totaux->keys())
            ->orderBy('mag_code')
            ->get()
            ->groupBy('societe_id');

        $palette = [
            '#2563eb', '#dc2626', '#16a34a', '#f59e0b', '#7c3aed',
            '#0891b2', '#db2777', '#65a30d', '#ea580c', '#475569',
        ];

        $charts = [];

        foreach (Societe::query()->orderBy('code_stock')->get() as $societe) {
            $datasets = [];

            foreach (($magasinsParSociete[$societe->id] ?? collect())->values() as $i => $magasin) {
                $byJour = $totaux[$magasin->mag_code]->pluck('total', 'jour');
                $color  = $palette[$i % count($palette)];
                $data   = array_map(fn ($j) => (float) ($byJour[$j] ?? 0), $jours);

                $datasets[] = [
                    'label'            => $magasin->mag_code . ($magasin->libelle ? ' · ' . $magasin->libelle : ''),
                    'data'             => $data,
                    'total'            => array_sum($data),
                    'baseColor'        => $color,
                    'borderColor'      => $color,
                    'backgroundColor'  => $color,
                    'borderWidth'      => 1.5,
                    'borderDash'       => $i >= 5 ? [6, 4] : [],
                    'tension'          => 0,
                    'pointRadius'      => 0,
                    'pointHoverRadius' => 5,
                    'pointHitRadius'   => 12,
                ];
            }

            // Plus gros magasins en premier
            usort($datasets, fn ($a, $b) => $b['total'] <=> $a['total']);

            $charts[] = [
                'id'    => $societe->id,
                'title' => $societe->code_stock . ($societe->libelle ? ' · ' . $societe->libelle : ''),
                'max'   => collect($datasets)->flatMap(fn ($d) => $d['data'])->max() ?: 1,
                'data'  => ['labels' => $labels, 'datasets' => $datasets],
            ];
        }

        return $charts;
    }
}
