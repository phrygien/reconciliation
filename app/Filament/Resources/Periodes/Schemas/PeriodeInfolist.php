<?php

namespace App\Filament\Resources\Periodes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PeriodeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('periode'),
                TextEntry::make('statut'),
                TextEntry::make('cloture_le')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
