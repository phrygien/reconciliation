<?php

namespace App\Filament\Resources\StockMensuels\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StockMensuelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('periode'),
                TextEntry::make('code_stock'),
                TextEntry::make('parkod'),
                TextEntry::make('qte_ouverture')
                    ->numeric(),
                TextEntry::make('val_ouverture')
                    ->numeric(),
                TextEntry::make('qte_in')
                    ->numeric(),
                TextEntry::make('val_in')
                    ->numeric(),
                TextEntry::make('qte_out')
                    ->numeric(),
                TextEntry::make('val_out')
                    ->numeric(),
                TextEntry::make('qte_stk')
                    ->numeric(),
                TextEntry::make('val_stk')
                    ->numeric(),
                TextEntry::make('qte_cloture')
                    ->numeric(),
                TextEntry::make('val_cloture')
                    ->numeric(),
                TextEntry::make('pamp_cloture')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
