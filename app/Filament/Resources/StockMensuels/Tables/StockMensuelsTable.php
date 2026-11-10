<?php

namespace App\Filament\Resources\StockMensuels\Tables;

use App\Filament\Exports\StockMensuelExporter;
use App\Models\Article;
use App\Models\Periode;
use App\Models\Societe;
use App\Models\StockMensuel;
use Filament\Actions\ExportAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMensuelsTable
{
    private static function couleur(mixed $state): string
    {
        return match (true) {
            $state === null => 'gray',
            $state < 0 => 'danger',
            $state == 0 => 'gray',
            default => 'success',
        };
    }

    private static function colonneQte(
        string $alias,
        string $label,
        bool $gras = false,
        string $labelTotal = 'Total',
    ): TextColumn {
        return TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 0, locale: 'fr')
            ->placeholder('0')
            ->color(fn ($state) => self::couleur($state))
            ->weight($gras ? 'bold' : null)
            ->alignEnd()
            ->sortable()
            ->summarize(Sum::make()->label($labelTotal)->numeric(decimalPlaces: 0, locale: 'fr'));
    }

    private static function colonneVal(
        string $alias,
        string $label,
        bool $cache = true,
        string $labelTotal = 'Total',
    ): TextColumn {
        return TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 2, locale: 'fr')
            ->placeholder('0,00')
            ->alignEnd()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: $cache)
            ->summarize(Sum::make()->label($labelTotal)->numeric(decimalPlaces: 2, locale: 'fr'));
    }

    public static function configure(Table $table): Table
    {
        /** @var array<string, string> $societes code_stock => libellé */
        $societes = Societe::query()
            ->orderBy('code_stock')
            ->pluck('libelle', 'code_stock')
            ->all();

        $groupesSocietes = [];
        $i = 0;
        foreach ($societes as $code => $libelle) {
            $groupesSocietes[] = ColumnGroup::make(($libelle ?: $code) . " ({$code})", [
                self::colonneQte("s{$i}_qte", 'Qté', gras: true),
                self::colonneVal("s{$i}_val", 'Valeur'),
            ])->alignCenter();
            $i++;
        }

        $labelTotal = 'Total stock dépôt centrale';

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($societes) {
                $selects = ['MIN(id) as id', 'periode', 'parkod'];
                $bindings = [];

                $i = 0;
                foreach (array_keys($societes) as $code) {
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN qte_cloture END), 0) as s{$i}_qte";
                    $bindings[] = $code;
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN val_cloture END), 0) as s{$i}_val";
                    $bindings[] = $code;
                    $i++;
                }

                $selects[] = 'COALESCE(SUM(qte_cloture), 0) as t_qte';
                $selects[] = 'COALESCE(SUM(val_cloture), 0) as t_val';

                return $query
                    ->with(['article', 'periodeRef'])
                    ->selectRaw(implode(', ', $selects), $bindings)
                    ->groupBy('periode', 'parkod');
            })
            ->groups([
                Group::make('periode')
                    ->label('Période')
                    ->getTitleFromRecordUsing(fn ($record): string => $record->periode
                        . ($record->periodeRef?->estOuverte() ? ' — ouverte' : ' — clôturée'))
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('periode', $direction))
                    ->collapsible(),
            ])
            ->defaultGroup('periode')
            ->defaultSort('parkod')
            ->striped()
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('periode')
                    ->label('Période')
                    ->badge()
                    ->color(fn ($record) => $record->periodeRef?->estOuverte() ? 'success' : 'gray')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('parkod')
                    ->label('Code article')
                    ->copyable()
                    ->searchable(isIndividual: true)
                    ->sortable(),

                TextColumn::make('article.description')
                    ->label('Désignation')
                    ->searchable(isIndividual: true)
                    ->wrap(),

                ...$groupesSocietes,

                ColumnGroup::make($labelTotal, [
                    self::colonneQte('t_qte', 'Qté clôture', gras: true, labelTotal: $labelTotal),
                    self::colonneVal('t_val', 'Valeur', labelTotal: $labelTotal),
                ])->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(fn () => Periode::query()
                        ->orderByDesc('periode')
                        ->pluck('periode', 'periode')
                        ->all())
                    ->default(fn () => [Periode::ouvertes()->orderByDesc('periode')->value('periode')])
                    ->searchable()
                    ->multiple(),

                Filter::make('stock_negatif')
                    ->label('Stock négatif (une société au moins)')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('qte_cloture', '<', 0)),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export stock mensuels')
                    ->exporter(StockMensuelExporter::class)
                    ->fileDisk('local')
                    ->chunkSize(500)
                    // On ignore la requête groupée de la table : l'export part des articles
                    ->modifyQueryUsing(fn (Builder $query, array $options): Builder => Article::query()
                        ->whereIn(
                            'parkod',
                            StockMensuel::query()
                                ->pourPeriode($options['periode'])
                                ->select('parkod')
                        )),
            ])
            ->emptyStateHeading('Aucun stock mensuel')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
