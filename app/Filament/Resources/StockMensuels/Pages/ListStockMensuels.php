<?php

namespace App\Filament\Resources\StockMensuels\Pages;

use App\Filament\Resources\StockMensuels\StockMensuelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListStockMensuels extends ListRecords
{
    protected static string $resource = StockMensuelResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
