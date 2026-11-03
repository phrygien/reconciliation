<?php

namespace App\Filament\Resources\Societes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SocieteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code_stock')
                            ->required(),
                        TextInput::make('libelle')
                            ->required(),
                    ]),
            ]);
    }
}
