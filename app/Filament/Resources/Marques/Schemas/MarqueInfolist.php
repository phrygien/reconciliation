<?php

namespace App\Filament\Resources\Marques\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MarqueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('nom'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
