<?php

namespace App\Filament\Resources\MouvementAffectes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MouvementAffecteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('mag_orig'),
                TextEntry::make('mag_dest'),
                TextEntry::make('mouvementBrut.id')
                    ->label('Mouvement brut'),
                TextEntry::make('code_stock'),
                TextEntry::make('qte')
                    ->numeric(),
                TextEntry::make('valeur')
                    ->numeric(),
                TextEntry::make('mode_affect'),
                TextEntry::make('date_comptable')
                    ->date()
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
