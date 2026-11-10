<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use App\Filament\Resources\StockMensuels\StockMensuelResource;
use App\Models\Periode;
use App\Models\Societe;
use App\Models\StockMensuel;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMensuelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMensuels';

    protected static ?string $title = 'Stock mensuel';

    protected static ?string $modelLabel = 'stock mensuel';

    protected static ?string $pluralModelLabel = 'stocks mensuels';

    /** Dépôt affiché dans le libellé du total. */
    private const DEPOT = '0000';

    /**
     * Périodes ouvertes pour lesquelles cet article n'a pas encore de stock mensuel.
     *
     * @return array<int, string>
     */
    private function periodesIndisponibles(): array
    {
        $existantes = $this->getOwnerRecord()
            ->stockMensuels()
            ->reorder()
            ->distinct()
            ->pluck('periode');

        return Periode::query()
            ->ouvertes()
            ->whereNotIn('periode', $existantes)
            ->orderByDesc('periode')
            ->pluck('periode')
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(function (): ?string {
                $manquantes = $this->periodesIndisponibles();

                return $manquantes === []
                    ? null
                    : 'Stock pas encore disponible pour : ' . implode(', ', $manquantes);
            })
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['periodeRef', 'societe']))
            ->groups([
                Group::make('periode')
                    ->label('Période')
                    ->getTitleFromRecordUsing(fn (StockMensuel $record): string => $record->periode
                        . ($record->periodeRef?->estOuverte() ? ' — ouverte' : ' — clôturée'))
                    ->orderQueryUsing(fn (Builder $query) => $query->orderByDesc('periode'))
                    ->collapsible(),
            ])
            ->defaultGroup('periode')
            ->defaultSort('code_stock')
            ->striped()
            ->columns([
                TextColumn::make('periode')
                    ->label('Période')
                    ->badge()
                    ->color(fn (StockMensuel $record) => $record->periodeRef?->estOuverte() ? 'success' : 'gray')
                    ->tooltip(fn (StockMensuel $record) => $record->periodeRef?->estOuverte()
                        ? 'Période ouverte'
                        : 'Période clôturée')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('societe.libelle')
                    ->label('Société')
                    ->description(fn (StockMensuel $record) => $record->code_stock)
                    ->searchable(['code_stock'])
                    ->sortable(['code_stock']),

                TextColumn::make('qte_ouverture')
                    ->label('Qté ouv.')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('qte_in')
                    ->label('Qté entrées')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->color('success')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('qte_out')
                    ->label('Qté sorties')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->color('danger')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('qte_stk')
                    ->label('Qté écart inv.')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : null))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('qte_cloture')
                    ->label('Qté clôture')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Stock actuel dépôt ' . self::DEPOT)
                            ->numeric(decimalPlaces: 0, locale: 'fr'),
                    ),

                TextColumn::make('val_cloture')
                    ->label('Val. clôture')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('pamp_cloture')
                    ->label('PAMP')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('coherent')
                    ->label('Cohérence')
                    ->state(fn (StockMensuel $record) => $record->estCoherent() ? 'OK' : 'Écart')
                    ->badge()
                    ->color(fn (string $state) => $state === 'OK' ? 'success' : 'danger')
                    ->tooltip(fn (StockMensuel $record) => $record->estCoherent()
                        ? 'Clôture conforme au calcul'
                        : 'Théorique : ' . $record->qteClotureTheorique() . ' u. / ' . $record->valClotureTheorique())
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(fn () => Periode::query()
                        ->orderByDesc('periode')
                        ->pluck('periode', 'periode')
                        ->all())
                    ->searchable()
                    ->multiple(),

                SelectFilter::make('code_stock')
                    ->label('Société')
                    ->options(fn () => Societe::query()
                        ->orderBy('libelle')
                        ->pluck('libelle', 'code_stock')
                        ->all())
                    ->multiple(),

                Filter::make('incoherents')
                    ->label('Clôtures incohérentes')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereRaw(
                        '(qte_cloture <> qte_ouverture + qte_in + qte_out + qte_stk
                          OR ROUND(val_cloture, 4) <> ROUND(val_ouverture + val_in + val_out + val_stk, 4))'
                    )),
            ])
            ->headerActions([
                // Lecture seule : pas de création depuis la fiche article
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (StockMensuel $record): string => StockMensuelResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Stock pas encore disponible')
            ->emptyStateDescription('Aucun stock mensuel n\'a encore été calculé pour cet article.')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
