<?php

namespace App\Filament\Resources\Articles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('marque'))
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('parkod')
                    ->label('Parkod')
                    ->searchable(isIndividual: true)
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Parkod copié')
                    ->weight('medium'),

                TextColumn::make('marque.nom')
                    ->label('Marque')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('ean')
                    ->label('EAN')
                    ->searchable(isIndividual: true)
                    ->sortable()
                    ->copyable()
                    ->copyMessage('EAN copié')
                    ->weight('medium'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(isIndividual: true)
                    ->limit(50)
                    ->tooltip(fn (TextColumn $column): ?string =>
                    strlen($column->getState()) > 50 ? $column->getState() : null
                    )
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('paht')
                    ->label('Prix d\'achat HT')
                    ->money('EUR') // adapte la devise (EUR, EUR...)
                    ->sortable()
                    ->alignEnd(),

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
                SelectFilter::make('marque_id')
                    ->label('Marque')
                    ->relationship('marque', 'nom')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun article')
            ->emptyStateDescription('Créez votre premier article pour commencer.')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
