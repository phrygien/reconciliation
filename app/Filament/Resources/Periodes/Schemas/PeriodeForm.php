<?php

namespace App\Filament\Resources\Periodes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PeriodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('statut')
                    ->required()
                    ->default('OUVERTE'),
                DateTimePicker::make('cloture_le'),
            ]);
    }
}
