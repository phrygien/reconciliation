<?php

namespace App\Filament\Resources\Factures\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FactureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('numero'),
                TextEntry::make('lot'),
                TextEntry::make('periode'),
                TextEntry::make('facturer_a'),
                TextEntry::make('facture_par'),
                TextEntry::make('nb_articles')
                    ->numeric(),
                TextEntry::make('total_qte')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('statut'),
                TextEntry::make('date_facture')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('pdf_path')
                    ->placeholder('-'),
                TextEntry::make('user.name')
                    ->label('User')
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
