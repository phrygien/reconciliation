<?php

namespace App\Filament\Resources\Societes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SocieteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code_stock')
                    ->required(),
                TextInput::make('libelle')
                    ->required(),
            ]);
    }
}
