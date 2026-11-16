<?php

namespace App\Filament\Exports;

use App\Models\MouvementBrut;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
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

class MouvementBrutExporter extends Exporter
{
    protected static ?string $model = MouvementBrut::class;

    /** Largeur Excel par colonne. */
    protected const WIDTHS = [
        'date_mvt'                   => 18,
        'in_out'                     => 10,
        'typ_mvt'                    => 12,
        'mag_orig'                   => 12,
        'magasinOrigine.libelle'     => 28,
        'mag_dest'                   => 12,
        'magasinDestination.libelle' => 28,
        'parkod'                     => 16,
        'ean'                        => 18,
        'article.description'        => 45,
        'qte'                        => 12,
        'montant'                    => 16,
    ];

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('date_mvt')
                ->label('Date')
                ->formatStateUsing(fn ($state): ?string => $state ? Carbon::parse($state)->format('d/m/Y H:i') : null),
            ExportColumn::make('in_out')
                ->label('IN/OUT'),
            ExportColumn::make('typ_mvt')
                ->label('Type'),
            ExportColumn::make('mag_orig')
                ->label('Magasin origine'),
            ExportColumn::make('magasinOrigine.libelle')
                ->label('Libellé origine'),
            ExportColumn::make('mag_dest')
                ->label('Magasin destination'),
            ExportColumn::make('magasinDestination.libelle')
                ->label('Libellé destination'),
            ExportColumn::make('parkod')
                ->label('Article'),
            ExportColumn::make('ean')
                ->label('EAN')
                ->formatStateUsing(fn ($state): ?string => $state !== null ? (string) $state : null),
            ExportColumn::make('article.description')
                ->label('Description'),
            ExportColumn::make('qte')
                ->label('Qté'),
            ExportColumn::make('montant')
                ->label('Montant'),
        ];
    }

    // ---------------------------------------------------------------
    // Styles Excel
    // ---------------------------------------------------------------

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontSize(10)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder(new Border(
                new BorderPart(Border::BOTTOM, Color::rgb(209, 213, 219), Border::WIDTH_THIN, Border::STYLE_SOLID),
            ));
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontSize(11)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(30, 64, 175))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(true);
    }

    /**
     * @param array<mixed> $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $merger = new StyleMerger();
        $cells = [];

        foreach (array_keys($this->columnMap) as $index => $column) {
            $value = $values[$index] ?? null;

            $cells[] = match ($column) {
                'qte' => Cell::fromValue(
                    $value === null || $value === '' ? null : (int) $value,
                    $merger->merge(
                        (new Style())->setFormat('#,##0')->setCellAlignment(CellAlignment::RIGHT),
                        $style,
                    ),
                ),
                'montant' => Cell::fromValue(
                    $value === null || $value === '' ? null : (float) $value,
                    $merger->merge(
                        (new Style())->setFormat('#,##0.00')->setCellAlignment(CellAlignment::RIGHT),
                        $style,
                    ),
                ),
                'in_out' => Cell::fromValue(
                    $value,
                    $merger->merge(
                        (new Style())
                            ->setFontBold()
                            ->setFontColor(
                                str_starts_with(strtoupper((string) $value), 'I')
                                    ? Color::rgb(22, 163, 74)
                                    : Color::rgb(220, 38, 38)
                            )
                            ->setCellAlignment(CellAlignment::CENTER),
                        $style,
                    ),
                ),
                'typ_mvt', 'mag_orig', 'mag_dest' => Cell::fromValue(
                    $value,
                    $merger->merge((new Style())->setCellAlignment(CellAlignment::CENTER), $style),
                ),
                'article.description' => Cell::fromValue(
                    $value,
                    $merger->merge((new Style())->setShouldWrapText(true), $style),
                ),
                default => Cell::fromValue($value, $style),
            };
        }

        return new Row($cells, $style);
    }

    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        return Row::fromValues($values, $style);
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options();

        foreach (array_keys($this->columnMap) as $index => $column) {
            $options->setColumnWidth(self::WIDTHS[$column] ?? 15, $index + 1);
        }

        return $options;
    }

    public function configureXlsxWriterBeforeClose(Writer $writer): Writer
    {
        $sheetView = new SheetView();
        $sheetView->setFreezeRow(2); // fige la ligne d'en-tête

        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView($sheetView);
        $sheet->setName('Mouvements OUT');

        return $writer;
    }

    // ---------------------------------------------------------------
    // Fichier / notification
    // ---------------------------------------------------------------

    public function getFileName(Export $export): string
    {
        $magasin = $this->options['magasin'] ?? 'magasin';
        $periode = $this->options['periode'] ?? 'toutes-periodes';

        return "mouvements-out-{$magasin}-{$periode}-{$export->getKey()}";
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export des mouvements OUT terminé : ' . Number::format($export->successful_rows) . ' ' . str('ligne')->plural($export->successful_rows) . ' exportée(s).';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('ligne')->plural($failedRowsCount) . ' en échec.';
        }

        return $body;
    }
}
