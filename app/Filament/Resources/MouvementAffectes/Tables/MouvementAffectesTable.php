<?php

namespace App\Filament\Resources\MouvementAffectes\Tables;

use App\Models\MouvementAffecte;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MouvementAffectesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['societe', 'mouvementBrut.article']))
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('(qte < 0) asc')   // IN d'abord, puis OUT
                ->orderByDesc('date_comptable')
                ->orderByDesc('id'))
            ->striped()
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('sens')
                    ->label('Sens')
                    ->state(fn (MouvementAffecte $record) => $record->sens)
                    ->badge()
                    ->color(fn (string $state) => $state === 'IN' ? 'success' : 'danger'),

                TextColumn::make('code_stock')
                    ->label('Code stock')
                    ->badge()
                    ->color('gray')
                    ->description(fn (MouvementAffecte $record) => $record->societe?->libelle)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('date_comptable')
                    ->label('Date comptable')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('mouvementBrut.parkod')
                    ->label('Article')
                    ->description(fn (MouvementAffecte $record) => $record->mouvementBrut?->article?->description)
                    ->weight('medium')
                    ->copyable()
                    ->searchable(isIndividual: true),

                TextColumn::make('mouvementBrut.typ_mvt')
                    ->label('Type mvt')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('mag_orig')
                    ->label('Mag. origine')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('mag_dest')
                    ->label('Mag. destination')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('qte')
                    ->label('Qté')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),

                TextColumn::make('valeur')
                    ->label('Valeur')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),

                TextColumn::make('mode_affect')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        MouvementAffecte::MODE_AUTO => 'success',
                        MouvementAffecte::MODE_REPARTI => 'info',
                        MouvementAffecte::MODE_MANUEL => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        MouvementAffecte::MODE_AUTO => 'Automatique',
                        MouvementAffecte::MODE_REPARTI => 'Réparti',
                        MouvementAffecte::MODE_MANUEL => 'Manuel',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('mouvement_brut_id')
                    ->label('N° brut')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sens')
                    ->label('Sens')
                    ->options(['IN' => 'Entrées (IN)', 'OUT' => 'Sorties (OUT)'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'IN' => $query->where('qte', '>=', 0),
                        'OUT' => $query->where('qte', '<', 0),
                        default => $query,
                    }),

                SelectFilter::make('mode_affect')
                    ->label('Mode')
                    ->options([
                        MouvementAffecte::MODE_AUTO => 'Automatique',
                        MouvementAffecte::MODE_REPARTI => 'Réparti',
                        MouvementAffecte::MODE_MANUEL => 'Manuel',
                    ])
                    ->multiple(),

                Filter::make('date_comptable')
                    ->label('Date comptable')
                    ->schema([
                        DatePicker::make('du')->label('Du'),
                        DatePicker::make('au')->label('Au'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn (Builder $q, $d) => $q->whereDate('date_comptable', '>=', $d))
                        ->when($data['au'] ?? null, fn (Builder $q, $d) => $q->whereDate('date_comptable', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun mouvement affecté')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
