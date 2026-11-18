<?php

namespace App\Filament\Resources\Factures\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FactureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('numero')
                    ->required(),
                TextInput::make('lot')
                    ->required(),
                TextInput::make('periode')
                    ->required(),
                TextInput::make('facturer_a')
                    ->required(),
                TextInput::make('facture_par')
                    ->required(),
                TextInput::make('nb_articles')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_qte')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('statut')
                    ->required()
                    ->default('BROUILLON'),
                DatePicker::make('date_facture'),
                TextInput::make('pdf_path')
                    ->default(null),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->default(null),
            ]);
    }
}
