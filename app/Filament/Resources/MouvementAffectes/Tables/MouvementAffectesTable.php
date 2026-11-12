<?php

namespace App\Filament\Resources\MouvementAffectes\Tables;

use App\Filament\Exports\MouvementAffecteExporter;
use App\Models\EtatStockWinparf;
use App\Models\MouvementAffecte;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MouvementAffectesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'societe',
                'mouvementBrut.article',
                'etatsStockWinparf',
            ]))
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('(qte < 0) asc')   // IN d'abord, puis OUT
                ->orderByDesc('date_comptable')
                ->orderByDesc('id'))
            ->striped()
            ->persistFiltersInSession()
            ->headerActions([
                ExportAction::make()
                    ->label('Exporter Excel')
                    ->icon(Phosphor::MicrosoftExcelLogo)
                    ->color('success')
                    ->button()
                    ->exporter(MouvementAffecteExporter::class)
                    ->columnMappingColumns(2),
            ])
            ->columns([
                TextColumn::make('sens')
                    ->label('Sens')
                    ->state(fn (MouvementAffecte $record) => $record->sens)
                    ->badge()
                    ->color(fn (string $state) => $state === 'IN' ? 'success' : 'danger'),

                TextColumn::make('code_stock')
                    ->label('Code stock')
                    ->badge()
                    ->color('gray')
                    ->description(fn (MouvementAffecte $record) => $record->societe?->libelle)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('date_comptable')
                    ->label('Date comptable')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('mouvementBrut.parkod')
                    ->label('Article')
                    ->description(function (MouvementAffecte $record, $livewire) {
                        $desc = e($record->mouvementBrut?->article?->description ?? '');
                        $ligne = self::ligneStockWinparf($record, $livewire);

                        return new HtmlString(
                            $desc . '<br><span style="font-size:.75rem;opacity:.75;">' . e($ligne) . '</span>'
                        );
                    })
                    ->weight('medium')
                    ->copyable()
                    ->searchable(isIndividual: true),

                TextColumn::make('mouvementBrut.typ_mvt')
                    ->label('Type mvt')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('mag_orig')
                    ->label('Mag. origine')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('mag_dest')
                    ->label('Mag. destination')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('qte')
                    ->label('Qté')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),

                TextColumn::make('valeur')
                    ->label('Valeur')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),

                TextColumn::make('mode_affect')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        MouvementAffecte::MODE_AUTO => 'success',
                        MouvementAffecte::MODE_REPARTI => 'info',
                        MouvementAffecte::MODE_MANUEL => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        MouvementAffecte::MODE_AUTO => 'Automatique',
                        MouvementAffecte::MODE_REPARTI => 'Réparti',
                        MouvementAffecte::MODE_MANUEL => 'Manuel',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('mouvement_brut_id')
                    ->label('N° brut')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sens')
                    ->label('Sens')
                    ->options(['IN' => 'Entrées (IN)', 'OUT' => 'Sorties (OUT)'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'IN' => $query->where('qte', '>=', 0),
                        'OUT' => $query->where('qte', '<', 0),
                        default => $query,
                    }),

                SelectFilter::make('mode_affect')
                    ->label('Mode')
                    ->options([
                        MouvementAffecte::MODE_AUTO => 'Automatique',
                        MouvementAffecte::MODE_REPARTI => 'Réparti',
                        MouvementAffecte::MODE_MANUEL => 'Manuel',
                    ])
                    ->multiple(),

                Filter::make('avec_stock_winparf')
                    ->label('Avec état stock WinParf')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereExists(
                        fn ($q) => $q->selectRaw('1')
                            ->from('etat_stock_winparf as e')
                            ->join('mouvement_bruts as eb', 'eb.id', '=', 'e.mouvement_brut_id')
                            ->join('mouvement_bruts as mb', 'mb.id', '=', 'mouvement_affectes.mouvement_brut_id')
                            ->whereColumn('e.code_stock', 'mouvement_affectes.code_stock')
                            ->whereColumn('eb.parkod', 'mb.parkod')
                            ->whereColumn('e.date_comptable', '<=', 'mouvement_affectes.date_comptable')
                    )),

                Filter::make('date_comptable')
                    ->label('Date comptable')
                    ->schema([
                        DatePicker::make('du')->label('Du'),
                        DatePicker::make('au')->label('Au'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn (Builder $q, $d) => $q->whereDate('date_comptable', '>=', $d))
                        ->when($data['au'] ?? null, fn (Builder $q, $d) => $q->whereDate('date_comptable', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Exporter la sélection')
                        ->exporter(MouvementAffecteExporter::class)
                        ->columnMappingColumns(2),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun mouvement affecté')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    /**
     * Texte "Stock WinParf" : état direct (même mouvement brut + code_stock),
     * sinon dernier état du même article/code_stock à date <= date_comptable.
     * Les états de repli de la page sont chargés en une seule requête.
     */
    private static function ligneStockWinparf(MouvementAffecte $record, $livewire): string
    {
        $etat = $record->etatsStockWinparf->firstWhere('code_stock', $record->code_stock)
            ?? self::etatRepli($record, $livewire);

        if (! $etat) {
            return 'Stock WinParf : —';
        }

        return 'Stock WinParf : qté '
            . number_format((int) $etat->qte, 0, ',', ' ')
            . ' · valeur '
            . number_format((float) $etat->valeur, 2, ',', ' ');
    }

    private static function etatRepli(MouvementAffecte $record, $livewire): ?EtatStockWinparf
    {
        static $cache = [];

        $pageKey = spl_object_id($livewire) . ':' . md5(json_encode($livewire->getTableRecords()->pluck('id')));

        if (! isset($cache[$pageKey])) {
            $records = $livewire->getTableRecords();

            $parkods = $records->map(fn ($r) => $r->mouvementBrut?->parkod)->filter()->unique()->values();
            $codes = $records->pluck('code_stock')->unique()->values();

            $etats = EtatStockWinparf::query()
                ->join('mouvement_bruts', 'mouvement_bruts.id', '=', 'etat_stock_winparf.mouvement_brut_id')
                ->whereIn('mouvement_bruts.parkod', $parkods)
                ->whereIn('etat_stock_winparf.code_stock', $codes)
                ->select('etat_stock_winparf.*', 'mouvement_bruts.parkod as parkod_article')
                ->orderByDesc('etat_stock_winparf.date_comptable')
                ->orderByDesc('etat_stock_winparf.stock_winparf_id')
                ->get();

            $cache = [$pageKey => $etats->groupBy(fn ($e) => $e->parkod_article . '|' . $e->code_stock)];
        }

        $parkod = $record->mouvementBrut?->parkod;

        if (! $parkod || ! $record->date_comptable) {
            return null;
        }

        return $cache[$pageKey]
            ->get($parkod . '|' . $record->code_stock)
            ?->first(fn ($e) => $e->date_comptable <= $record->date_comptable);
    }
}
