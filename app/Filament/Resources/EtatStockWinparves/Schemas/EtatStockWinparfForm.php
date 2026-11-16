<?php

namespace App\Filament\Resources\EtatStockWinparves\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EtatStockWinparfForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('mouvement_brut_id')
                    ->relationship('mouvementBrut', 'id')
                    ->default(null),
                TextInput::make('code_stock')
                    ->default(null),
                TextInput::make('qte')
                    ->numeric()
                    ->default(null),
                TextInput::make('valeur')
                    ->numeric()
                    ->default(null),
                DatePicker::make('date_comptable'),
            ]);
    }
}
