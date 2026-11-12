<?php

namespace App\Filament\Exports;

use App\Models\Article;
use App\Models\Periode;
use App\Models\Societe;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Manager\Style\StyleMerger;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class StockMensuelExporter extends Exporter
{
    protected static ?string $model = Article::class;

    /** Colonnes quantité (entiers) : préfixes de clé. */
    private const PREFIXES_QTE = ['qte_', 'stk_', 'in_', 'out_'];

    /** Colonnes valeur (décimales) : préfixes de clé. */
    private const PREFIXES_VAL = ['vin_', 'vout_'];

    /** Colonnes de total (mises en gras avec fond bleu). */
    private const COLONNES_TOTAL = [
        'qte_cloture_total',
        'in_total',
        'out_total',
        'stk_total',
        'vin_total',
        'vout_total',
    ];

    /**
     * Les valeurs s{i}_qte, s{i}_in, s{i}_out, s{i}_stk, s{i}_vin, s{i}_vout
     * et t_* sont calculées en SQL par StockMensuelsTable::requeteExport().
     */
    public static function getColumns(): array
    {
        $columns = [
            ExportColumn::make('periode')
                ->label('Période')
                ->state(fn (array $options): string => (string) $options['periode']),

            ExportColumn::make('parkod')
                ->label('Code article'),

            ExportColumn::make('description')
                ->label('Désignation'),
        ];

        $societes = Societe::query()->orderBy('code_stock')->pluck('libelle', 'code_stock');

        $i = 0;
        foreach ($societes as $code => $libelle) {
            $nom = ($libelle ?: $code) . " ({$code})";
            $alias = $i;

            $columns[] = ExportColumn::make("qte_{$code}")
                ->label($nom)
                ->state(fn (Article $record): int => (int) ($record->{"s{$alias}_qte"} ?? 0));

            $columns[] = ExportColumn::make("in_{$code}")
                ->label('IN ' . $nom)
                ->state(fn (Article $record): int => (int) ($record->{"s{$alias}_in"} ?? 0));

            $columns[] = ExportColumn::make("out_{$code}")
                ->label('OUT ' . $nom)
                ->state(fn (Article $record): int => (int) ($record->{"s{$alias}_out"} ?? 0));

            $columns[] = ExportColumn::make("stk_{$code}")
                ->label('Écart inv. ' . $nom)
                ->state(fn (Article $record): int => (int) ($record->{"s{$alias}_stk"} ?? 0));

            $columns[] = ExportColumn::make("vin_{$code}")
                ->label('Val. IN ' . $nom)
                ->state(fn (Article $record): float => (float) ($record->{"s{$alias}_vin"} ?? 0));

            $columns[] = ExportColumn::make("vout_{$code}")
                ->label('Val. OUT ' . $nom)
                ->state(fn (Article $record): float => (float) ($record->{"s{$alias}_vout"} ?? 0));

            $i++;
        }

        $columns[] = ExportColumn::make('qte_cloture_total')
            ->label('Total stock dépôt centrale')
            ->state(fn (Article $record): int => (int) ($record->t_qte ?? 0));

        $columns[] = ExportColumn::make('in_total')
            ->label('IN total dépôt centrale')
            ->state(fn (Article $record): int => (int) ($record->t_in ?? 0));

        $columns[] = ExportColumn::make('out_total')
            ->label('OUT total dépôt centrale')
            ->state(fn (Article $record): int => (int) ($record->t_out ?? 0));

        $columns[] = ExportColumn::make('stk_total')
            ->label('Écart inv. total dépôt centrale')
            ->state(fn (Article $record): int => (int) ($record->t_stk ?? 0));

        $columns[] = ExportColumn::make('vin_total')
            ->label('Val. IN total dépôt centrale')
            ->state(fn (Article $record): float => (float) ($record->t_vin ?? 0));

        $columns[] = ExportColumn::make('vout_total')
            ->label('Val. OUT total dépôt centrale')
            ->state(fn (Article $record): float => (float) ($record->t_vout ?? 0));

        return $columns;
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('periode')
                ->label('Période')
                ->options(fn () => Periode::query()->orderByDesc('periode')->pluck('periode', 'periode')->all())
                ->default(fn () => Periode::ouvertes()->orderByDesc('periode')->value('periode'))
                ->required(),
        ];
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getFileName(Export $export): string
    {
        return 'stock-mensuel-' . ($export->getOptions()['periode'] ?? $export->getKey());
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->orderBy('parkod');
    }

    // ───────────────────────── Styles XLSX ─────────────────────────

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontSize(11)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(31, 56, 100))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(true);
    }

    /**
     * Une colonne « Qté » (qte_{code_stock} ou qte_cloture_total) ouvre
     * un nouveau groupe : c'est là qu'on met le séparateur vertical.
     */
    private function estDebutGroupe(string $column): bool
    {
        return str_starts_with($column, 'qte_');
    }

    /**
     * Bordure gauche épaisse. La couleur change selon l'en-tête (fond bleu) ou le corps.
     */
    private function styleSeparateur(bool $entete = false): Style
    {
        $couleur = $entete ? Color::WHITE : Color::rgb(89, 89, 89);

        return (new Style())->setBorder(
            new Border(
                new BorderPart(Border::LEFT, $couleur, Border::WIDTH_MEDIUM, Border::STYLE_SOLID)
            )
        );
    }

    /**
     * En-tête : tout en texte (libellé + code_stock), jamais converti en nombre.
     *
     * @param array<mixed> $values
     */
    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        $styleMerger = new StyleMerger();
        $cells = [];
        $keys = array_keys($this->columnMap);

        foreach ($values as $i => $value) {
            $cellStyle = $style;

            if (isset($keys[$i]) && $this->estDebutGroupe($keys[$i])) {
                $cellStyle = $styleMerger->merge($this->styleSeparateur(true), $style ?? new Style());
            }

            $cells[] = Cell::fromValue((string) $value, $cellStyle);
        }

        return new Row($cells, $style);
    }

    /**
     * @param array<mixed> $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $styleMerger = new StyleMerger();
        $cells = [];

        foreach (array_keys($this->columnMap) as $i => $column) {
            $value = $values[$i] ?? null;

            $estQte = $this->commencePar($column, self::PREFIXES_QTE);
            $estVal = $this->commencePar($column, self::PREFIXES_VAL);

            if ($estQte || $estVal) {
                $numStyle = (new Style())
                    ->setFormat($estVal ? '#,##0.00;[Red]-#,##0.00' : '#,##0;[Red]-#,##0')
                    ->setCellAlignment(CellAlignment::RIGHT);

                if (in_array($column, self::COLONNES_TOTAL, true)) {
                    $numStyle->setFontBold()->setBackgroundColor(Color::rgb(221, 235, 247));
                }

                if ($this->estDebutGroupe($column)) {
                    $numStyle->setBorder(
                        new Border(
                            new BorderPart(Border::LEFT, Color::rgb(89, 89, 89), Border::WIDTH_MEDIUM, Border::STYLE_SOLID)
                        )
                    );
                }

                $nombre = $estVal ? (float) $value : (int) $value;

                $cells[] = Cell::fromValue($nombre, $styleMerger->merge($numStyle, $style ?? new Style()));
            } else {
                $cells[] = Cell::fromValue((string) $value, $style);
            }
        }

        return new Row($cells, $style);
    }

    /**
     * @param array<string> $prefixes
     */
    private function commencePar(string $column, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($column, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options();

        foreach (array_keys($this->columnMap) as $i => $column) {
            $width = match (true) {
                $column === 'periode' => 11,
                $column === 'parkod' => 16,
                $column === 'description' => 48,
                $column === 'qte_cloture_total' => 22,
                $column === 'stk_total' => 24,
                in_array($column, ['in_total', 'out_total', 'vin_total', 'vout_total'], true) => 24,
                default => 18,
            };

            $options->setColumnWidth($width, $i + 1);
        }

        return $options;
    }

    public function configureXlsxWriterBeforeClose(Writer $writer): Writer
    {
        $sheetView = new SheetView();
        $sheetView->setFreezeRow(2);
        $sheetView->setFreezeColumn('D');

        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView($sheetView);
        $sheet->setName('Stock mensuel');

        return $writer;
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Export du stock mensuel terminé';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return number_format($export->successful_rows, 0, ',', ' ') . ' ligne(s) exportée(s).';
    }
}
