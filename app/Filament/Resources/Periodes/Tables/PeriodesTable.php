<?php

namespace App\Filament\Resources\Periodes\Tables;

use App\Models\Periode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PeriodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('periode', 'desc')
            ->columns([
                TextColumn::make('periode')
                    ->label('Période')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => $state === Periode::STATUT_OUVERTE ? 'success' : 'gray')
                    ->searchable(),
                TextColumn::make('cloture_le')
                    ->label('Clôturée le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(array_combine(Periode::STATUTS, Periode::STATUTS)),
            ])
            ->recordActions([
                // Action::make('cloturer')
                //     ->label('Clôturer')
                //     ->icon('heroicon-o-lock-closed')
                //     ->color('danger')
                //     ->visible(fn (Periode $record): bool => $record->estOuverte())
                //     ->requiresConfirmation()
                //     ->modalHeading(fn (Periode $record): string => "Clôturer la période {$record->periode} ?")
                //     ->modalDescription('La période passera au statut CLOTURE. Vous pourrez la rouvrir si nécessaire.')
                //     ->modalSubmitActionLabel('Clôturer')
                //     ->action(function (Periode $record): void {
                //         $record->cloturer();

                //         Notification::make()
                //             ->title("Période {$record->periode} clôturée")
                //             ->success()
                //             ->send();
                //     }),

                // Action::make('rouvrir')
                //     ->label('Rouvrir')
                //     ->icon('heroicon-o-lock-open')
                //     ->color('warning')
                //     ->visible(fn (Periode $record): bool => $record->estCloturee())
                //     ->requiresConfirmation()
                //     ->modalHeading(fn (Periode $record): string => "Rouvrir la période {$record->periode} ?")
                //     ->modalSubmitActionLabel('Rouvrir')
                //     ->action(function (Periode $record): void {
                //         $record->rouvrir();

                //         Notification::make()
                //             ->title("Période {$record->periode} rouverte")
                //             ->success()
                //             ->send();
                //     }),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
