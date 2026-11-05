<?php

namespace App\Filament\Exports;

use App\Models\StockMensuel;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
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

class StockMensuelExporter extends Exporter
{
    protected static ?string $model = StockMensuel::class;
    private const QTE = ['qte_ouverture', 'qte_in', 'qte_out', 'qte_stk', 'qte_cloture'];
    private const VAL = ['val_ouverture', 'val_in', 'val_out', 'val_stk', 'val_cloture'];

    private const WIDTHS = [
        'periode'         => 11,
        'code_stock'      => 12,
        'societe.libelle' => 18,
        'parkod'          => 14,
        'article.description' => 38,
        'qte_ouverture'   => 14,
        'val_ouverture'   => 16,
        'qte_in'          => 14,
        'val_in'          => 16,
        'qte_out'         => 14,
        'val_out'         => 16,
        'qte_stk'         => 18,
        'val_stk'         => 18,
        'qte_cloture'     => 14,
        'val_cloture'     => 16,
        'pamp_cloture'    => 14,
        'coherence'       => 12,
    ];

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['societe', 'article']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('periode')->label('Période'),
            ExportColumn::make('code_stock')->label('Code stock'),
            ExportColumn::make('societe.libelle')->label('Société'),
            ExportColumn::make('parkod')->label('Parkod'),
            ExportColumn::make('article.description')->label('Description article'),

            ExportColumn::make('qte_ouverture')->label('Qté ouverture'),
            ExportColumn::make('val_ouverture')->label('Val ouverture'),
            ExportColumn::make('qte_in')->label('Qté entrées'),
            ExportColumn::make('val_in')->label('Val entrées'),
            ExportColumn::make('qte_out')->label('Qté sorties'),
            ExportColumn::make('val_out')->label('Val sorties'),
            ExportColumn::make('qte_stk')->label('Qté écart inventaire'),
            ExportColumn::make('val_stk')->label('Val écart inventaire'),
            ExportColumn::make('qte_cloture')->label('Qté clôture'),
            ExportColumn::make('val_cloture')->label('Val clôture'),
            ExportColumn::make('pamp_cloture')->label('PAMP clôture'),

            ExportColumn::make('coherence')
                ->label('Cohérence')
                ->state(fn (StockMensuel $record): string => $record->estCoherent() ? 'OK' : 'Écart'),
        ];
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getFileName(Export $export): string
    {
        return 'stock-mensuel-' . now()->format('Y-m-d-His');
    }
    private function bordure(): Border
    {
        return new Border(
            new BorderPart(Border::BOTTOM, Color::rgb(209, 213, 219), Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::rgb(229, 231, 235), Border::WIDTH_THIN, Border::STYLE_SOLID),
        );
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontSize(11)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->bordure());
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(31, 41, 55))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(true)
            ->setBorder($this->bordure());
    }

    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $cells = [];

        foreach (array_keys($this->columnMap) as $i => $column) {
            $value = $values[$i] ?? null;

            $cell = match (true) {
                in_array($column, self::QTE, true) => Cell::fromValue(
                    (int) $value,
                    $this->style($style, '#,##0;[Red]-#,##0', CellAlignment::RIGHT),
                ),

                in_array($column, self::VAL, true) => Cell::fromValue(
                    (float) $value,
                    $this->style($style, '#,##0.00;[Red]-#,##0.00', CellAlignment::RIGHT),
                ),

                $column === 'pamp_cloture' => Cell::fromValue(
                    (float) $value,
                    $this->style($style, '#,##0.0000', CellAlignment::RIGHT),
                ),

                $column === 'coherence' => Cell::fromValue(
                    $value,
                    $this->style($style, null, CellAlignment::CENTER, bold: true, color: $value === 'OK'
                        ? Color::rgb(22, 163, 74)
                        : Color::rgb(220, 38, 38)),
                ),

                default => Cell::fromValue($value, $style),
            };

            $cells[] = $cell;
        }

        return new Row($cells, $style);
    }

    private function style(
        ?Style $base,
        ?string $format,
        string $alignment,
        bool $bold = false,
        ?string $color = null,
    ): Style {
        $style = (new Style())
            ->setFontName('Calibri')
            ->setFontSize(11)
            ->setCellAlignment($alignment)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->bordure());

        if ($format) {
            $style->setFormat($format);
        }

        if ($bold) {
            $style->setFontBold();
        }

        if ($color) {
            $style->setFontColor($color);
        }

        return $style;
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options();

        // Largeurs adaptées aux colonnes réellement sélectionnées
        foreach (array_keys($this->columnMap) as $i => $column) {
            $options->setColumnWidth(self::WIDTHS[$column] ?? 15, $i + 1);
        }

        return $options;
    }

    public function configureXlsxWriterBeforeClose(Writer $writer): Writer
    {
        $sheetView = new SheetView();
        $sheetView->setFreezeRow(2);
        $sheetView->setFreezeColumn('E');

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
