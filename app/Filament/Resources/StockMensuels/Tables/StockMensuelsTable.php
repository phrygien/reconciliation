<?php

namespace App\Filament\Resources\StockMensuels\Tables;

use App\Filament\Exports\StockMensuelExporter;
use App\Models\Article;
use App\Models\Periode;
use App\Models\Societe;
use App\Models\StockMensuel;
use Closure;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use WeakMap;

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

    // ───────────────────── Calcul tardif (page courante) ─────────────────────

    /**
     * Calcule en UNE requête les métriques des seules lignes affichées.
     * Résultat mis en cache par page (WeakMap : libéré avec le paginator).
     *
     * @return array<string, array{s: array<string, array<string, float>>, t: array<string, float>}>
     */
    private static function metriquesPage(object $livewire): array
    {
        static $cache = null;
        $cache ??= new WeakMap();

        $records = $livewire->getTableRecords();

        if (isset($cache[$records])) {
            return $cache[$records];
        }

        $items = $records instanceof Collection ? $records : collect($records->items());

        if ($items->isEmpty()) {
            return $cache[$records] = [];
        }

        // Backticks : "in" et "out" sont des mots réservés SQL.
        $sommes = [];
        foreach (self::METRIQUES as $alias => $col) {
            $sommes[] = "COALESCE(SUM({$col}), 0) as `{$alias}`";
        }

        $lignes = StockMensuel::query()
            ->select(['periode', 'parkod', 'code_stock'])
            ->selectRaw(implode(', ', $sommes))
            ->whereIn('periode', $items->pluck('periode')->unique()->all())
            ->whereIn('parkod', $items->pluck('parkod')->unique()->all())
            ->groupBy('periode', 'parkod', 'code_stock')
            ->toBase()
            ->get();

        $out = [];
        foreach ($lignes as $ligne) {
            $cle = $ligne->periode . '|' . $ligne->parkod;

            foreach (self::METRIQUES as $alias => $_) {
                $v = (float) $ligne->{$alias};
                $out[$cle]['s'][$ligne->code_stock][$alias] = $v;
                $out[$cle]['t'][$alias] = ($out[$cle]['t'][$alias] ?? 0) + $v;
            }
        }

        return $cache[$records] = $out;
    }

    /**
     * État d'une colonne : $code = null pour le total, sinon code_stock de la société.
     */
    private static function etat(?string $code, string $alias): Closure
    {
        return function ($record, $livewire) use ($code, $alias) {
            $m = self::metriquesPage($livewire)[$record->periode . '|' . $record->parkod] ?? null;

            return $code === null
                ? ($m['t'][$alias] ?? 0)
                : ($m['s'][$code][$alias] ?? 0);
        };
    }

    // ───────────────────── Totaux (pied de tableau) ─────────────────────

    /**
     * Somme de toutes les métriques, par code_stock, pour l'ensemble des clés
     * (période, article) de la requête reçue. Une seule requête, mise en cache.
     *
     * @return array{s: array<string, array<string, float>>, t: array<string, float>}
     */
    private static function totauxRequete(mixed $query): array
    {
        static $cache = [];

        $base = $query instanceof Builder ? $query->toBase() : $query;
        $base = (clone $base)->reorder();

        $cle = md5($base->toSql() . serialize($base->getBindings()));

        if (isset($cache[$cle])) {
            return $cache[$cle];
        }

        // Backticks : "in" et "out" sont des mots réservés SQL.
        $sommes = [];
        foreach (self::METRIQUES as $alias => $col) {
            $sommes[] = "COALESCE(SUM(sm.{$col}), 0) as `{$alias}`";
        }

        // Le sous-select contient une ligne par (periode, parkod) : pas de doublons.
        $lignes = DB::table('stock_mensuels as sm')
            ->joinSub($base, 'k', fn ($join) => $join
                ->on('k.periode', '=', 'sm.periode')
                ->on('k.parkod', '=', 'sm.parkod'))
            ->selectRaw('sm.code_stock, ' . implode(', ', $sommes))
            ->groupBy('sm.code_stock')
            ->get();

        $out = ['s' => [], 't' => []];
        foreach ($lignes as $ligne) {
            foreach (self::METRIQUES as $alias => $_) {
                $v = (float) $ligne->{$alias};
                $out['s'][$ligne->code_stock][$alias] = $v;
                $out['t'][$alias] = ($out['t'][$alias] ?? 0) + $v;
            }
        }

        return $cache[$cle] = $out;
    }

    /** Summarizer d'une colonne : $code = null pour le total général. */
    private static function total(?string $code, string $alias, int $decimales = 0): Summarizer
    {
        return Summarizer::make()
            ->label('Total')
            ->numeric(decimalPlaces: $decimales, locale: 'fr')
            ->using(function ($query) use ($code, $alias) {
                $t = self::totauxRequete($query);

                return $code === null
                    ? ($t['t'][$alias] ?? 0)
                    : ($t['s'][$code][$alias] ?? 0);
            });
    }

    // ───────────────────────────── Colonnes ─────────────────────────────

    private static function avecSeparateur(TextColumn $colonne): TextColumn
    {
        return $colonne
            ->extraHeaderAttributes(['style' => self::SEPARATEUR])
            ->extraCellAttributes(['style' => self::SEPARATEUR]);
    }

    /** Colonne quantité (couleur selon le signe). */
    private static function colonneQte(string $nom, Closure $etat, string $label, bool $gras = false): TextColumn
    {
        return TextColumn::make($nom)
            ->label($label)
            ->state($etat)
            ->numeric(decimalPlaces: 0, locale: 'fr')
            ->placeholder('0')
            ->color(fn ($state) => self::couleur($state))
            ->weight($gras ? 'bold' : null)
            ->alignEnd();
    }

    /** Colonne mouvement (IN / OUT) avec couleur fixe. */
    private static function colonneMvt(string $nom, Closure $etat, string $label, string $couleur): TextColumn
    {
        return TextColumn::make($nom)
            ->label($label)
            ->state($etat)
            ->numeric(decimalPlaces: 0, locale: 'fr')
            ->placeholder('0')
            ->color(fn ($state) => $state ? $couleur : 'gray')
            ->alignEnd();
    }

    /** Colonne valeur. */
    private static function colonneVal(string $nom, Closure $etat, string $label, ?string $couleur = null): TextColumn
    {
        $colonne = TextColumn::make($nom)
            ->label($label)
            ->state($etat)
            ->numeric(decimalPlaces: 2, locale: 'fr')
            ->placeholder('0,00')
            ->alignEnd();

        if ($couleur !== null) {
            $colonne->color(fn ($state) => $state ? $couleur : 'gray');
        }

        return $colonne;
    }

    /**
     * Les 7 colonnes d'un bloc (une société ou le total), avec leurs totaux.
     *
     * @return array<int, TextColumn>
     */
    private static function blocColonnes(string $prefixe, ?string $code, string $labelQte): array
    {
        return [
            self::avecSeparateur(
                self::colonneQte("{$prefixe}_qte", self::etat($code, 'qte'), $labelQte, gras: true)
                    ->summarize(self::total($code, 'qte'))
            ),
            self::colonneMvt("{$prefixe}_in", self::etat($code, 'in'), 'IN', 'success')
                ->summarize(self::total($code, 'in')),
            self::colonneMvt("{$prefixe}_out", self::etat($code, 'out'), 'OUT', 'danger')
                ->summarize(self::total($code, 'out')),
            self::colonneQte("{$prefixe}_stk", self::etat($code, 'stk'), 'Écart inv.')
                ->summarize(self::total($code, 'stk')),
            self::colonneVal("{$prefixe}_vin", self::etat($code, 'vin'), 'Val. IN', 'success')
                ->summarize(self::total($code, 'vin', 2)),
            self::colonneVal("{$prefixe}_vout", self::etat($code, 'vout'), 'Val. OUT', 'danger')
                ->summarize(self::total($code, 'vout', 2)),
            self::colonneVal("{$prefixe}_val", self::etat($code, 'val'), 'Valeur')
                ->summarize(self::total($code, 'val', 2)),
        ];
    }

    // ────────────────────────────── Export ──────────────────────────────

    /**
     * Requête de l'export : jointure directe sur stock_mensuels.
     * Filament relance cette requête par chunk (restreinte aux clés du chunk).
     *
     * @param array<string, string> $societes code_stock => libellé
     */
    private static function requeteExport(array $societes, string $periode): Builder
    {
        $modele = new Article();
        $articles = $modele->getTable();
        $cle = $modele->getQualifiedKeyName();

        // L'export n'utilise pas la valeur de clôture.
        $metriques = array_diff_key(self::METRIQUES, ['val' => true]);

        $selects = [];
        $bindings = [];

        $i = 0;
        foreach (array_keys($societes) as $code) {
            foreach ($metriques as $alias => $col) {
                $selects[] = "SUM(CASE WHEN sm.code_stock = ? THEN sm.{$col} ELSE 0 END) as s{$i}_{$alias}";
                $bindings[] = $code;
            }
            $i++;
        }

        foreach ($metriques as $alias => $col) {
            $selects[] = "SUM(sm.{$col}) as t_{$alias}";
        }

        return Article::query()
            ->select([$cle, "{$articles}.parkod", "{$articles}.description"])
            ->selectRaw(implode(', ', $selects), $bindings)
            ->join('stock_mensuels as sm', function ($join) use ($articles, $periode) {
                $join->on('sm.parkod', '=', "{$articles}.parkod")
                    ->where('sm.periode', '=', $periode);
            })
            ->groupBy($cle, "{$articles}.parkod", "{$articles}.description");
    }

    // ────────────────────────────── Aide ──────────────────────────────

    /** Texte d'aide affiché sous le titre du tableau. */
    private static function aide(): HtmlString
    {
        return new HtmlString(
            '<div class="space-y-1 text-sm">'
            . '<p><strong>Lecture du tableau :</strong> une ligne = un article pour une période. '
            . 'Les colonnes sont regroupées par société, puis le total du dépôt central.</p>'
            . '<ul class="list-disc ps-5">'
            . '<li><strong>Qté / Qté clôture</strong> : quantité en stock à la clôture de la période.</li>'
            . '<li><strong>IN</strong> / <strong>OUT</strong> : quantités entrées / sorties pendant la période.</li>'
            . '<li><strong>Écart inv.</strong> : écart d\'inventaire (positif = surplus, négatif = manquant).</li>'
            . '<li><strong>Val. IN</strong> / <strong>Val. OUT</strong> : valeur des entrées / sorties.</li>'
            . '<li><strong>Valeur</strong> : valeur du stock à la clôture.</li>'
            . '<li><strong>Total stock dépôt centrale</strong> : somme de toutes les sociétés.</li>'
            . '</ul>'
            . '<p><strong>Couleurs :</strong> vert = positif, rouge = négatif, gris = zéro.</p>'
            . '<p><strong>Totaux en bas du tableau :</strong> ils portent sur tous les résultats '
            . 'des filtres et recherches en cours, toutes pages confondues, et aussi par période '
            . 'quand le groupement est actif.</p>'
            . '<p><strong>Filtres :</strong> « Stock négatif » affiche les articles dont au moins '
            . 'une société a une quantité de clôture négative.</p>'
            . '</div>'
        );
    }

    // ────────────────────────────── Table ──────────────────────────────

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
            $groupesSocietes[] = ColumnGroup::make(
                ($libelle ?: $code) . " ({$code})",
                self::blocColonnes("s{$i}", (string) $code, 'Qté'),
            )
                ->alignCenter()
                ->extraHeaderAttributes(['style' => self::SEPARATEUR]);
            $i++;
        }

        $labelTotal = 'Total stock dépôt centrale';

        return $table
            ->description(self::aide())
            // Requête de pagination minimale : uniquement les clés, aucun SUM.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with([
                    'article:parkod,description,ean',
                    'periodeRef',
                ])
                ->select(['periode', 'parkod'])
                ->selectRaw('MIN(id) as id')
                ->groupBy('periode', 'parkod'))
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
                        ? $record->article->ean
                        : null)
                    ->searchable(isIndividual: true)
                    ->wrap(),

                ...$groupesSocietes,

                ColumnGroup::make($labelTotal, self::blocColonnes('t', null, 'Qté clôture'))
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

                // Une société au moins avec une clôture négative sur (période, article).
                Filter::make('stock_negatif')
                    ->label('Stock négatif (une société au moins)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereRaw(
                        '(periode, parkod) in (
                            select sn.periode, sn.parkod
                            from stock_mensuels as sn
                            group by sn.periode, sn.parkod, sn.code_stock
                            having sum(sn.qte_cloture) < 0
                        )'
                    )),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export stock mensuels')
                    ->exporter(StockMensuelExporter::class)
                    ->columnMapping(false)
                    ->fileDisk('local')
                    ->chunkSize(1000)
                    ->modifyQueryUsing(fn (Builder $query, array $options): Builder => self::requeteExport(
                        $societes,
                        (string) $options['periode'],
                    )),
            ])
            ->emptyStateHeading('Aucun stock mensuel')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }
}
