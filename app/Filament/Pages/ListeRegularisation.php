<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Marque;
use App\Services\Regularisation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

class ListeRegularisation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedScale;

    protected static string | UnitEnum | null $navigationGroup = 'Régularisation';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'À régulariser';

    protected static ?string $title = 'Régularisation des stocks';

    protected static ?string $slug = 'regularisation';

    protected string $view = 'filament.pages.liste-regularisation';

    /** Nombre maximum de lignes affichées dans l'aperçu du assistant. */
    protected const APERCU_LIMITE = 200;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * Colonnes de l'export : clé => [libellé, largeur, format numérique|null, gras ?]
     * Format null = texte.
     */
    protected const EXPORT_COLUMNS = [
        'periode'           => ['Période',         12, null,                      false],
        'parkod'            => ['Article',         16, null,                      false],
        'ean'               => ['EAN',             18, null,                      false],
        'marque'            => ['Marque',          20, null,                      false],
        'description'       => ['Description',     45, null,                      false],
        'qte_adv'           => ['Qté ADV',         12, '#,##0.00;[Red]-#,##0.00', false],
        'qte_cosma'         => ['Qté COSMA',       12, '#,##0.00;[Red]-#,##0.00', false],
        'qte_total'         => ['Qté totale',      12, '#,##0.00;[Red]-#,##0.00', false],
        'pamp_adv'          => ['PAMP ADV',        12, '#,##0.0000',              false],
        'pamp_cosma'        => ['PAMP COSMA',      12, '#,##0.0000',              false],
        'cas'               => ['Cas',             22, null,                      false],
        'facturer_a'        => ['Facturer à',      12, null,                      false],
        'facture_par'       => ['Facturé par',     12, null,                      false],
        'qte_a_facturer'    => ['Qté à facturer',  14, '#,##0.00',                false],
        'pamp'              => ['PAMP retenu',     12, '#,##0.0000',              false],
        'valeur_a_facturer' => ['Montant facturé', 16, '#,##0.00',                true],
        'qte_residuelle'    => ['Qté résiduelle',  14, '#,##0.00;[Red]-#,##0.00', false],
    ];

    protected const CAS_LABELS = [
        'COMPENSABLE_TOTAL'   => 'Compensable total',
        'COMPENSABLE_PARTIEL' => 'Compensable partiel',
        'NON_COMPENSABLE'     => 'Non compensable',
    ];

    /**
     * Couleurs de l'export : [fond, texte] par cas.
     */
    protected const CAS_COLORS = [
        'COMPENSABLE_TOTAL'   => ['D1FAE5', '065F46'],
        'COMPENSABLE_PARTIEL' => ['FEF3C7', '92400E'],
        'NON_COMPENSABLE'     => ['FEE2E2', '991B1B'],
    ];

    /**
     * État courant des filtres, normalisé pour le service.
     *
     * @return array{filtre: string, periode: ?string, facturerA: ?string, marqueIds: int[]}
     */
    protected function filtresCourants(): array
    {
        $filtre    = data_get($this->tableFilters, 'filtre.value');
        $periode   = data_get($this->tableFilters, 'periode.value');
        $facturerA = data_get($this->tableFilters, 'facturer_a.value');
        $marques   = data_get($this->tableFilters, 'marque_id.values');

        return [
            'filtre'    => $filtre ?: Regularisation::FILTRE_ALL,
            'periode'   => $periode ?: null,
            'facturerA' => $facturerA ?: null,
            'marqueIds' => array_values(array_map('intval', array_filter((array) $marques))),
        ];
    }

    /**
     * Interroge le service avec l'état courant des filtres (lu depuis Livewire).
     */
    protected function fetchRegularisation(int $page, int $perPage): LengthAwarePaginator
    {
        return app(Regularisation::class)->listeRegularisation(
            ...['page' => $page, 'perPage' => $perPage, ...$this->filtresCourants()]
        );
    }

    /**
     * Aperçu de ce que la régularisation va traiter (COMPENSABLE_TOTAL, filtres actifs).
     * Les totaux portent sur toutes les lignes, la liste est limitée à APERCU_LIMITE.
     *
     * @return array{nb: int, qte: float, total: float, lignes: array<int, array<string, mixed>>}
     */
    protected function apercuRegularisation(): array
    {
        $f = $this->filtresCourants();

        $rows = app(Regularisation::class)->exportRegularisation(
            filtre: Regularisation::FILTRE_A_REGULARISER,
            periode: $f['periode'],
            facturerA: $f['facturerA'],
            marqueIds: $f['marqueIds'],
        );

        $nb     = 0;
        $qte    = 0.0;
        $total  = 0.0;
        $lignes = [];

        foreach ($rows as $row) {
            if ($row->cas !== 'COMPENSABLE_TOTAL') {
                continue;
            }

            $nb++;
            $qte   += (float) $row->qte_a_facturer;
            $total += (float) $row->valeur_a_facturer;

            if ($nb <= self::APERCU_LIMITE) {
                $lignes[] = (array) $row;
            }
        }

        return ['nb' => $nb, 'qte' => $qte, 'total' => round($total, 2), 'lignes' => $lignes];
    }

    /**
     * Libellé lisible des filtres actifs (étape 1 de l'assistant).
     *
     * @return array<string, string>
     */
    protected function libellesFiltres(): array
    {
        $f = $this->filtresCourants();

        $marques = $f['marqueIds'] === []
            ? 'Toutes les marques'
            : Marque::query()->whereIn('id', $f['marqueIds'])->orderBy('nom')->pluck('nom')->implode(', ');

        return [
            'Période'    => $f['periode'] ?? 'Toutes les périodes ouvertes',
            'Facturer à' => $f['facturerA'] ?? 'Tous',
            'Marques'    => $marques,
        ];
    }

    /**
     * Régularise les « Compensable total » correspondant aux filtres actifs.
     * Le filtre « Affichage » est ignoré : le service ne traite que les COMPENSABLE_TOTAL.
     */
    public function regulariserStocks(): void
    {
        $filtres = $this->filtresCourants();

        try {
            $bilan = app(Regularisation::class)->regulariserLeStocks(
                periode: $filtres['periode'],
                facturerA: $filtres['facturerA'],
                marqueIds: $filtres['marqueIds'],
                user_id: auth()->id(),
            );
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Échec de la régularisation')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($bilan['regularises'] === 0) {
            Notification::make()
                ->title('Aucune ligne régularisée')
                ->body('Aucun « Compensable total » à traiter avec ces filtres (' . $bilan['ignores'] . ' ignorée(s)).')
                ->warning()
                ->send();

            return;
        }

        $nbFactures = count($bilan['factures']);
        $total      = number_format($bilan['total_facture'], 2, ',', ' ');

        $notification = Notification::make()
            ->title('Régularisation effectuée')
            ->body(
                "{$bilan['regularises']} ligne(s) régularisée(s), {$nbFactures} facture(s) générée(s), "
                . "total {$total}. {$bilan['ignores']} ignorée(s)."
                . (count($bilan['erreurs']) > 0 ? ' ' . count($bilan['erreurs']) . ' erreur(s), voir les logs.' : '')
            );

        count($bilan['erreurs']) > 0 ? $notification->warning() : $notification->success();

        $notification->persistent()->send();
    }

    /**
     * Bordure fine grise sur les 4 côtés.
     */
    protected function xlsxBorder(string $color = 'D1D5DB'): Border
    {
        $part = fn (string $name): BorderPart => new BorderPart(
            $name,
            $this->xlsxColor($color),
            Border::WIDTH_THIN,
            Border::STYLE_SOLID,
        );

        return new Border(
            $part(Border::TOP),
            $part(Border::RIGHT),
            $part(Border::BOTTOM),
            $part(Border::LEFT),
        );
    }

    protected function xlsxColor(string $hex): string
    {
        return Color::rgb(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Export Excel (.xlsx) de toutes les lignes correspondant aux filtres actifs.
     */
    public function exportExcel(): StreamedResponse
    {
        $filtres  = $this->filtresCourants();
        $fileName = 'regularisation-' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($filtres): void {
            set_time_limit(0);

            // Largeurs de colonnes
            $options = new Options();
            $index   = 1;

            foreach (self::EXPORT_COLUMNS as [, $width]) {
                $options->setColumnWidth($width, $index++);
            }

            $writer = new Writer($options);
            $writer->openToFile('php://output');

            $border = $this->xlsxBorder();

            // Figer la ligne d'en-tête + nom de la feuille
            $sheetView = new SheetView();
            $sheetView->setFreezeRow(2);
            $sheet = $writer->getCurrentSheet();
            $sheet->setSheetView($sheetView);
            $sheet->setName('Régularisation');

            // Style d'en-tête
            $headerStyle = (new Style())
                ->setFontBold()
                ->setFontSize(11)
                ->setFontColor(Color::WHITE)
                ->setBackgroundColor($this->xlsxColor('1F2937'))
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                ->setShouldWrapText(false)
                ->setBorder($border);

            $writer->addRow(new Row(
                array_map(
                    fn (array $c): Cell => Cell::fromValue($c[0], $headerStyle),
                    array_values(self::EXPORT_COLUMNS),
                ),
            ));

            // Styles pré-construits par colonne (réutilisés pour chaque ligne)
            $columnStyles = [];

            foreach (self::EXPORT_COLUMNS as $key => [, , $format, $bold]) {
                $style = (new Style())
                    ->setFontSize(10)
                    ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                    ->setBorder($border);

                if ($format !== null) {
                    $style->setFormat($format)->setCellAlignment(CellAlignment::RIGHT);
                }

                if ($bold) {
                    $style->setFontBold();
                }

                $columnStyles[$key] = $style;
            }

            // Styles de la colonne « Cas » selon la valeur
            $casStyles = [];

            foreach (self::CAS_COLORS as $cas => [$bg, $fg]) {
                $casStyles[$cas] = (new Style())
                    ->setFontSize(10)
                    ->setFontBold()
                    ->setFontColor($this->xlsxColor($fg))
                    ->setBackgroundColor($this->xlsxColor($bg))
                    ->setCellAlignment(CellAlignment::CENTER)
                    ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                    ->setBorder($border);
            }

            // Style de la colonne « Facturer à » / « Facturé par »
            $centerStyle = (new Style())
                ->setFontSize(10)
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                ->setBorder($border);

            $rows = app(Regularisation::class)->exportRegularisation(...$filtres);

            foreach ($rows as $row) {
                $cells = [];

                foreach (self::EXPORT_COLUMNS as $key => [, , $format]) {
                    $value = $row->{$key} ?? null;
                    $style = $columnStyles[$key];

                    if ($key === 'cas' && $value !== null) {
                        $style = $casStyles[$value] ?? $style;
                        $value = self::CAS_LABELS[$value] ?? $value;
                    }

                    if (in_array($key, ['facturer_a', 'facture_par'], true)) {
                        $style = $centerStyle;
                    }

                    if ($format !== null && $value !== null) {
                        $value = (float) $value;
                    }

                    $cells[] = Cell::fromValue($value, $style);
                }

                $writer->addRow(new Row($cells));
            }

            $writer->close();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('regulariser')
                    ->label('Régulariser les stocks')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('warning')
                    ->modalHeading('Régulariser les stocks')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Régulariser')
                    ->steps([
                        Step::make('Filtres')
                            ->description('Périmètre de la régularisation')
                            ->icon(Heroicon::OutlinedFunnel)
                            ->schema(function (): array {
                                $lignes = [];

                                foreach ($this->libellesFiltres() as $label => $valeur) {
                                    $lignes[] = Text::make("{$label} : {$valeur}")->weight('semibold');
                                }

                                $lignes[] = Text::make(
                                    'Seules les lignes « Compensable total » sont traitées. '
                                    . 'Le filtre « Affichage » est ignoré.'
                                )->color('gray');

                                return $lignes;
                            }),

                        Step::make('Aperçu')
                            ->description('Lignes à régulariser')
                            ->icon(Heroicon::OutlinedEye)
                            ->schema(function (): array {
                                $a = $this->apercuRegularisation();

                                if ($a['nb'] === 0) {
                                    return [
                                        Text::make('Aucune ligne « Compensable total » à régulariser avec ces filtres.')
                                            ->color('danger'),
                                    ];
                                }

                                return [
                                    Text::make(
                                        "{$a['nb']} ligne(s) · quantité " . number_format($a['qte'], 0, ',', ' ')
                                        . ' · montant à facturer ' . number_format($a['total'], 2, ',', ' ')
                                    )->weight('semibold'),

                                    View::make('filament.pages.partials.apercu-regularisation')
                                        ->viewData([
                                            'lignes' => $a['lignes'],
                                            'nb'     => $a['nb'],
                                            'limite' => self::APERCU_LIMITE,
                                        ]),

                                    Text::make('Les stocks d\'ouverture ADV / COSMA seront modifiés et les factures générées.')
                                        ->color('gray'),
                                ];
                            }),

                        Step::make('Confirmation')
                            ->description('Validation finale')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                Text::make('Cette opération ne peut pas être annulée automatiquement.')
                                    ->color('danger')
                                    ->weight('semibold'),

                                Checkbox::make('confirmation')
                                    ->label('Je confirme vouloir régulariser ces stocks')
                                    ->accepted()
                                    ->validationMessages([
                                        'accepted' => 'Vous devez confirmer pour continuer.',
                                    ]),
                            ]),
                    ])
                    ->action(fn () => $this->regulariserStocks()),

                Action::make('export')
                    ->label('Exporter Excel')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->action(fn () => $this->exportExcel()),
            ])
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $paginator = $this->fetchRegularisation($page, $recordsPerPage);

                // Page hors limites après changement de filtre : on recharge la dernière page
                if ($paginator->isEmpty() && $page > 1) {
                    $lastPage = max($paginator->lastPage(), 1);

                    $this->setPage($lastPage, $this->getTablePaginationPageName());

                    $paginator = $this->fetchRegularisation($lastPage, $recordsPerPage);
                }

                // Filament attend des tableaux indexés par une clé unique
                $paginator->setCollection(
                    $paginator->getCollection()->mapWithKeys(
                        fn ($row) => [$row->periode . '|' . $row->parkod => (array) $row]
                    )
                );

                return $paginator;
            })
            ->recordUrl(
                fn (array $record): ?string => ! empty($record['article_id'])
                    ? ArticleResource::getUrl('view', ['record' => $record['article_id']])
                    : null
            )
            ->columns([
                TextColumn::make('periode')
                    ->label('Période'),

                TextColumn::make('parkod')
                    ->label('Article'),

                TextColumn::make('ean')
                    ->label('EAN')
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('marque')
                    ->label('Marque')
                    ->placeholder('—'),

                TextColumn::make('description')
                    ->label('Description')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn ($state): ?string => is_string($state) && mb_strlen($state) > 50 ? $state : null),

                TextColumn::make('qte_adv')
                    ->label('Qté ADV')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),

                TextColumn::make('qte_cosma')
                    ->label('Qté COSMA')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),

                TextColumn::make('qte_total')
                    ->label('Qté totale')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),

                TextColumn::make('pamp_adv')
                    ->label('PAMP ADV')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('pamp_cosma')
                    ->label('PAMP COSMA')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('cas')
                    ->label('Cas')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::CAS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'COMPENSABLE_TOTAL'   => 'success',
                        'COMPENSABLE_PARTIEL' => 'warning',
                        'NON_COMPENSABLE'     => 'danger',
                        default               => 'gray',
                    }),

                TextColumn::make('facturer_a')
                    ->label('Facturer à')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('facture_par')
                    ->label('Facturé par')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('qte_a_facturer')
                    ->label('Qté à facturer')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('pamp')
                    ->label('PAMP retenu')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('valeur_a_facturer')
                    ->label('Montant facturé')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->weight('bold')
                    ->placeholder('—'),

                TextColumn::make('qte_residuelle')
                    ->label('Qté résiduelle')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('filtre')
                    ->label('Affichage')
                    ->options([
                        Regularisation::FILTRE_ALL           => 'Tous',
                        Regularisation::FILTRE_A_REGULARISER => 'À régulariser',
                        Regularisation::FILTRE_ANOMALIES     => 'Anomalies',
                    ])
                    ->default(Regularisation::FILTRE_ALL)
                    ->selectablePlaceholder(false),

                SelectFilter::make('periode')
                    ->label('Période')
                    ->placeholder('Toutes les périodes ouvertes')
                    ->options(fn () => DB::table('periodes')
                        ->where('statut', 'OUVERTE')
                        ->orderBy('periode')
                        ->pluck('periode', 'periode')
                        ->all()),

                SelectFilter::make('facturer_a')
                    ->label('Facturer à')
                    ->placeholder('Tous')
                    ->options([
                        Regularisation::FACTURER_A_ADV   => 'ADV',
                        Regularisation::FACTURER_A_COSMA => 'COSMA',
                    ]),

                SelectFilter::make('marque_id')
                    ->label('Marques')
                    ->placeholder('Toutes les marques')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => Marque::query()
                        ->orderBy('nom')
                        ->pluck('nom', 'id')
                        ->all()),
            ])
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Aucun article à régulariser');
    }
}
