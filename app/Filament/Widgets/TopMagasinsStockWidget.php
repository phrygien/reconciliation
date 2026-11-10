<?php

namespace App\Filament\Widgets;

use App\Models\Magasin;
use App\Models\StockMensuel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopMagasinsStockWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'TOP magasins par stock';

    /** Dernière période ayant du stock, ouverte ou clôturée. */
    private function periode(): ?string
    {
        return StockMensuel::query()->max('periode');
    }

    public function table(Table $table): Table
    {
        $periode = $this->periode();

        $totalQte = (int) StockMensuel::query()
            ->when($periode, fn (Builder $q) => $q->pourPeriode($periode))
            ->sum('qte_cloture');

        return $table
            ->heading('TOP magasins par stock' . ($periode ? " — période {$periode}" : ''))
            ->query(
                StockMensuel::query()
                    ->with('societe')
                    ->when($periode, fn (Builder $q) => $q->pourPeriode($periode))
                    ->selectRaw('MIN(id) as id, code_stock, SUM(qte_cloture) as qte_total')
                    ->groupBy('code_stock')
                    ->orderByDesc('qte_total')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('rang')
                    ->label('#')
                    ->rowIndex(),

                TextColumn::make('code_stock')
                    ->label('Code stock')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('societe.libelle')
                    ->label('Société')
                    ->placeholder('—'),

                TextColumn::make('nb_magasins')
                    ->label('Magasins')
                    ->alignEnd()
                    ->state(fn (StockMensuel $record): int => $record->societe
                        ? Magasin::query()->where('societe_id', $record->societe->id)->count()
                        : 0),

                TextColumn::make('qte_total')
                    ->label('Qté clôture')
                    ->numeric(decimalPlaces: 0, locale: 'fr')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('part')
                    ->label('Part')
                    ->alignEnd()
                    ->state(fn (StockMensuel $record): string => $totalQte > 0
                        ? number_format($record->qte_total / $totalQte * 100, 1, ',', ' ') . ' %'
                        : '—'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Aucun stock pour la période');
    }
}
