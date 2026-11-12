<?php

namespace App\Filament\Resources\StockMensuels\Tables;

use App\Filament\Exports\StockMensuelExporter;
use App\Models\Article;
use App\Models\Periode;
use App\Models\Societe;
use App\Models\StockMensuel;
use Filament\Actions\ExportAction;
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
    /**
     * Séparateur vertical entre deux groupes (bordure gauche).
     * rgba pour rester lisible en mode clair et sombre.
     */
    private const SEPARATEUR = 'border-left: 2px solid rgba(128, 128, 128, 0.55);';

    private static function couleur(mixed $state): string
    {
        return match (true) {
            $state === null => 'gray',
            $state < 0 => 'danger',
            $state == 0 => 'gray',
            default => 'success',
        };
    }

    /**
     * Ajoute la bordure gauche sur une colonne (en-tête + cellules).
     */
    private static function avecSeparateur(TextColumn $colonne): TextColumn
    {
        return $colonne
            ->extraHeaderAttributes(['style' => self::SEPARATEUR])
            ->extraCellAttributes(['style' => self::SEPARATEUR]);
    }

    private static function colonneQte(
        string $alias,
        string $label,
        bool $gras = false,
        string $labelTotal = 'Total',
        bool $cache = false,
    ): TextColumn {
        return TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 0, locale: 'fr')
            ->placeholder('0')
            ->color(fn ($state) => self::couleur($state))
            ->weight($gras ? 'bold' : null)
            ->alignEnd()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: $cache)
            ->summarize(Sum::make()->label($labelTotal)->numeric(decimalPlaces: 0, locale: 'fr'));
    }

    /**
     * Colonne de mouvement quantité (IN / OUT) avec couleur fixe.
     */
    private static function colonneMvt(
        string $alias,
        string $label,
        string $couleur,
        string $labelTotal = 'Total',
        bool $cache = false,
    ): TextColumn {
        return TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 0, locale: 'fr')
            ->placeholder('0')
            ->color(fn ($state) => $state ? $couleur : 'gray')
            ->alignEnd()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: $cache)
            ->summarize(Sum::make()->label($labelTotal)->numeric(decimalPlaces: 0, locale: 'fr'));
    }

    /**
     * Colonne valeur. Couleur optionnelle (utilisée pour Val. IN / Val. OUT).
     */
    private static function colonneVal(
        string $alias,
        string $label,
        bool $cache = true,
        string $labelTotal = 'Total',
        ?string $couleur = null,
    ): TextColumn {
        $colonne = TextColumn::make($alias)
            ->label($label)
            ->numeric(decimalPlaces: 2, locale: 'fr')
            ->placeholder('0,00')
            ->alignEnd()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: $cache)
            ->summarize(Sum::make()->label($labelTotal)->numeric(decimalPlaces: 2, locale: 'fr'));

        if ($couleur !== null) {
            $colonne->color(fn ($state) => $state ? $couleur : 'gray');
        }

        return $colonne;
    }

    /**
     * Requête de l'export : un article par ligne, avec les quantités
     * et valeurs (clôture, IN, OUT, écart inventaire) calculées en SQL
     * pour la période choisie.
     *
     * @param array<string, string> $societes code_stock => libellé
     */
    private static function requeteExport(array $societes, string $periode): Builder
    {
        $articles = (new Article())->getTable();

        $somme = fn (string $colonne, ?string $code = null) => StockMensuel::query()
            ->selectRaw("COALESCE(SUM(stock_mensuels.{$colonne}), 0)")
            ->whereColumn('stock_mensuels.parkod', "{$articles}.parkod")
            ->where('stock_mensuels.periode', $periode)
            ->when($code !== null, fn ($q) => $q->where('stock_mensuels.code_stock', $code));

        $query = Article::query()
            ->select("{$articles}.*")
            ->whereIn(
                "{$articles}.parkod",
                StockMensuel::query()->pourPeriode($periode)->select('parkod')
            );

        $i = 0;
        foreach (array_keys($societes) as $code) {
            $query->selectSub($somme('qte_cloture', $code), "s{$i}_qte");
            $query->selectSub($somme('qte_in', $code), "s{$i}_in");
            $query->selectSub($somme('qte_out', $code), "s{$i}_out");
            $query->selectSub($somme('qte_stk', $code), "s{$i}_stk");
            $query->selectSub($somme('val_in', $code), "s{$i}_vin");
            $query->selectSub($somme('val_out', $code), "s{$i}_vout");
            $i++;
        }

        $query->selectSub($somme('qte_cloture'), 't_qte');
        $query->selectSub($somme('qte_in'), 't_in');
        $query->selectSub($somme('qte_out'), 't_out');
        $query->selectSub($somme('qte_stk'), 't_stk');
        $query->selectSub($somme('val_in'), 't_vin');
        $query->selectSub($somme('val_out'), 't_vout');

        return $query;
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
                self::colonneVal("s{$i}_vin", 'Val. IN', couleur: 'success'),
                self::colonneVal("s{$i}_vout", 'Val. OUT', couleur: 'danger'),
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
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN qte_cloture END), 0) as s{$i}_qte";
                    $bindings[] = $code;
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN qte_in END), 0) as s{$i}_in";
                    $bindings[] = $code;
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN qte_out END), 0) as s{$i}_out";
                    $bindings[] = $code;
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN qte_stk END), 0) as s{$i}_stk";
                    $bindings[] = $code;
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN val_in END), 0) as s{$i}_vin";
                    $bindings[] = $code;
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN val_out END), 0) as s{$i}_vout";
                    $bindings[] = $code;
                    $selects[] = "COALESCE(SUM(CASE WHEN code_stock = ? THEN val_cloture END), 0) as s{$i}_val";
                    $bindings[] = $code;
                    $i++;
                }

                $selects[] = 'COALESCE(SUM(qte_cloture), 0) as t_qte';
                $selects[] = 'COALESCE(SUM(qte_in), 0) as t_in';
                $selects[] = 'COALESCE(SUM(qte_out), 0) as t_out';
                $selects[] = 'COALESCE(SUM(qte_stk), 0) as t_stk';
                $selects[] = 'COALESCE(SUM(val_in), 0) as t_vin';
                $selects[] = 'COALESCE(SUM(val_out), 0) as t_vout';
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
                    ->description(fn ($record): ?string => $record->article?->ean
                        ? 'EAN : ' . $record->article->ean
                        : null)
                    ->searchable(isIndividual: true)
                    ->wrap(),

                ...$groupesSocietes,

                ColumnGroup::make($labelTotal, [
                    self::avecSeparateur(
                        self::colonneQte('t_qte', 'Qté clôture', gras: true, labelTotal: $labelTotal)
                    ),
                    self::colonneMvt('t_in', 'IN', 'success', labelTotal: $labelTotal),
                    self::colonneMvt('t_out', 'OUT', 'danger', labelTotal: $labelTotal),
                    self::colonneQte('t_stk', 'Écart inv.', labelTotal: $labelTotal),
                    self::colonneVal('t_vin', 'Val. IN', labelTotal: $labelTotal, couleur: 'success'),
                    self::colonneVal('t_vout', 'Val. OUT', labelTotal: $labelTotal, couleur: 'danger'),
                    self::colonneVal('t_val', 'Valeur', labelTotal: $labelTotal),
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
                    ->modifyQueryUsing(fn (Builder $query, array $options): Builder => self::requeteExport(
                        $societes,
                        (string) $options['periode'],
                    )),
            ])
            ->emptyStateHeading('Aucun stock mensuel')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
