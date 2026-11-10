<?php

namespace App\Filament\Exports;

use App\Models\Article;
use App\Models\Periode;
use App\Models\Societe;
use App\Models\StockMensuel;
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

    /** Cache par période : [parkod => [code_stock => qte_cloture]] */
    protected static array $pivot = [];

    protected static function pivot(string $periode): array
    {
        return self::$pivot[$periode] ??= StockMensuel::query()
            ->pourPeriode($periode)
            ->get(['parkod', 'code_stock', 'qte_cloture'])
            ->groupBy('parkod')
            ->map(fn ($rows) => $rows->pluck('qte_cloture', 'code_stock')->all())
            ->all();
    }

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

        foreach ($societes as $code => $libelle) {
            $columns[] = ExportColumn::make("qte_{$code}")
                ->label(($libelle ?: $code) . " ({$code})")
                ->state(fn (Article $record, array $options): int => (int) (
                    self::pivot($options['periode'])[$record->parkod][$code] ?? 0
                ));
        }

        $columns[] = ExportColumn::make('qte_cloture_total')
            ->label('Total stock dépôt centrale')
            ->state(fn (Article $record, array $options): int => (int) array_sum(
                self::pivot($options['periode'])[$record->parkod] ?? []
            ));

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

            if (str_starts_with($column, 'qte_')) {
                $numStyle = (new Style())
                    ->setFormat('#,##0;[Red]-#,##0')
                    ->setCellAlignment(CellAlignment::RIGHT);

                if ($column === 'qte_cloture_total') {
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
