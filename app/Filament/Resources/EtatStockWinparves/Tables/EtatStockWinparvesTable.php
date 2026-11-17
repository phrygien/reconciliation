<?php

namespace App\Filament\Resources\EtatStockWinparves\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EtatStockWinparvesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('mouvementBrut.article'))
            ->columns([
                TextColumn::make('mouvementBrut.parkod')
                    ->label('Parkod')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mouvementBrut.ean')
                    ->label('EAN')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mouvementBrut.article.description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('code_stock')
                    ->searchable(),
                TextColumn::make('qte')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('valeur')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('date_comptable')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //DeleteBulkAction::make(),
                ]),
            ]);
    }
}
