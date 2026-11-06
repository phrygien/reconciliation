<?php

namespace App\Filament\Exports;

use App\Models\MouvementAffecte;
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
use OpenSpout\Writer\Common\Manager\Style\StyleMerger;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class MouvementAffecteExporter extends Exporter
{
    protected static ?string $model = MouvementAffecte::class;

    /** Largeur des colonnes (en caractères) par nom de colonne. */
    private const WIDTHS = [
        'sens'                => 8,
        'code_stock'          => 14,
        'societe_libelle'     => 28,
        'date_comptable'      => 16,
        'parkod'              => 18,
        'article_description' => 40,
        'typ_mvt'             => 12,
        'mag_orig'            => 16,
        'mag_dest'            => 18,
        'qte'                 => 12,
        'valeur'              => 16,
        'mode_affect'         => 14,
        'mouvement_brut_id'   => 12,
        'created_at'          => 18,
    ];

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('sens')
                ->label('Sens')
                ->state(fn (MouvementAffecte $record) => $record->sens),

            ExportColumn::make('code_stock')
                ->label('Code stock'),

            ExportColumn::make('societe_libelle')
                ->label('Société')
                ->state(fn (MouvementAffecte $record) => $record->societe?->libelle),

            ExportColumn::make('date_comptable')
                ->label('Date comptable')
                ->state(fn (MouvementAffecte $record) => $record->date_comptable?->format('d/m/Y')),

            ExportColumn::make('parkod')
                ->label('Article')
                ->state(fn (MouvementAffecte $record) => $record->mouvementBrut?->parkod),

            ExportColumn::make('article_description')
                ->label('Désignation')
                ->state(fn (MouvementAffecte $record) => $record->mouvementBrut?->article?->description),

            ExportColumn::make('typ_mvt')
                ->label('Type mvt')
                ->state(fn (MouvementAffecte $record) => $record->mouvementBrut?->typ_mvt),

            ExportColumn::make('mag_orig')
                ->label('Mag. origine'),

            ExportColumn::make('mag_dest')
                ->label('Mag. destination'),

            ExportColumn::make('qte')
                ->label('Qté')
                ->state(fn (MouvementAffecte $record) => (int) $record->qte),

            ExportColumn::make('valeur')
                ->label('Valeur')
                ->state(fn (MouvementAffecte $record) => (float) $record->valeur),

            ExportColumn::make('mode_affect')
                ->label('Mode')
                ->formatStateUsing(fn (string $state) => match ($state) {
                    MouvementAffecte::MODE_AUTO => 'Automatique',
                    MouvementAffecte::MODE_REPARTI => 'Réparti',
                    MouvementAffecte::MODE_MANUEL => 'Manuel',
                    default => $state,
                }),

            ExportColumn::make('mouvement_brut_id')
                ->label('N° brut')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Créé le')
                ->state(fn (MouvementAffecte $record) => $record->created_at?->format('d/m/Y H:i'))
                ->enabledByDefault(false),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['societe', 'mouvementBrut.article']);
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getFileName(Export $export): string
    {
        return 'mouvements-affectes-' . now()->format('Ymd-His');
    }

    /** Style de base des cellules : police + bordure fine. */
    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontSize(11)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->border());
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style())
            ->setFontName('Calibri')
            ->setFontSize(12)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(30, 64, 175))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(false)
            ->setBorder($this->border(Color::rgb(30, 64, 175)));
    }

    /**
     * Style par colonne : formats numériques, alignements, couleurs.
     *
     * @param array<mixed> $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $merger = new StyleMerger();
        $cells = [];

        foreach (array_keys($this->columnMap) as $index => $column) {
            $value = $values[$index] ?? null;

            $custom = match ($column) {
                'sens' => (new Style())
                    ->setFontBold()
                    ->setCellAlignment(CellAlignment::CENTER)
                    ->setFontColor($value === 'IN' ? Color::rgb(22, 128, 61) : Color::rgb(185, 28, 28))
                    ->setBackgroundColor($value === 'IN' ? Color::rgb(220, 252, 231) : Color::rgb(254, 226, 226)),

                'qte' => (new Style())
                    ->setFormat('#,##0;-#,##0')
                    ->setCellAlignment(CellAlignment::RIGHT)
                    ->setFontColor($this->signColor($value)),

                'valeur' => (new Style())
                    ->setFormat('#,##0.00;-#,##0.00')
                    ->setCellAlignment(CellAlignment::RIGHT)
                    ->setFontColor($this->signColor($value)),

                'code_stock', 'typ_mvt', 'date_comptable', 'mode_affect', 'mouvement_brut_id', 'created_at'
                => (new Style())->setCellAlignment(CellAlignment::CENTER),

                'parkod' => (new Style())->setFontBold(),

                default => null,
            };

            $cells[] = Cell::fromValue(
                $value,
                $custom ? $merger->merge($custom, $style) : $style,
            );
        }

        return new Row($cells, $style);
    }

    /** Largeurs de colonnes selon les colonnes réellement exportées. */
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
        $sheetView->setFreezeRow(2);

        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView($sheetView);
        $sheet->setName('Mouvements affectés');

        return $writer;
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Export des mouvements affectés terminé';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return number_format($export->successful_rows, 0, ',', ' ') . ' ligne(s) exportée(s).';
    }

    private function signColor(mixed $value): string
    {
        return is_numeric($value) && (float) $value < 0
            ? Color::rgb(185, 28, 28)
            : Color::rgb(22, 128, 61);
    }

    private function border(string $color = 'D1D5DB'): Border
    {
        $color = $color === 'D1D5DB' ? Color::rgb(209, 213, 219) : $color;

        return new Border(
            new BorderPart(Border::TOP, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );
    }
}
