<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Article')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('parkod')
                            ->label('Code article')
                            ->weight('bold')
                            ->copyable()
                            ->copyMessage('Code copié'),

                        TextEntry::make('marque.nom')
                            ->label('Marque')
                            ->badge()
                            ->color('gray')
                            ->placeholder('-'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('paht')
                            ->label('PAHT')
                            ->numeric(decimalPlaces: 4, locale: 'fr')
                            ->placeholder('-'),

                        IconEntry::make('actif')
                            ->label('Actif')
                            ->boolean(),

                        TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
