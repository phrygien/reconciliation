<?php

namespace App\Filament\Resources\StockMensuels\Tables;

use App\Filament\Exports\StockMensuelExporter;
use App\Models\Article;
use App\Models\Periode;
use App\Models\Societe;
use App\Models\StockMensuel;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMensuelsTable
{
    private const SEPARATEUR = 'border-left: 2px solid rgba(128, 128, 128, 0.55);';

    /** alias => colonne SQL de stock_mensuels. */
    private const METRIQUES = [
        'qte'  => 'qte_cloture',
        'in'   => 'qte_in',
        'out'  => 'qte_out',
        'stk'  => 'qte_stk',
        'vin'  => 'val_in',
        'vout' => 'val_out',
        'val'  => 'val_cloture',
    ];

    private static function couleur(mixed $state): string
    {
        return match (true) {
            $state === null, $state == 0 => 'gray',
            $state < 0 => 'danger',
            default => 'success',
        };
    }

    private static function avecSeparateur(TextColumn $colonne): TextColumn
    {
        return $colonne
            ->extraHeaderAttributes(['style' => self::SEPARATEUR])
            ->extraCellAttributes(['style' => self::SEPARATEUR]);
    }

    /** Colonne quantité (couleur selon le signe). */
    private static function colonneQte(string $alias, string $label, bool $gras = false): TextColumn
    {
        return TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 0, locale: 'fr')
            ->placeholder('0')
            ->color(fn ($state) => self::couleur($state))
            ->weight($gras ? 'bold' : null)
            ->alignEnd()
            ->sortable();
    }

    /** Colonne mouvement (IN / OUT) avec couleur fixe. */
    private static function colonneMvt(string $alias, string $label, string $couleur): TextColumn
    {
        return TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 0, locale: 'fr')
            ->placeholder('0')
            ->color(fn ($state) => $state ? $couleur : 'gray')
            ->alignEnd()
            ->sortable();
    }

    /** Colonne valeur. */
    private static function colonneVal(string $alias, string $label, ?string $couleur = null): TextColumn
    {
        $colonne = TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 2, locale: 'fr')
            ->placeholder('0,00')
            ->alignEnd()
            ->sortable();

        if ($couleur !== null) {
            $colonne->color(fn ($state) => $state ? $couleur : 'gray');
        }

        return $colonne;
    }

    /**
     * Requête de l'export : UN seul scan de stock_mensuels (table dérivée
     * agrégée par article) joint aux articles.
     *
     * @param array<string, string> $societes code_stock => libellé
     */
    private static function requeteExport(array $societes, string $periode): Builder
    {
        $articles = (new Article())->getTable();

        // L'export n'utilise pas la valeur de clôture.
        $metriques = array_diff_key(self::METRIQUES, ['val' => true]);

        $agg = StockMensuel::query()
            ->select('parkod')
            ->where('periode', $periode)
            ->groupBy('parkod');

        $aliases = [];
        $i = 0;
        foreach (array_keys($societes) as $code) {
            foreach ($metriques as $alias => $col) {
                $agg->selectRaw(
                    "SUM(CASE WHEN code_stock = ? THEN {$col} ELSE 0 END) as s{$i}_{$alias}",
                    [$code]
                );
                $aliases[] = "s{$i}_{$alias}";
            }
            $i++;
        }

        foreach ($metriques as $alias => $col) {
            $agg->selectRaw("SUM({$col}) as t_{$alias}");
            $aliases[] = "t_{$alias}";
        }

        return Article::query()
            ->select("{$articles}.*")
            ->joinSub($agg, 'sm', 'sm.parkod', '=', "{$articles}.parkod")
            ->addSelect(array_map(fn (string $a) => "sm.{$a}", $aliases));
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
                self::avecSeparateur(self::colonneQte("s{$i}_qte", 'Qté', gras: true)),
                self::colonneMvt("s{$i}_in", 'IN', 'success'),
                self::colonneMvt("s{$i}_out", 'OUT', 'danger'),
                self::colonneQte("s{$i}_stk", 'Écart inv.'),
                self::colonneVal("s{$i}_vin", 'Val. IN', 'success'),
                self::colonneVal("s{$i}_vout", 'Val. OUT', 'danger'),
                self::colonneVal("s{$i}_val", 'Valeur'),
            ])
                ->alignCenter()
                ->extraHeaderAttributes(['style' => self::SEPARATEUR]);
            $i++;
        }

        $labelTotal = 'Total stock dépôt centrale';

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($societes) {
                $selects = ['MIN(id) as id', 'periode', 'parkod'];
                $bindings = [];

                $i = 0;
                foreach (array_keys($societes) as $code) {
                    foreach (self::METRIQUES as $alias => $col) {
                        $selects[] = "SUM(CASE WHEN code_stock = ? THEN {$col} ELSE 0 END) as s{$i}_{$alias}";
                        $bindings[] = $code;
                    }
                    $i++;
                }

                foreach (self::METRIQUES as $alias => $col) {
                    $selects[] = "SUM({$col}) as t_{$alias}";
                }

                return $query
                    ->with([
                        'article:parkod,description,ean',
                        'periodeRef',
                    ])
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
            ->deferLoading()
            ->columns([
                TextColumn::make('periode')
                    ->label('Période')
                    ->badge()
                    ->color(fn ($record) => $record->periodeRef?->estOuverte() ? 'success' : 'gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parkod')
                    ->label('Code article')
                    ->copyable()
                    ->searchable(isIndividual: true)
                    ->sortable(),

                TextColumn::make('article.description')
                    ->label('Désignation')
                    ->description(fn ($record): ?string => $record->article?->ean
                        ? 'EAN : ' . $record->article->ean
                        : null)
                    ->searchable(isIndividual: true)
                    ->wrap(),

                ...$groupesSocietes,

                ColumnGroup::make($labelTotal, [
                    self::avecSeparateur(self::colonneQte('t_qte', 'Qté clôture', gras: true)),
                    self::colonneMvt('t_in', 'IN', 'success'),
                    self::colonneMvt('t_out', 'OUT', 'danger'),
                    self::colonneQte('t_stk', 'Écart inv.'),
                    self::colonneVal('t_vin', 'Val. IN', 'success'),
                    self::colonneVal('t_vout', 'Val. OUT', 'danger'),
                    self::colonneVal('t_val', 'Valeur'),
                ])
                    ->alignCenter()
                    ->extraHeaderAttributes(['style' => self::SEPARATEUR]),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(fn () => Periode::query()
                        ->orderByDesc('periode')
                        ->pluck('periode', 'periode')
                        ->all())
                    ->default(fn () => array_filter([
                        Periode::ouvertes()->orderByDesc('periode')->value('periode'),
                    ]))
                    ->searchable()
                    ->multiple(),

                Filter::make('stock_negatif')
                    ->label('Stock négatif (une société au moins)')
                    ->toggle()
                    ->query(function (Builder $query) use ($societes): Builder {
                        if (empty($societes)) {
                            return $query;
                        }

                        $conditions = [];
                        $bindings = [];

                        foreach (array_keys($societes) as $code) {
                            $conditions[] = 'SUM(CASE WHEN code_stock = ? THEN qte_cloture ELSE 0 END) < 0';
                            $bindings[] = $code;
                        }

                        return $query->havingRaw('(' . implode(' OR ', $conditions) . ')', $bindings);
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export stock mensuels')
                    ->exporter(StockMensuelExporter::class)
                    ->fileDisk('local')
                    ->chunkSize(500)
                    ->modifyQueryUsing(fn (Builder $query, array $options): Builder => self::requeteExport(
                        $societes,
                        (string) $options['periode'],
                    )),
            ])
            ->emptyStateHeading('Aucun stock mensuel')
            ->paginationMode(PaginationMode::Simple)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
