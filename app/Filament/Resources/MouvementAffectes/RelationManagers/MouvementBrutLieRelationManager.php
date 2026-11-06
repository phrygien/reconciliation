<?php

namespace App\Filament\Resources\MouvementAffectes\RelationManagers;

use App\Filament\Resources\MouvementBruts\MouvementBrutResource;
use App\Models\MouvementBrut;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MouvementBrutLieRelationManager extends RelationManager
{
    protected static string $relationship = 'mouvementBrutLie';

    protected static ?string $title = 'Mouvement brut';

    protected static ?string $modelLabel = 'mouvement brut';

    protected static ?string $pluralModelLabel = 'mouvement brut';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['article', 'typeMouvement', 'magasinOrigine', 'magasinDestination', 'batch'])
                ->withCount('affectations')
                ->withSum('affectations', 'qte')
                ->withSum('affectations', 'valeur'))
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->sortable(),

                TextColumn::make('date_mvt')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('in_out')
                    ->label('Sens')
                    ->badge()
                    ->color(fn (?string $state) => strtoupper((string) $state) === 'IN' ? 'success' : 'danger'),

                TextColumn::make('societe_src')
                    ->label('Société source')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('parkod')
                    ->label('Article')
                    ->description(fn (MouvementBrut $record) => $record->article?->description)
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('article.marque.nom')
                    ->label('Marque')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('typ_mvt')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->description(fn (MouvementBrut $record) => $record->typeMouvement?->libelle),

                TextColumn::make('mag_orig')
                    ->label('Mag. origine')
                    ->description(fn (MouvementBrut $record) => $record->magasinOrigine?->libelle),

                TextColumn::make('mag_dest')
                    ->label('Mag. destination')
                    ->description(fn (MouvementBrut $record) => $record->magasinDestination?->libelle),

                TextColumn::make('qte')
                    ->label('Qté')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('montant')
                    ->label('Montant')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('paht')
                    ->label('PAHT')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('pamp')
                    ->label('PAMP')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('punet')
                    ->label('PU net')
                    ->numeric(decimalPlaces: 4, locale: 'fr')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('montant_calcule')
                    ->label('Qté x PU net')
                    ->state(fn (MouvementBrut $record) => (float) $record->qte * (float) $record->punet)
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->alignEnd()
                    ->tooltip('Contrôle : à comparer au montant')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->tooltip(fn (MouvementBrut $record) => $record->affectations_count . ' affectation(s)')
                    ->toggleable(),

                TextColumn::make('affectations_sum_qte')
                    ->label('Qté affectée')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->placeholder('0')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('affectations_sum_valeur')
                    ->label('Valeur affectée')
                    ->numeric(decimalPlaces: 2, locale: 'fr')
                    ->placeholder('0,00')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('reste_a_affecter')
                    ->label('Reste à affecter')
                    ->state(fn (MouvementBrut $record) => abs((int) $record->qte) - abs((int) $record->affectations_sum_qte))
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : null)
                    ->toggleable(),

                TextColumn::make('id_transac')
                    ->label('N° transaction')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('sequence_src')
                    ->label('Séquence source')
                    ->numeric(thousandsSeparator: '')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('batch.id')
                    ->label('Lot import')
                    ->description(fn (MouvementBrut $record) => $record->batch?->created_at?->format('d/m/Y H:i'))
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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ouvrir')
                    ->url(fn (MouvementBrut $record) => MouvementBrutResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false)
            ->emptyStateHeading('Aucun mouvement brut lié');
    }
}
