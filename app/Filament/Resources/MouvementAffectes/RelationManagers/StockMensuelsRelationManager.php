<?php

namespace App\Filament\Resources\MouvementAffectes\RelationManagers;

use App\Models\StockMensuel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMensuelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMensuels';

    protected static ?string $title = 'Stock mensuel';

    protected static ?string $modelLabel = 'stock mensuel';

    protected static ?string $pluralModelLabel = 'stocks mensuels';

    /** Format de la colonne `periode` : adapte si besoin (ex. 'Ym'). */
    private const PERIODE_FORMAT = 'Y-m';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stock mensuel')
            ->description(function (): ?string {
                $brut = $this->getOwnerRecord()->mouvementBrut;

                if (! $brut) {
                    return null;
                }

                return trim($brut->parkod . ' — ' . ($brut->article?->description ?? ''), ' —');
            })
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['article', 'societe']))
            ->defaultSort('periode', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->recordClasses(fn (StockMensuel $record) => $record->periode === $this->periodeAffectation()
                ? 'bg-primary-50 dark:bg-primary-500/10'
                : null)
            ->columns([
                TextColumn::make('periode')
                    ->label('Période')
                    ->weight('bold')
                    ->badge(fn (StockMensuel $record) => $record->periode === $this->periodeAffectation())
                    ->color(fn (StockMensuel $record) => $record->periode === $this->periodeAffectation() ? 'primary' : 'gray')
                    ->tooltip(fn (StockMensuel $record) => $record->periode === $this->periodeAffectation()
                        ? 'Période de l\'affectation'
                        : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parkod')
                    ->label('Article')
                    ->weight('bold')
                    ->description(fn (StockMensuel $record) => $record->article?->description)
                    ->wrap()
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code_stock')
                    ->label('Code stock')
                    ->badge()
                    ->color('gray')
                    ->description(fn (StockMensuel $record) => $record->societe?->libelle)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('qte_ouverture')
                    ->label('Qté ouverture')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('val_ouverture')
                    ->label('Val. ouverture')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('qte_in')
                    ->label('Qté entrées')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color('success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),

                TextColumn::make('val_in')
                    ->label('Val. entrées')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color('success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),

                TextColumn::make('qte_out')
                    ->label('Qté sorties')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color('danger')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),

                TextColumn::make('val_out')
                    ->label('Val. sorties')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color('danger')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),

                TextColumn::make('qte_stk')
                    ->label('Qté écart inv.')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : null))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('val_stk')
                    ->label('Val. écart inv.')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : null))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('qte_cloture')
                    ->label('Qté clôture')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('val_cloture')
                    ->label('Val. clôture')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('pamp_cloture')
                    ->label('PAMP clôture')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('coherent')
                    ->label('Cohérent')
                    ->state(fn (StockMensuel $record) => $record->estCoherent())
                    ->boolean()
                    ->tooltip(fn (StockMensuel $record) => $record->estCoherent()
                        ? 'Clôture conforme au calcul'
                        : 'Qté théorique : ' . number_format($record->qteClotureTheorique(), 0, ',', ' ')
                        . ' | Val. théorique : ' . number_format((float) $record->valClotureTheorique(), 2, ',', ' ')),

                TextColumn::make('qte_cloture_theorique')
                    ->label('Qté clôture théorique')
                    ->state(fn (StockMensuel $record) => $record->qteClotureTheorique())
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('val_cloture_theorique')
                    ->label('Val. clôture théorique')
                    ->state(fn (StockMensuel $record) => (float) $record->valClotureTheorique())
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(fn () => $this->getOwnerRecord()
                        ->stockMensuels()
                        ->reorder()
                        ->distinct()
                        ->orderByDesc('periode')
                        ->pluck('periode', 'periode')
                        ->all())
                    ->searchable()
                    ->multiple(),

                Filter::make('periode_affectation')
                    ->label('Période de l\'affectation')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('periode', $this->periodeAffectation())),

                Filter::make('incoherents')
                    ->label('Clôtures incohérentes')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereRaw(
                        '(qte_cloture <> (qte_ouverture + qte_in - qte_out + qte_stk))
                         OR (ABS(val_cloture - (val_ouverture + val_in - val_out + val_stk)) > 0.0001)'
                    )),
            ])
            ->paginated([12, 24, 50])
            ->defaultPaginationPageOption(12)
            ->emptyStateHeading('Aucun stock mensuel pour cet article et ce code stock');
    }

    /** Période (selon le format de `periode`) correspondant à la date comptable de l'affectation. */
    private function periodeAffectation(): ?string
    {
        return $this->getOwnerRecord()->date_comptable?->format(self::PERIODE_FORMAT);
    }
}
