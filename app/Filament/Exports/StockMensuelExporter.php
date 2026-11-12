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

    /**
     * Les valeurs s{i}_qte, s{i}_stk, t_qte, t_stk sont calculées en SQL
     * par StockMensuelsTable (ExportAction::modifyQueryUsing).
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

            $columns[] = ExportColumn::make("stk_{$code}")
                ->label('Écart inv. ' . $nom)
                ->state(fn (Article $record): int => (int) ($record->{"s{$alias}_stk"} ?? 0));

            $i++;
        }

        $columns[] = ExportColumn::make('qte_cloture_total')
            ->label('Total stock dépôt centrale')
            ->state(fn (Article $record): int => (int) ($record->t_qte ?? 0));

        $columns[] = ExportColumn::make('stk_total')
            ->label('Écart inv. total dépôt centrale')
            ->state(fn (Article $record): int => (int) ($record->t_stk ?? 0));

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
     * En-tête : tout en texte (libellé + code_stock), jamais converti en nombre.
     *
     * @param array<mixed> $values
     */
    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        $cells = [];

        foreach ($values as $value) {
            $cells[] = Cell::fromValue((string) $value, $style);
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

            if (str_starts_with($column, 'qte_') || str_starts_with($column, 'stk_')) {
                $numStyle = (new Style())
                    ->setFormat('#,##0;[Red]-#,##0')
                    ->setCellAlignment(CellAlignment::RIGHT);

                if ($column === 'qte_cloture_total' || $column === 'stk_total') {
                    $numStyle->setFontBold()->setBackgroundColor(Color::rgb(221, 235, 247));
                }

                $cells[] = Cell::fromValue((int) $value, $styleMerger->merge($numStyle, $style));
            } else {
                $cells[] = Cell::fromValue((string) $value, $style);
            }
        }

        return new Row($cells, $style);
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
