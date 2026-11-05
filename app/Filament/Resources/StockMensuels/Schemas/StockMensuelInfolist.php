<?php

namespace App\Filament\Resources\StockMensuels\Schemas;

use App\Models\StockMensuel;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class StockMensuelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Stock mensuel')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Identification')
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                            ->schema([
                                TextEntry::make('periode')
                                    ->label('Période')
                                    ->badge()
                                    ->color(fn (StockMensuel $record) => $record->periodeRef?->estOuverte() ? 'success' : 'gray')
                                    ->formatStateUsing(fn (StockMensuel $record) => $record->periode
                                        . ($record->periodeRef?->estOuverte() ? ' (ouverte)' : ' (clôturée)')),

                                TextEntry::make('societe.libelle')
                                    ->label('Société')
                                    ->placeholder('-')
                                    ->helperText(fn (StockMensuel $record) => $record->code_stock),

                                TextEntry::make('parkod')
                                    ->label('Article')
                                    ->weight(FontWeight::Medium)
                                    ->copyable()
                                    ->helperText(fn (StockMensuel $record) => $record->article?->description),

                                TextEntry::make('article.marque.nom')
                                    ->label('Marque')
                                    ->placeholder('-')
                                    ->badge()
                                    ->color('gray'),
                            ]),

                        Tab::make('Ouverture')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('qte_ouverture')
                                    ->label('Quantité')
                                    ->numeric(decimalPlaces: 0, locale: 'fr'),
                                TextEntry::make('val_ouverture')
                                    ->label('Valeur')
                                    ->numeric(decimalPlaces: 2, locale: 'fr'),
                            ]),

                        Tab::make('Entrées')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('qte_in')
                                    ->label('Quantité')
                                    ->numeric(decimalPlaces: 0, locale: 'fr')
                                    ->color('success'),
                                TextEntry::make('val_in')
                                    ->label('Valeur')
                                    ->numeric(decimalPlaces: 2, locale: 'fr')
                                    ->color('success'),
                            ]),

                        Tab::make('Sorties')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('qte_out')
                                    ->label('Quantité')
                                    ->numeric(decimalPlaces: 0, locale: 'fr')
                                    ->color('danger'),
                                TextEntry::make('val_out')
                                    ->label('Valeur')
                                    ->numeric(decimalPlaces: 2, locale: 'fr')
                                    ->color('danger'),
                            ]),

                        Tab::make('Écart d\'inventaire')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('qte_stk')
                                    ->label('Quantité')
                                    ->numeric(decimalPlaces: 0, locale: 'fr')
                                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : null)),
                                TextEntry::make('val_stk')
                                    ->label('Valeur')
                                    ->numeric(decimalPlaces: 2, locale: 'fr')
                                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : null)),
                            ]),

                        Tab::make('Clôture')
                            ->columns(['default' => 1, 'md' => 4])
                            ->badge(fn (StockMensuel $record) => $record->estCoherent() ? null : 'Écart')
                            ->badgeColor('danger')
                            ->schema([
                                TextEntry::make('qte_cloture')
                                    ->label('Quantité')
                                    ->numeric(decimalPlaces: 0, locale: 'fr')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('val_cloture')
                                    ->label('Valeur')
                                    ->numeric(decimalPlaces: 2, locale: 'fr')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('pamp_cloture')
                                    ->label('PAMP')
                                    ->numeric(decimalPlaces: 4, locale: 'fr')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('coherence')
                                    ->label('Cohérence')
                                    ->state(fn (StockMensuel $record) => $record->estCoherent() ? 'Conforme' : 'Écart détecté')
                                    ->badge()
                                    ->color(fn (string $state) => $state === 'Conforme' ? 'success' : 'danger')
                                    ->helperText(fn (StockMensuel $record) => $record->estCoherent()
                                        ? 'Clôture égale à ouverture + entrées + sorties + écart.'
                                        : 'Théorique : '
                                        . number_format($record->qteClotureTheorique(), 0, ',', ' ') . ' u. / '
                                        . number_format((float) $record->valClotureTheorique(), 2, ',', ' ')),
                            ]),

                        Tab::make('Historique')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Créé le')
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
