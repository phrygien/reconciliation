<?php

namespace App\Filament\Resources\Factures\RelationManagers;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\RegularisationStock;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Lignes de la facture';

    protected static ?string $recordTitleAttribute = 'parkod';

    private const STATUT_LABELS = [
        RegularisationStock::STATUT_A_FACTURER => 'À facturer',
        RegularisationStock::STATUT_FACTURE    => 'Facturé',
        RegularisationStock::STATUT_ANNULE     => 'Annulé',
    ];

    // lecture seule, y compris depuis la page de visualisation de la facture
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('article.marque'))
            ->defaultSort('parkod')
            ->striped()
            ->columns([
                TextColumn::make('parkod')
                    ->label('Article')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('semibold')
                    ->url(fn (RegularisationStock $record): ?string => $record->article
                        ? ArticleResource::getUrl('view', ['record' => $record->article])
                        : null),

                TextColumn::make('article.ean')
                    ->label('EAN')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('article.marque.nom')
                    ->label('Marque')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('article.description')
                    ->label('Description')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn ($state): ?string => is_string($state) && mb_strlen($state) > 50 ? $state : null)
                    ->searchable(),

                TextColumn::make('periode_origine')
                    ->label('Écart né en')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('code_stock_debiteur')
                    ->label('Débiteur')
                    ->badge()
                    ->color('info'),

                TextColumn::make('code_stock_crediteur')
                    ->label('Créditeur')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('qte')
                    ->label('Qté')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric()),

                TextColumn::make('pamp')
                    ->label('PAMP')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('valeur')
                    ->label('Montant')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2)),

                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::STATUT_LABELS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        RegularisationStock::STATUT_A_FACTURER => 'warning',
                        RegularisationStock::STATUT_FACTURE    => 'success',
                        RegularisationStock::STATUT_ANNULE     => 'danger',
                        default                                => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(self::STATUT_LABELS)
                    ->multiple(),

                SelectFilter::make('marque')
                    ->label('Marque')
                    ->relationship('article.marque', 'nom')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Aucune ligne');
    }
}
