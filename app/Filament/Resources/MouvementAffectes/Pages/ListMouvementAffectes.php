<?php

namespace App\Filament\Resources\MouvementAffectes\Pages;

use App\Filament\Resources\MouvementAffectes\MouvementAffecteResource;
use App\Models\MouvementAffecte;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListMouvementAffectes extends ListRecords
{
    protected static string $resource = MouvementAffecteResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
    /**
     * Nombre de lignes par code_stock (une seule requête).
     *
     * @return array<string, int>
     */
    protected function getCodesStock(): array
    {
        return MouvementAffecte::query()
            ->whereNotNull('code_stock')
            ->selectRaw('code_stock, COUNT(*) as total')
            ->groupBy('code_stock')
            ->orderBy('code_stock')
            ->pluck('total', 'code_stock')
            ->all();
    }

    public function getTabs(): array
    {
        $tabs = [];

        foreach ($this->getCodesStock() as $code => $total) {
            $tabs[$code] = Tab::make($code)
                ->badge($total)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('code_stock', $code));
        }

        // Onglet global, placé en dernier
        $tabs['tous'] = Tab::make('Tous')
            ->badge(array_sum($this->getCodesStock()));

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return array_key_first($this->getCodesStock());
    }
}
