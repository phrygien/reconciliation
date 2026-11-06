<?php

namespace App\Filament\Resources\MouvementAffectes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MouvementAffecteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mag_orig')
                    ->required(),
                TextInput::make('mag_dest')
                    ->required(),
                Select::make('mouvement_brut_id')
                    ->relationship('mouvementBrut', 'id')
                    ->required(),
                TextInput::make('code_stock')
                    ->required(),
                TextInput::make('qte')
                    ->required()
                    ->numeric(),
                TextInput::make('valeur')
                    ->required()
                    ->numeric(),
                TextInput::make('mode_affect')
                    ->required(),
                DatePicker::make('date_comptable'),
            ]);
    }
}
