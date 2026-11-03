<?php

namespace App\Filament\Resources\Societes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SocieteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code_stock'),
                TextEntry::make('libelle'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
