<?php

namespace App\Filament\Resources\WinparfImportLogs;

use App\Filament\Resources\WinparfImportLogs\Pages\ManageWinparfImportLogs;
use App\Models\WinparfImportLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class WinparfImportLogResource extends Resource
{
    protected static ?string $model = WinparfImportLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Imports Winparf';

    protected static ?string $modelLabel = 'import Winparf';

    protected static ?string $pluralModelLabel = 'imports Winparf';

    /**
     * Disque et dossier où sont stockés les fichiers importés.
     * À adapter selon votre configuration.
     */
    protected static string $filesDisk = 'local';

    protected static string $filesDirectory = 'winparf';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Noms de fichiers du log (séparés par virgule, point-virgule ou retour à la ligne).
     *
     * @return array<int, string>
     */
    protected static function fileNames(WinparfImportLog $record): array
    {
        $names = preg_split('/[,;\r\n]+/', (string) $record->files) ?: [];

        return array_values(array_unique(array_filter(
            array_map(fn (string $name): string => basename(trim($name)), $names)
        )));
    }

    /**
     * Fichiers réellement présents sur le disque : [nom => chemin relatif].
     *
     * @return array<string, string>
     */
    protected static function existingFiles(WinparfImportLog $record): array
    {
        $disk = Storage::disk(static::$filesDisk);
        $found = [];

        foreach (static::fileNames($record) as $name) {
            $path = trim(static::$filesDirectory . '/' . $name, '/');

            if ($disk->exists($path)) {
                $found[$name] = $path;
            }
        }

        return $found;
    }

    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Télécharger')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (WinparfImportLog $record): bool => filled($record->files))
            ->action(function (WinparfImportLog $record) {
                $files = static::existingFiles($record);

                if (empty($files)) {
                    Notification::make()
                        ->title('Fichier(s) introuvable(s)')
                        ->body('Les fichiers de cet import ne sont plus disponibles sur le serveur.')
                        ->danger()
                        ->send();

                    return null;
                }

                $disk = Storage::disk(static::$filesDisk);

                // Un seul fichier : téléchargement direct
                if (count($files) === 1) {
                    $name = array_key_first($files);

                    return $disk->download($files[$name], $name);
                }

                // Plusieurs fichiers : archive ZIP
                $zipPath = tempnam(sys_get_temp_dir(), 'winparf_');
                $zip = new ZipArchive();

                if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
                    Notification::make()
                        ->title('Impossible de créer l\'archive ZIP')
                        ->danger()
                        ->send();

                    return null;
                }

                foreach ($files as $name => $path) {
                    $zip->addFile($disk->path($path), $name);
                }

                $zip->close();

                $zipName = 'winparf-import-' . $record->getKey() . '.zip';

                return response()->download($zipPath, $zipName)->deleteFileAfterSend();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('files')
                ->label('Fichiers')
                ->columnSpanFull(),
            TextEntry::make('started_at')
                ->label('Début')
                ->dateTime('d/m/Y H:i:s')
                ->columnSpanFull(),
            ViewEntry::make('live')
                ->hiddenLabel()
                ->view('filament.winparf-log-live')
                ->columnSpanFull(),
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
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
                static::downloadAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWinparfImportLogs::route('/'),
        ];
    }
}
