<?php

namespace App\Filament\Resources\WinparfImportLogs;

use App\Filament\Resources\WinparfImportLogs\Pages\ManageWinparfImportLogs;
use App\Models\WinparfImportLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WinparfImportLogResource extends Resource
{
    protected static ?string $model = WinparfImportLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Imports Winparf';

    protected static ?string $modelLabel = 'import Winparf';

    protected static ?string $pluralModelLabel = 'imports Winparf';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('files')
                ->label('Fichiers')
                ->columnSpanFull(),
            TextEntry::make('status')
                ->label('Statut')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'running' => 'warning',
                    'success' => 'success',
                    default => 'danger',
                }),
            TextEntry::make('exit_code')
                ->label('Code retour')
                ->placeholder('-'),
            TextEntry::make('started_at')
                ->label('Début')
                ->dateTime('d/m/Y H:i:s'),
            TextEntry::make('finished_at')
                ->label('Fin')
                ->dateTime('d/m/Y H:i:s')
                ->placeholder('-'),
            TextEntry::make('output')
                ->label('Sortie de app:import-winparf-file')
                ->placeholder('Aucune sortie')
                ->columnSpanFull()
                ->extraAttributes([
                    'style' => 'white-space: pre-wrap; font-family: monospace; font-size: 12px; max-height: 60vh; overflow: auto;',
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('2s')
            ->columns([
                TextColumn::make('started_at')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('files')
                    ->label('Fichiers')
                    ->limit(60),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'running' => 'warning',
                        'success' => 'success',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'running' => 'En cours',
                        'success' => 'Terminé',
                        default => 'Échec',
                    }),
                TextColumn::make('duration')
                    ->label('Durée')
                    ->getStateUsing(fn (WinparfImportLog $record): string => $record->started_at
                        ? (int) $record->started_at->diffInSeconds($record->finished_at ?? now(), true).' s'
                        : '-'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Voir le log')
                    ->modalWidth('5xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWinparfImportLogs::route('/'),
        ];
    }
}
