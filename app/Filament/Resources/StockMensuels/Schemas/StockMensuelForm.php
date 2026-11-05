<?php

namespace App\Filament\Resources\StockMensuels\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockMensuelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('periode')
                    ->required(),
                TextInput::make('code_stock')
                    ->required(),
                TextInput::make('parkod')
                    ->required(),
                TextInput::make('qte_ouverture')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('val_ouverture')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('qte_in')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('val_in')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('qte_out')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('val_out')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('qte_stk')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('val_stk')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('qte_cloture')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('val_cloture')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('pamp_cloture')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
