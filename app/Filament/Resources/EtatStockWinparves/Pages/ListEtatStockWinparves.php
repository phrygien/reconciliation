<?php

namespace App\Filament\Resources\EtatStockWinparves\Pages;

use App\Filament\Resources\EtatStockWinparves\EtatStockWinparfResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEtatStockWinparves extends ListRecords
{
    protected static string $resource = EtatStockWinparfResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $model = static::getResource()::getModel();

        $counts = $model::query()
            ->whereNotNull('code_stock')
            ->selectRaw('code_stock, COUNT(*) as total')
            ->groupBy('code_stock')
            ->orderBy('code_stock')
            ->pluck('total', 'code_stock');

        $tabs = [
            'all' => Tab::make('Tous')
                ->badge($counts->sum()),
        ];

        foreach ($counts as $code => $total) {
            $tabs[(string) $code] = Tab::make((string) $code)
                ->badge($total)
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('code_stock', $code)
                );
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
