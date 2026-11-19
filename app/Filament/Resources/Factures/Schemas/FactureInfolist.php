<?php

namespace App\Filament\Resources\Factures\Schemas;

use App\Models\Facture;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
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

    private const GRID_2 = ['default' => 1, 'md' => 2];
    private const GRID_3 = ['default' => 1, 'md' => 3];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([

                Section::make()
                    ->schema([

                        // ── En-tête : titre + numéro | statut ─────────────
                        Flex::make([
                            Group::make([
                                TextEntry::make('numero')
                                    ->hiddenLabel()
                                    ->prefix('FACTURE ')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),

                                TextEntry::make('periode')
                                    ->hiddenLabel()
                                    ->prefix('Période : ')
                                    ->color('gray'),
                            ]),

                            TextEntry::make('statut')
                                ->hiddenLabel()
                                ->badge()
                                ->size(TextSize::Large)
                                ->formatStateUsing(fn (?string $state) => self::STATUT_LABELS[$state] ?? $state)
                                ->color(fn (?string $state) => match ($state) {
                                    Facture::STATUT_BROUILLON => 'warning',
                                    Facture::STATUT_EMISE     => 'success',
                                    Facture::STATUT_ANNULEE   => 'danger',
                                    default                   => 'gray',
                                })
                                ->grow(false),
                        ])->from('md'),

                        // ── Émetteur / Client ────────────────────────────
                        Grid::make(self::GRID_2)
                            ->extraAttributes(['class' => 'border-t border-gray-200 pt-4 dark:border-white/10'])
                            ->schema([
                                TextEntry::make('facture_par')
                                    ->label('Émise par')
                                    ->weight(FontWeight::SemiBold)
                                    ->size(TextSize::Large),

                                TextEntry::make('facturer_a')
                                    ->label('Facturée à')
                                    ->weight(FontWeight::SemiBold)
                                    ->size(TextSize::Large),
                            ]),

                        // ── Métadonnées ──────────────────────────────────
                        Grid::make(self::GRID_3)
                            ->extraAttributes(['class' => 'border-t border-gray-200 pt-4 dark:border-white/10'])
                            ->schema([
                                TextEntry::make('date_facture')
                                    ->label('Date de facture')
                                    ->date('d/m/Y')
                                    ->placeholder('Non émise'),

                                TextEntry::make('lot')
                                    ->label('Lot')
                                    ->copyable(),

                                TextEntry::make('nb_articles')
                                    ->label('Nombre d’articles')
                                    ->numeric(),
                            ]),

                        // ── Totaux (alignés à droite) ────────────────────
                        Grid::make(self::GRID_3)
                            ->extraAttributes(['class' => 'border-t border-gray-200 pt-4 dark:border-white/10'])
                            ->schema([
                                Group::make([
                                    TextEntry::make('total_qte')
                                        ->label('Quantité totale')
                                        ->numeric()
                                        ->inlineLabel(),

                                    TextEntry::make('total')
                                        ->label('Total')
                                        ->inlineLabel()
                                        ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: ' ')
                                        ->suffix(' €')
                                        ->size(TextSize::Large)
                                        ->weight(FontWeight::Bold)
                                        ->color('primary'),
                                ])->columnStart(['md' => 3]),
                            ]),
                    ]),

                // ── Pied : document et suivi ─────────────────────────────
                Section::make('Document et suivi')
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->columns(self::GRID_2)
                    ->schema([
                        TextEntry::make('pdf_path')
                            ->label('PDF')
                            ->placeholder('Pas encore généré')
                            ->formatStateUsing(fn (?string $state) => $state ? basename($state) : null)
                            ->icon('heroicon-o-document-arrow-down')
                            ->url(
                                fn (Facture $record): ?string => filled($record->pdf_path) && Storage::exists($record->pdf_path)
                                    ? Storage::url($record->pdf_path)
                                    : null,
                                shouldOpenInNewTab: true,
                            )
                            ->columnSpanFull(),

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
