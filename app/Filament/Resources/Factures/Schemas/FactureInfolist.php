<?php

namespace App\Filament\Resources\Factures\Schemas;

use App\Models\Facture;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Storage;

class FactureInfolist
{
    private const STATUT_LABELS = [
        Facture::STATUT_BROUILLON => 'Brouillon',
        Facture::STATUT_EMISE     => 'Émise',
        Facture::STATUT_ANNULEE   => 'Annulée',
    ];

    // 1 colonne sur mobile, 3 à partir de md
    private const GRID_3 = ['default' => 1, 'md' => 3];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([

                Section::make('Facture')
                    ->columns(self::GRID_3)
                    ->schema([
                        TextEntry::make('numero')
                            ->label('N° facture')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->copyable()
                            ->columnSpan(['md' => 2]),

                        TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => self::STATUT_LABELS[$state] ?? $state)
                            ->color(fn (?string $state) => match ($state) {
                                Facture::STATUT_BROUILLON => 'warning',
                                Facture::STATUT_EMISE     => 'success',
                                Facture::STATUT_ANNULEE   => 'danger',
                                default                   => 'gray',
                            }),

                        TextEntry::make('periode')
                            ->label('Période'),

                        TextEntry::make('facturer_a')
                            ->label('Facturée à')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('facture_par')
                            ->label('Émise par')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('date_facture')
                            ->label('Date facture')
                            ->date('d/m/Y')
                            ->placeholder('Non émise'),

                        TextEntry::make('lot')
                            ->label('Lot')
                            ->copyable(),
                    ]),

                Section::make('Totaux')
                    ->schema([
                        Grid::make(self::GRID_3)
                            ->schema([
                                TextEntry::make('nb_articles')
                                    ->label('Articles')
                                    ->numeric()
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold),

                                TextEntry::make('total_qte')
                                    ->label('Quantité totale')
                                    ->numeric()
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold),

                                TextEntry::make('total')
                                    ->label('Montant total')
                                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: ' ')
                                    ->suffix(' €')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),
                            ]),
                    ]),

                Section::make('Document et suivi')
                    ->collapsible()
                    ->columns(self::GRID_3)
                    ->schema([
                        TextEntry::make('pdf_path')
                            ->label('PDF')
                            ->placeholder('Pas encore généré')
                            ->formatStateUsing(fn (?string $state) => $state ? basename($state) : null)
                            ->url(
                                fn (Facture $record): ?string => filled($record->pdf_path) && Storage::exists($record->pdf_path)
                                    ? Storage::url($record->pdf_path)
                                    : null,
                                shouldOpenInNewTab: true,
                            ),

                        TextEntry::make('user.name')
                            ->label('Créée par')
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Créée le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('updated_at')
                            ->label('Modifiée le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
