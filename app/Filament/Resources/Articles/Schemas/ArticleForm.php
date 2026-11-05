<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('parkod')
                            ->label('Parkod')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus()
                            ->helperText('Code-barres ou référence unique de l\'article.'),

                        Select::make('marque_id')
                            ->label('Marque')
                            ->relationship('marque', 'nom')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('nom')
                                    ->label('Nom de la marque')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('marques', 'nom'),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Tarification et statut')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('paht')
                            ->label('Prix d\'achat HT')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0)
                            ->prefix('Rs'), // adapte : '€', 'MUR'...

                        Toggle::make('actif')
                            ->label('Article actif')
                            ->helperText('Un article inactif n\'apparaît plus dans les sélections.')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
