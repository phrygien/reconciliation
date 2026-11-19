<?php

namespace App\Filament\Resources\Factures\Tables;

use App\Models\Facture;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class FacturesTable
{
    private const STATUT_LABELS = [
        Facture::STATUT_BROUILLON => 'Brouillon',
        Facture::STATUT_EMISE     => 'Émise',
        Facture::STATUT_ANNULEE   => 'Annulée',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('numero')
                    ->label('N° facture')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->copyable(),

                TextColumn::make('periode')
                    ->label('Période')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('facturer_a')
                    ->label('Facturée à')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('facture_par')
                    ->label('Émise par')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('nb_articles')
                    ->label('Articles')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('total_qte')
                    ->label('Qté totale')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Montant')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->numeric(decimalPlaces: 2)
                    ),

                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::STATUT_LABELS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Facture::STATUT_BROUILLON => 'warning',
                        Facture::STATUT_EMISE     => 'success',
                        Facture::STATUT_ANNULEE   => 'danger',
                        default                   => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('date_facture')
                    ->label('Date facture')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('lot')
                    ->label('Lot')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('Créée par')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Modifiée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(self::STATUT_LABELS)
                    ->multiple(),

                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(fn () => Facture::query()
                        ->select('periode')
                        ->distinct()
                        ->orderByDesc('periode')
                        ->pluck('periode', 'periode')
                        ->all()),

                SelectFilter::make('facturer_a')
                    ->label('Facturée à')
                    ->options(['ADV' => 'ADV', 'COSMA' => 'COSMA']),

                SelectFilter::make('facture_par')
                    ->label('Émise par')
                    ->options(['ADV' => 'ADV', 'COSMA' => 'COSMA']),

                SelectFilter::make('lot')
                    ->label('Lot')
                    ->searchable()
                    ->options(fn () => Facture::query()
                        ->select('lot')
                        ->distinct()
                        ->orderByDesc('lot')
                        ->limit(50)
                        ->pluck('lot', 'lot')
                        ->all()),

                Filter::make('date_facture')
                    ->label('Date facture')
                    ->schema([
                        DatePicker::make('du')->label('Du'),
                        DatePicker::make('au')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('date_facture', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('date_facture', '<=', $d))),
            ])
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),

                // modifiable seulement tant que la facture est en brouillon
                /*EditAction::make()
                    ->visible(fn (Facture $record): bool => $record->statut === Facture::STATUT_BROUILLON),*/

                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Facture $record): bool => filled($record->pdf_path))
                    ->action(fn (Facture $record) => Storage::download($record->pdf_path)),
            ])
            // Pas de suppression en masse : une facture est liée à des mouvements de stock.
            // Une facture sans ligne active passe automatiquement en « Annulée ».
            ->toolbarActions([])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Aucune facture');
    }
}
