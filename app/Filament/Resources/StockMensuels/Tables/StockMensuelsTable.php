<?php

namespace App\Filament\Resources\StockMensuels\Tables;

use App\Models\Periode;
use App\Models\Societe;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Exports\StockMensuelExporter;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
class StockMensuelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['periodeRef', 'societe', 'article']))
            ->defaultSort('periode', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('periode')
                    ->label('Période')
                    ->tooltip(fn ($record) => $record->periodeRef?->estOuverte()
                        ? 'Période ouverte'
                        : 'Période clôturée')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('societe.libelle')
                    ->label('Société')
                    ->description(fn ($record) => $record->code_stock)
                    ->searchable(['code_stock', 'societe.libelle'])
                    ->sortable(['code_stock'])
                    ->toggleable(),

                TextColumn::make('parkod')
                    ->label('Article')
                    ->description(fn ($record) => $record->article?->description)
                    ->weight('medium')
                    ->copyable()
                    ->searchable(isIndividual: true)
                    ->sortable(),

                ColumnGroup::make('Ouverture', [
                    TextColumn::make('qte_ouverture')
                        ->label('Qté')
                        ->numeric(decimalPlaces: 0, locale: 'fr')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),
                    TextColumn::make('val_ouverture')
                        ->label('Valeur')
                        ->numeric(decimalPlaces: 2, locale: 'fr')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),
                ]),

                ColumnGroup::make('Entrées', [
                    TextColumn::make('qte_in')
                        ->label('Qté')
                        ->numeric(decimalPlaces: 0, locale: 'fr')
                        ->color('success')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),
                    TextColumn::make('val_in')
                        ->label('Valeur')
                        ->numeric(decimalPlaces: 2, locale: 'fr')
                        ->color('success')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),
                ]),

                ColumnGroup::make('Sorties', [
                    TextColumn::make('qte_out')
                        ->label('Qté')
                        ->numeric(decimalPlaces: 0, locale: 'fr')
                        ->color('danger')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),
                    TextColumn::make('val_out')
                        ->label('Valeur')
                        ->numeric(decimalPlaces: 2, locale: 'fr')
                        ->color('danger')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),
                ]),

                ColumnGroup::make('Écart inventaire', [
                    TextColumn::make('qte_stk')
                        ->label('Qté')
                        ->numeric(decimalPlaces: 0, locale: 'fr')
                        ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : null))
                        ->alignEnd()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('val_stk')
                        ->label('Valeur')
                        ->numeric(decimalPlaces: 2, locale: 'fr')
                        ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : null))
                        ->alignEnd()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),

                ColumnGroup::make('Clôture', [
                    TextColumn::make('qte_cloture')
                        ->label('Qté')
                        ->numeric(decimalPlaces: 0, locale: 'fr')
                        ->weight('bold')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),
                    TextColumn::make('val_cloture')
                        ->label('Valeur')
                        ->numeric(decimalPlaces: 2, locale: 'fr')
                        ->alignEnd()
                        ->sortable()
                        ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),
                    TextColumn::make('pamp_cloture')
                        ->label('PAMP')
                        ->numeric(decimalPlaces: 4, locale: 'fr')
                        ->alignEnd()
                        ->sortable(),
                ]),

                /*TextColumn::make('coherent')
                    ->label('Cohérence')
                    ->state(fn ($record) => $record->estCoherent() ? 'OK' : 'Écart')
                    ->badge()
                    ->color(fn (string $state) => $state === 'OK' ? 'success' : 'danger')
                    ->tooltip(fn ($record) => $record->estCoherent()
                        ? 'Clôture conforme au calcul'
                        : 'Théorique : ' . $record->qteClotureTheorique() . ' u. / ' . $record->valClotureTheorique())
                    ->alignCenter(),*/

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
                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(fn () => Periode::query()
                        ->orderByDesc('periode')
                        ->pluck('periode', 'periode')
                        ->all())
                    ->default(fn () => [Periode::ouvertes()->orderByDesc('periode')->value('periode')])
                    ->searchable()
                    ->multiple(),

                SelectFilter::make('code_stock')
                    ->label('Société')
                    ->options(fn () => Societe::query()
                        ->orderBy('libelle')
                        ->pluck('libelle', 'code_stock')
                        ->all())
                    ->searchable()
                    ->multiple(),

                Filter::make('incoherents')
                    ->label('Clôtures incohérentes')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereRaw(
                        '(qte_cloture <> qte_ouverture + qte_in - qte_out + qte_stk
                          OR ROUND(val_cloture, 4) <> ROUND(val_ouverture + val_in - val_out + val_stk, 4))'
                    )),

                Filter::make('stock_negatif')
                    ->label('Stock de clôture négatif')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('qte_cloture', '<', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => $record->periodeRef?->estOuverte()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exporter en Excel')
                    ->exporter(StockMensuelExporter::class)
                    ->columnMappingColumns(2)
                    ->maxRows(200000),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Exporter la sélection')
                        ->exporter(StockMensuelExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun stock mensuel')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
