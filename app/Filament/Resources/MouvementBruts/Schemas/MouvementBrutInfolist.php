<?php

namespace App\Filament\Resources\MouvementBruts\Schemas;

use App\Models\MouvementBrut;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class MouvementBrutInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Mouvement brut')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Général')
                            ->icon('heroicon-o-information-circle')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('date_mvt')
                                    ->label('Date')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),

                                TextEntry::make('in_out')
                                    ->label('Sens')
                                    ->badge()
                                    ->color(fn (?string $state) => strtoupper((string) $state) === 'IN' ? 'success' : 'danger'),

                                TextEntry::make('societe_src')
                                    ->label('Société source')
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('typ_mvt')
                                    ->label('Type de mouvement')
                                    ->badge()
                                    ->color('gray')
                                    ->helperText(fn (MouvementBrut $record) => $record->typeMouvement?->libelle)
                                    ->placeholder('-'),

                                TextEntry::make('parkod')
                                    ->label('Article')
                                    ->weight('bold')
                                    ->copyable()
                                    ->helperText(fn (MouvementBrut $record) => $record->article?->description)
                                    ->placeholder('-'),

                                TextEntry::make('id_transac')
                                    ->label('N° transaction')
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('mag_orig')
                                    ->label('Magasin origine')
                                    ->helperText(fn (MouvementBrut $record) => $record->magasinOrigine?->libelle)
                                    ->placeholder('-'),

                                TextEntry::make('mag_dest')
                                    ->label('Magasin destination')
                                    ->helperText(fn (MouvementBrut $record) => $record->magasinDestination?->libelle)
                                    ->placeholder('-'),
                            ]),

                        Tab::make('Valeurs')
                            ->icon('heroicon-o-currency-euro')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('qte')
                                    ->label('Quantité')
                                    ->numeric(decimalPlaces: 0, locale: 'fr')
                                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                                    ->placeholder('-'),

                                TextEntry::make('montant')
                                    ->label('Montant')
                                    ->numeric(decimalPlaces: 2, locale: 'fr')
                                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                                    ->placeholder('-'),

                                TextEntry::make('paht')
                                    ->label('PAHT')
                                    ->numeric(decimalPlaces: 4, locale: 'fr')
                                    ->placeholder('-'),

                                TextEntry::make('pamp')
                                    ->label('PAMP')
                                    ->numeric(decimalPlaces: 4, locale: 'fr')
                                    ->placeholder('-'),

                                TextEntry::make('punet')
                                    ->label('PU net')
                                    ->numeric(decimalPlaces: 4, locale: 'fr')
                                    ->placeholder('-'),

                                TextEntry::make('montant_calcule')
                                    ->label('Qté x PU net')
                                    ->state(fn (MouvementBrut $record) => (float) $record->qte * (float) $record->punet)
                                    ->numeric(decimalPlaces: 2, locale: 'fr')
                                    ->helperText('Contrôle : à comparer au montant'),
                            ]),

                        Tab::make('Affectations')
                            ->icon('heroicon-o-link')
                            ->badge(fn (MouvementBrut $record) => $record->affectations()->count())
                            ->schema([
                                RepeatableEntry::make('affectations')
                                    ->hiddenLabel()
                                    ->columns(5)
                                    ->placeholder('Aucune affectation')
                                    ->schema([
                                        TextEntry::make('code_stock')->label('Code stock')->badge()->color('gray'),
                                        TextEntry::make('mode_affect')->label('Mode')->badge(),
                                        TextEntry::make('date_comptable')->label('Date comptable')->date('d/m/Y'),
                                        TextEntry::make('qte')->label('Qté')->numeric(decimalPlaces: 0, locale: 'fr'),
                                        TextEntry::make('valeur')->label('Valeur')->numeric(decimalPlaces: 2, locale: 'fr'),
                                    ]),
                            ]),

                        Tab::make('Import')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('batch.id')
                                    ->label('Lot import')
                                    ->placeholder('-'),

                                TextEntry::make('sequence_src')
                                    ->label('Séquence source')
                                    ->numeric(thousandsSeparator: '')
                                    ->placeholder('-'),

                                TextEntry::make('hash_ligne')
                                    ->label('Hash ligne')
                                    ->copyable()
                                    ->columnSpanFull(),

                                TextEntry::make('created_at')
                                    ->label('Importé le')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),

                                TextEntry::make('updated_at')
                                    ->label('Modifié le')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
