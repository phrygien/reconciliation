<?php

namespace App\Filament\Resources\MouvementBruts\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EtatsStockWinparfRelationManager extends RelationManager
{
    protected static string $relationship = 'etatsStockWinparf';

    protected static ?string $title = 'Etat stock';

    protected static ?string $modelLabel = 'Etat stock';

    protected static ?string $pluralModelLabel = 'Etat stock';

    protected static ?string $recordTitleAttribute = 'code_stock';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code_stock')
                ->label('Code stock')
                ->required()
                ->maxLength(255),
            TextInput::make('qte')
                ->label('Quantité')
                ->numeric()
                ->integer()
                ->required(),
            TextInput::make('valeur')
                ->label('Valeur')
                ->numeric()
                ->step(0.0001)
                ->required(),
            DatePicker::make('date_comptable')
                ->label('Date comptable')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code_stock')
            ->columns([
                TextColumn::make('stock_winparf_id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('code_stock')
                    ->label('Code stock')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qte')
                    ->label('Quantité')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('valeur')
                    ->label('Valeur')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('date_comptable')
                    ->label('Date comptable')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('date_comptable', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
