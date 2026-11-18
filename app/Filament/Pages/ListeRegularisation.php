<?php

namespace App\Filament\Pages;

use App\Services\Regularisation;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ListeRegularisation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Régularisation';

    protected static ?string $title = 'Régularisation des stocks';

    protected static ?string $slug = 'regularisation';

    protected string $view = 'filament.pages.liste-regularisation';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * Interroge le service avec l'état courant des filtres (lu depuis Livewire).
     */
    protected function fetchRegularisation(int $page, int $perPage): LengthAwarePaginator
    {
        $filtre    = data_get($this->tableFilters, 'filtre.value');
        $periode   = data_get($this->tableFilters, 'periode.value');
        $facturerA = data_get($this->tableFilters, 'facturer_a.value');

        return app(Regularisation::class)->listeRegularisation(
            page: $page,
            perPage: $perPage,
            filtre: $filtre ?: Regularisation::FILTRE_ALL,
            periode: $periode ?: null,
            facturerA: $facturerA ?: null,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $paginator = $this->fetchRegularisation($page, $recordsPerPage);

                // Page hors limites après changement de filtre : on recharge la dernière page
                if ($paginator->isEmpty() && $page > 1) {
                    $lastPage = max($paginator->lastPage(), 1);

                    $this->setPage($lastPage, $this->getTablePaginationPageName());

                    $paginator = $this->fetchRegularisation($lastPage, $recordsPerPage);
                }

                // Filament attend des tableaux indexés par une clé unique
                $paginator->setCollection(
                    $paginator->getCollection()->mapWithKeys(
                        fn ($row) => [$row->periode . '|' . $row->parkod => (array) $row]
                    )
                );

                return $paginator;
            })
            ->columns([
                TextColumn::make('periode')
                    ->label('Période'),

                TextColumn::make('parkod')
                    ->label('Article'),

                TextColumn::make('ean')
                    ->label('EAN')
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('marque')
                    ->label('Marque')
                    ->placeholder('—'),

                TextColumn::make('description')
                    ->label('Description')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn ($state): ?string => is_string($state) && mb_strlen($state) > 50 ? $state : null),

                TextColumn::make('qte_adv')
                    ->label('Qté ADV')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),

                TextColumn::make('qte_cosma')
                    ->label('Qté COSMA')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),

                TextColumn::make('qte_total')
                    ->label('Qté totale')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),

                TextColumn::make('cas')
                    ->label('Cas')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'COMPENSABLE_TOTAL'   => 'Compensable total',
                        'COMPENSABLE_PARTIEL' => 'Compensable partiel',
                        'NON_COMPENSABLE'     => 'Non compensable',
                        default               => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'COMPENSABLE_TOTAL'   => 'success',
                        'COMPENSABLE_PARTIEL' => 'warning',
                        'NON_COMPENSABLE'     => 'danger',
                        default               => 'gray',
                    }),

                TextColumn::make('facturer_a')
                    ->label('Facturer à')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('facture_par')
                    ->label('Facturé par')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('qte_a_facturer')
                    ->label('Qté à facturer')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('pamp')
                    ->label('PAMP')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('montant_facture')
                    ->label('Montant facturé')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('qte_residuelle')
                    ->label('Qté résiduelle')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('filtre')
                    ->label('Affichage')
                    ->options([
                        Regularisation::FILTRE_ALL           => 'Tous',
                        Regularisation::FILTRE_A_REGULARISER => 'À régulariser',
                        Regularisation::FILTRE_ANOMALIES     => 'Anomalies',
                    ])
                    ->default(Regularisation::FILTRE_ALL)
                    ->selectablePlaceholder(false),

                SelectFilter::make('periode')
                    ->label('Période')
                    ->placeholder('Toutes les périodes ouvertes')
                    ->options(fn () => DB::table('periodes')
                        ->where('statut', 'OUVERTE')
                        ->orderBy('periode')
                        ->pluck('periode', 'periode')
                        ->all()),

                SelectFilter::make('facturer_a')
                    ->label('Facturer à')
                    ->placeholder('Tous')
                    ->options([
                        Regularisation::FACTURER_A_ADV   => 'ADV',
                        Regularisation::FACTURER_A_COSMA => 'COSMA',
                    ]),
            ])
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Aucun article à régulariser');
    }
}
