<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use App\Models\MouvementBrut;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MouvementBrutsRelationManager extends RelationManager
{
    protected static string $relationship = 'mouvementBruts';

    protected static ?string $title = 'Mouvements bruts';

    protected static ?string $modelLabel = 'mouvement brut';

    protected static ?string $pluralModelLabel = 'mouvements bruts';

    /** Statut d'affectation déduit des affectations liées. */
    private static function statut(MouvementBrut $record): string
    {
        $nb = (int) ($record->affectations_count ?? 0);

        if ($nb === 0) {
            return 'Non affecté';
        }

        return abs((int) $record->affectations_sum_qte) === abs((int) $record->qte)
            ? 'Affecté'
            : 'Partiel';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['magasinOrigine', 'magasinDestination', 'typeMouvement', 'batch'])
                ->withCount('affectations')
                ->withSum('affectations', 'qte')
                ->withSum('affectations', 'valeur'))
            ->defaultSort('date_mvt', 'desc')
            ->striped()
            ->persistFiltersInSession()
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

                TextColumn::make('typ_mvt')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->description(fn (MouvementBrut $record) => $record->typeMouvement?->libelle) // adapte le champ
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

                TextColumn::make('paht')
                    ->label('PAHT')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('pamp')
                    ->label('PAMP')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('punet')
                    ->label('PU net')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('montant')
                    ->label('Montant')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),

                TextColumn::make('statut_affectation')
                    ->label('Affectation')
                    ->state(fn (MouvementBrut $record) => self::statut($record))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Affecté' => 'success',
                        'Partiel' => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (MouvementBrut $record) => $record->affectations_count . ' affectation(s)'),

                TextColumn::make('affectations_sum_qte')
                    ->label('Qté affectée')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->placeholder('0')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('affectations_sum_valeur')
                    ->label('Valeur affectée')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->placeholder('0,00')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2, locale: 'fr')),

                TextColumn::make('reste_a_affecter')
                    ->label('Reste à affecter')
                    ->state(fn (MouvementBrut $record) => abs((int) $record->qte) - abs((int) $record->affectations_sum_qte))
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : null),

                TextColumn::make('montant_calcule')
                    ->label('Qté x PU net')
                    ->state(fn (MouvementBrut $record) => (float) $record->qte * (float) $record->punet)
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->tooltip('Contrôle : à comparer au montant')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('id_transac')
                    ->label('N° transaction')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('sequence_src')
                    ->label('Séquence source')
                    ->numeric(thousandsSeparator: '')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('batch_id')
                    ->label('Lot import')
                    ->description(fn (MouvementBrut $record) => $record->batch?->created_at?->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('hash_ligne')
                    ->label('Hash ligne')
                    ->limit(12)
                    ->tooltip(fn (MouvementBrut $record) => $record->hash_ligne)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Importé le')
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
                    ->options(fn () => MouvementBrut::query()
                        ->whereNotNull('batch_id')->distinct()->orderByDesc('batch_id')
                        ->pluck('batch_id', 'batch_id')->all())
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
                ViewAction::make()
                    ->label('Détail')
                    ->modalHeading(fn (MouvementBrut $record) => 'Mouvement brut n° ' . $record->getKey())
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('date_mvt')->label('Date')->dateTime('d/m/Y H:i'),
                            TextEntry::make('in_out')->label('Sens')->badge(),
                            TextEntry::make('societe_src')->label('Société source'),
                            TextEntry::make('typ_mvt')->label('Type de mouvement'),
                            TextEntry::make('mag_orig')->label('Magasin origine')
                                ->helperText(fn (MouvementBrut $record) => $record->magasinOrigine?->libelle),
                            TextEntry::make('mag_dest')->label('Magasin destination')
                                ->helperText(fn (MouvementBrut $record) => $record->magasinDestination?->libelle),
                            TextEntry::make('qte')->label('Quantité')->numeric(decimalPlaces: 0, locale: 'fr'),
                            TextEntry::make('montant')->label('Montant')->numeric(decimalPlaces: 2, locale: 'fr'),
                            TextEntry::make('paht')->label('PAHT')->numeric(decimalPlaces: 4, locale: 'fr'),
                            TextEntry::make('pamp')->label('PAMP')->numeric(decimalPlaces: 4, locale: 'fr'),
                            TextEntry::make('punet')->label('PU net')->numeric(decimalPlaces: 4, locale: 'fr'),
                            TextEntry::make('id_transac')->label('N° transaction')->copyable(),
                            TextEntry::make('sequence_src')->label('Séquence source')->numeric(thousandsSeparator: ''),
                            TextEntry::make('batch_id')->label('Lot import'),
                            TextEntry::make('hash_ligne')->label('Hash ligne')->copyable()->columnSpanFull(),

                            RepeatableEntry::make('affectations')
                                ->label('Affectations')
                                ->columnSpanFull()
                                ->columns(5)
                                ->schema([
                                    TextEntry::make('code_stock')->label('Code stock')->badge()->color('gray'),
                                    TextEntry::make('mode_affect')->label('Mode')->badge(),
                                    TextEntry::make('date_comptable')->label('Date comptable')->date('d/m/Y'),
                                    TextEntry::make('qte')->label('Qté')->numeric(decimalPlaces: 0, locale: 'fr'),
                                    TextEntry::make('valeur')->label('Valeur')->numeric(decimalPlaces: 2, locale: 'fr'),
                                ])
                                ->placeholder('Aucune affectation'),
                        ]),
                    ]),
            ])
            ->emptyStateHeading('Aucun mouvement brut pour cet article')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
