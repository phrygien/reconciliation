<?php

namespace App\Filament\Resources\MouvementBruts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MouvementBrutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('batch_id')
                    ->relationship('batch', 'id')
                    ->required(),
                TextInput::make('sequence_src')
                    ->numeric()
                    ->default(null),
                TextInput::make('in_out')
                    ->required(),
                TextInput::make('societe_src')
                    ->required(),
                DateTimePicker::make('date_mvt'),
                TextInput::make('typ_mvt')
                    ->default(null),
                TextInput::make('mag_orig')
                    ->default(null),
                TextInput::make('mag_dest')
                    ->default(null),
                TextInput::make('parkod')
                    ->default(null),
                TextInput::make('qte')
                    ->numeric()
                    ->default(null),
                TextInput::make('montant')
                    ->numeric()
                    ->default(null),
                TextInput::make('paht')
                    ->numeric()
                    ->default(null),
                TextInput::make('pamp')
                    ->numeric()
                    ->default(null),
                TextInput::make('punet')
                    ->numeric()
                    ->default(null),
                TextInput::make('id_transac')
                    ->default(null),
                TextInput::make('hash_ligne')
                    ->required(),
            ]);
    }
}
