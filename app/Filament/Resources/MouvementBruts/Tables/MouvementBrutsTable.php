<?php

namespace App\Filament\Resources\MouvementBruts\Tables;

use App\Models\MouvementBrut;
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

class MouvementBrutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['batch', 'article', 'typeMouvement', 'magasinOrigine', 'magasinDestination'])
                ->withCount('affectations')
                ->withSum('affectations', 'qte'))
            ->defaultSort('date_mvt', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->columns([
                TextColumn::make('date_mvt')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('in_out')
                    ->label('Sens')
                    ->badge()
                    ->color(fn (?string $state) => strtoupper((string) $state) === 'IN' ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('societe_src')
                    ->label('Société source')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parkod')
                    ->label('Article')
                    ->description(fn (MouvementBrut $record) => $record->article?->description)
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('typ_mvt')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->description(fn (MouvementBrut $record) => $record->typeMouvement?->libelle)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('mag_orig')
                    ->label('Mag. origine')
                    ->description(fn (MouvementBrut $record) => $record->magasinOrigine?->libelle)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('mag_dest')
                    ->label('Mag. destination')
                    ->description(fn (MouvementBrut $record) => $record->magasinDestination?->libelle)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('qte')
                    ->label('Qté')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 0, locale: 'fr')),

                TextColumn::make('montant')
                    ->label('Montant')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),

                TextColumn::make('paht')
                    ->label('PAHT')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('pamp')
                    ->label('PAMP')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('punet')
                    ->label('PU net')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('statut_affectation')
                    ->label('Affectation')
                    ->state(function (MouvementBrut $record): string {
                        if ((int) $record->affectations_count === 0) {
                            return 'Non affecté';
                        }

                        return abs((int) $record->affectations_sum_qte) === abs((int) $record->qte)
                            ? 'Affecté'
                            : 'Partiel';
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Affecté' => 'success',
                        'Partiel' => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (MouvementBrut $record) => $record->affectations_count . ' affectation(s)'),

                TextColumn::make('id_transac')
                    ->label('N° transaction')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('sequence_src')
                    ->label('Séquence source')
                    ->numeric(thousandsSeparator: '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('batch.id')
                    ->label('Lot import')
                    ->description(fn (MouvementBrut $record) => $record->batch?->created_at?->format('d/m/Y H:i'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('hash_ligne')
                    ->label('Hash ligne')
                    ->limit(12)
                    ->tooltip(fn (MouvementBrut $record) => $record->hash_ligne)
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Importé le')
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
                SelectFilter::make('in_out')
                    ->label('Sens')
                    ->options(fn () => MouvementBrut::query()
                        ->whereNotNull('in_out')->distinct()->orderBy('in_out')
                        ->pluck('in_out', 'in_out')->all()),

                SelectFilter::make('societe_src')
                    ->label('Société source')
                    ->options(fn () => MouvementBrut::query()
                        ->whereNotNull('societe_src')->distinct()->orderBy('societe_src')
                        ->pluck('societe_src', 'societe_src')->all())
                    ->multiple(),

                SelectFilter::make('typ_mvt')
                    ->label('Type de mouvement')
                    ->options(fn () => MouvementBrut::query()
                        ->whereNotNull('typ_mvt')->distinct()->orderBy('typ_mvt')
                        ->pluck('typ_mvt', 'typ_mvt')->all())
                    ->searchable()
                    ->multiple(),

                SelectFilter::make('affectation')
                    ->label('Affectation')
                    ->options([
                        'non' => 'Non affecté',
                        'oui' => 'Au moins une affectation',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'non' => $query->whereDoesntHave('affectations'),
                        'oui' => $query->whereHas('affectations'),
                        default => $query,
                    }),

                SelectFilter::make('batch_id')
                    ->label('Lot import')
                    ->relationship('batch', 'id')
                    ->searchable()
                    ->multiple(),

                Filter::make('date_mvt')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('du')->label('Du'),
                        DatePicker::make('au')->label('Au'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn (Builder $q, $d) => $q->whereDate('date_mvt', '>=', $d))
                        ->when($data['au'] ?? null, fn (Builder $q, $d) => $q->whereDate('date_mvt', '<=', $d))),
            ])
            ->recordActions([
                //ViewAction::make(),
                //EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun mouvement brut')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
